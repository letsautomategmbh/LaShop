<?php

namespace Modules\LaStore\Tests\Unit;

use Modules\LaStore\Support\InstalledModules;
use PHPUnit\Framework\TestCase;

/**
 * Was als Updateweg an der Signatur vorbei gilt.
 *
 * Am 04.09.2026 sind `latestVersionUrl` und `latestVersionZipUrl` aus allen
 * elf Repositories entfernt. Bei sieben davon steckte das GitHub-Token IN
 * der Adresse -- die Felder waren also beides zugleich: ein Geheimnis und
 * ein Weg, auf dem FreeScouts eingebauter Updater UNGEPRUEFT nach Modules/
 * entpackt.
 *
 * Gemeldet wird seitdem der Weg, nicht das Token. Der Unterschied ist nicht
 * kosmetisch: ein Feld OHNE Token ist derselbe Weg, nur unbenutzbar -- und
 * die alte Pruefung auf Token-Praefixe hat es uebersehen.
 *
 * Und es bleibt zu pruefen, obwohl unsere Repositories sauber sind: eine
 * Anlage, die vor diesem Datum eingerichtet wurde, traegt die Felder
 * weiterhin. Ein Repository zu aendern aendert keine ausgelieferte Kopie.
 *
 * Ohne Laravel und ohne Modulsystem: die Methode braucht von `$module` nur
 * getPath(), also genuegt ein Doppel mit genau dieser einen Methode.
 */
class UnsignedUpdatePathTest extends TestCase
{
    /** @var string */
    private $ordner;

    protected function setUp(): void
    {
        $this->ordner = sys_get_temp_dir().'/lashop-modul-'.getmypid().'-'.count(get_included_files());
        @mkdir($this->ordner, 0775, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->ordner.'/module.json');
        @rmdir($this->ordner);
    }

    private function befund(?array $json): ?string
    {
        if ($json !== null) {
            file_put_contents($this->ordner.'/module.json', json_encode($json));
        }

        $modul = new class($this->ordner) {
            private $p;

            public function __construct($p)
            {
                $this->p = $p;
            }

            public function getPath()
            {
                return $this->p;
            }
        };

        $m = new \ReflectionMethod(InstalledModules::class, 'unsignedUpdatePath');

        return $m->invoke(null, $modul);
    }

    public function testEineSauberrModuleJsonMeldetNichts()
    {
        $this->assertNull($this->befund(array('name' => 'Vault', 'version' => '0.9.22')));
    }

    public function testEinFeldOhneTokenIstTrotzdemEinFremderUpdateweg()
    {
        // Der Kern der Aenderung. Die alte Pruefung suchte Token-Praefixe und
        // liess genau diesen Fall durch -- vier unserer elf Module hatten die
        // Felder ohne Token darin, bei Backup zeigte sie schon auf 404.
        $this->assertSame(
            'Fremder Updateweg',
            $this->befund(array(
                'name'             => 'Backup',
                'latestVersionUrl' => 'https://api.github.com/repos/beispiel/Backup/releases/latest',
            ))
        );
    }

    public function testMitZugangsdatenIstDieSchaerfereAuskunft()
    {
        // Zwei Lagen unterscheiden, weil beim Aufraeumen im zweiten Fall ein
        // Geheimnis mitgeht -- das soll der Verwalter wissen.
        $this->assertSame(
            'Fremder Updateweg, mit Zugangsdaten',
            $this->befund(array(
                'name'                => 'Bexio',
                'latestVersionZipUrl' => 'https://irgendwer:geheim@example.invalid/Bexio.zip',
            ))
        );
    }

    public function testAuchDasZweiteFeldZaehlt()
    {
        $this->assertSame(
            'Fremder Updateweg',
            $this->befund(array('latestVersionZipUrl' => 'https://example.invalid/x.zip'))
        );
    }

    public function testEinLeeresFeldIstKeinWeg()
    {
        // Ein Feld, das dasteht und leer ist, fuehrt nirgendwohin. Es zu
        // melden hiesse, den Verwalter auf etwas zu schicken, das er nicht
        // aufraeumen kann.
        $this->assertNull($this->befund(array('latestVersionUrl' => '')));
    }

    public function testOhneModuleJsonKeinBefund()
    {
        // Kein Urteil ueber etwas, das nicht gelesen werden konnte.
        $this->assertNull($this->befund(null));
    }

    public function testKaputtesJsonFaelltAufDieRohsucheZurueck()
    {
        // json_decode gibt null, und dann bleibt nur der Text. Eine Adresse
        // mit Zugangsdaten soll auch dann auffallen -- eine unlesbare
        // module.json ist kein Grund, wegzusehen.
        file_put_contents($this->ordner.'/module.json', '{ das ist kein JSON https://wer:geheim@example.invalid }');

        $this->assertSame('Zugangsdaten in der URL', $this->befund(null));
    }
}
