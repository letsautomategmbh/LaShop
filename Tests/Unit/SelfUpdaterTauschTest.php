<?php

namespace Modules\LaStore\Tests\Unit;

use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\SelfUpdater;
use PHPUnit\Framework\TestCase;

/**
 * Der Tausch — der einzige Schritt, an dem etwas fehlen könnte.
 *
 * Alles davor lässt sich wiederholen: eine gescheiterte Signaturprüfung, ein
 * abgebrochener Download, ein unbrauchbares Archiv. Der Tausch nicht — er
 * greift in das laufende Modul. Darum ist er zwei Umbenennungen und hat einen
 * Rückweg, und darum steht er hier unter Prüfung.
 *
 * Über Reflexion, weil die Methode privat ist und privat bleiben soll: sie ist
 * kein Angebot an Aufrufer, sondern ein Schritt in einem Ablauf.
 */
class SelfUpdaterTauschTest extends TestCase
{
    /** @var string */
    private $wurzel;

    protected function setUp(): void
    {
        $this->wurzel = sys_get_temp_dir().'/lashop-test-'.substr(md5(uniqid('', true)), 0, 8);
        mkdir($this->wurzel.'/Modules/LaStore', 0755, true);
        mkdir($this->wurzel.'/bau/LaStore', 0755, true);
        file_put_contents($this->wurzel.'/Modules/LaStore/alt.txt', 'der alte Stand');
        file_put_contents($this->wurzel.'/bau/LaStore/neu.txt', 'der neue Stand');
    }

    protected function tearDown(): void
    {
        $this->weg($this->wurzel);
    }

    private function tauschen()
    {
        $m = new \ReflectionMethod(SelfUpdater::class, 'tauschen');
        $m->setAccessible(true);

        return $m->invoke(null, $this->wurzel.'/bau', $this->wurzel.'/Modules');
    }

    public function test_der_neue_stand_liegt_danach_am_platz()
    {
        $alt = $this->tauschen();

        $this->assertFileExists($this->wurzel.'/Modules/LaStore/neu.txt');
        $this->assertFileDoesNotExist($this->wurzel.'/Modules/LaStore/alt.txt');
        $this->assertNotSame('', $alt);
    }

    /**
     * Der bisherige Stand bleibt liegen — wer nach einem Update etwas
     * vermisst, hat den Vorgänger noch auf der Platte.
     */
    public function test_der_alte_stand_bleibt_als_sicherung()
    {
        $alt = $this->tauschen();

        $this->assertDirectoryExists($alt);
        $this->assertFileExists($alt.'/alt.txt');
        $this->assertStringContainsString('lastore-alt-', $alt);
    }

    public function test_ohne_laufenden_stand_ist_es_eine_installation()
    {
        $this->weg($this->wurzel.'/Modules/LaStore');

        $alt = $this->tauschen();

        $this->assertSame('', $alt, 'ohne Vorgänger gibt es keine Sicherung');
        $this->assertFileExists($this->wurzel.'/Modules/LaStore/neu.txt');
    }

    /**
     * Die Sperre, die eine Entwicklungsumgebung schützt: der Tausch ersetzt
     * das ganze Verzeichnis, und dort ist es die Git-Arbeitskopie.
     */
    public function test_eine_git_arbeitskopie_wird_nicht_getauscht()
    {
        mkdir($this->wurzel.'/Modules/LaStore/.git', 0755, true);

        try {
            $this->tauschen();
            $this->fail('Der Tausch hätte abbrechen müssen.');
        } catch (StoreException $e) {
            // errorCode() und nicht getCode(): der zweite ist der
            // HTTP-Status und hier 0.
            $this->assertSame('working_copy', $e->errorCode());
        }

        // Und nichts angefasst.
        $this->assertFileExists($this->wurzel.'/Modules/LaStore/alt.txt');
        $this->assertFileExists($this->wurzel.'/bau/LaStore/neu.txt');
    }

    /**
     * So kommt das Archiv wirklich vom Shop: der oberste Ordner heisst
     * "LaShop".
     *
     * Der Shop benennt ihn beim Einliefern auf das Feld "name" aus der
     * module.json um — richtig so, denn daraus wird bei jedem anderen Modul
     * der Zielordner. Nur bei uns weichen die beiden ab: das Verzeichnis
     * heisst LaStore, das Modul heisst LaShop. Der Tausch suchte "LaStore"
     * im Archiv, fand es nicht und meldete "Im Archiv fehlt der Ordner
     * LaStore" — bei einem völlig einwandfreien Paket. LaShop konnte sich
     * also nicht selbst aktualisieren, und aufgefallen ist es erst, als der
     * Weg gebraucht wurde.
     *
     * Der übrige Teil dieser Klasse prüft mit "LaStore" im Archiv und wäre
     * auch vorher grün gewesen. Deshalb dieser Fall EXTRA.
     */
    public function test_der_oberste_ordner_im_archiv_darf_anders_heissen()
    {
        $this->weg($this->wurzel.'/bau/LaStore');
        mkdir($this->wurzel.'/bau/LaShop', 0755, true);
        file_put_contents($this->wurzel.'/bau/LaShop/neu.txt', 'der neue Stand');

        $alt = $this->tauschen();

        $this->assertFileExists(
            $this->wurzel.'/Modules/LaStore/neu.txt',
            'Getauscht wird in unser Verzeichnis — unabhängig davon, wie der Ordner im Archiv heisst.'
        );
        $this->assertFileExists($alt.'/alt.txt');
    }

    public function test_zwei_oberste_ordner_sind_kein_modul()
    {
        // Raten wäre hier das Falscheste: ein Archiv mit zwei Ordnern ist
        // etwas anderes als ein Modul, und der Tausch ist nicht umkehrbar.
        mkdir($this->wurzel.'/bau/NochEiner', 0755, true);

        try {
            $this->tauschen();
            $this->fail('Der Tausch hätte abbrechen müssen.');
        } catch (StoreException $e) {
            $this->assertSame('bad_package_shape', $e->errorCode());
        }

        $this->assertFileExists($this->wurzel.'/Modules/LaStore/alt.txt');
    }

    private function weg($pfad)
    {
        if (!is_dir($pfad)) {
            @unlink($pfad);

            return;
        }

        $eintraege = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pfad, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($eintraege as $eintrag) {
            $eintrag->isDir() ? @rmdir($eintrag->getPathname()) : @unlink($eintrag->getPathname());
        }

        @rmdir($pfad);
    }
}
