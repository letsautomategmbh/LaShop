<?php

namespace Modules\LaStore\Tests\Unit;

use Modules\LaStore\Services\PackageInstaller;
use Modules\LaStore\Services\StoreException;
use PHPUnit\Framework\TestCase;

/**
 * Das Entpacken und der Tausch.
 *
 * Die Signatur sagt, dass ein Archiv von UNS ist. Sie sagt nicht, dass es
 * harmlos ist - ein Fehler unsererseits waere ebenso signiert. Darum wird
 * auch nach der Pruefung noch auf den Aufbau geschaut, und darum liegt der
 * alte Stand beiseite, bevor etwas ersetzt wird.
 */
class PackageInstallerTest extends TestCase
{
    /** @var string */
    protected $arbeit;

    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('\ZipArchive')) {
            $this->markTestSkipped('ZipArchive fehlt.');
        }

        $this->arbeit = sys_get_temp_dir().'/lastore-installertest-'.bin2hex(random_bytes(5));
        mkdir($this->arbeit.'/Modules', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->wegRaeumen($this->arbeit);
        parent::tearDown();
    }

    protected function wegRaeumen($pfad)
    {
        if (is_dir($pfad)) {
            foreach (array_diff(scandir($pfad), array('.', '..')) as $e) {
                $this->wegRaeumen($pfad.'/'.$e);
            }
            @rmdir($pfad);

            return;
        }

        @unlink($pfad);
    }

    /** @return string Pfad zum ZIP */
    protected function archiv(array $eintraege)
    {
        $pfad = $this->arbeit.'/paket-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new \ZipArchive();
        $zip->open($pfad, \ZipArchive::CREATE);

        foreach ($eintraege as $name => $inhalt) {
            $zip->addFromString($name, $inhalt);
        }

        $zip->close();

        return $pfad;
    }

    protected function installer()
    {
        // Der Modulpfad wird ueber die Konfiguration bestimmt. Fuer den Test
        // zeigt er in ein Wegwerfverzeichnis - es soll kein Test ein echtes
        // Modul ersetzen.
        $arbeit = $this->arbeit;

        return new class(null, $arbeit) extends PackageInstaller {
            protected $testPfad;

            public function __construct($client, $pfad)
            {
                $this->testPfad = $pfad.'/Modules';
            }

            protected function modulesPath()
            {
                return $this->testPfad;
            }

            // Nur fuer den Test nach aussen gelegt: die Lage des
            // Arbeitsverzeichnisses IST die Zusicherung, um die es hier geht.
            public function arbeitsDirOeffentlich($prefix)
            {
                return $this->arbeitsDir($prefix);
            }

            public function wegraeumenOeffentlich()
            {
                $this->alteArbeitWegraeumen();
            }

            public function modulesPathOeffentlich()
            {
                return $this->modulesPath();
            }
        };
    }

    public function testEinSauberesArchivWirdInstalliert()
    {
        $zip = $this->archiv(array(
            'Backup/module.json' => json_encode(array('name' => 'Backup', 'alias' => 'backup', 'version' => '1.9.5')),
            'Backup/Providers/X.php' => '<?php',
        ));

        $ergebnis = $this->installer()->install($zip, 'backup');

        $this->assertSame('Backup', $ergebnis['name']);
        $this->assertSame('1.9.5', $ergebnis['version']);
        $this->assertNull($ergebnis['backup'], 'Ohne Vorgänger gibt es nichts beiseitezulegen.');
        $this->assertFileExists($this->arbeit.'/Modules/Backup/module.json');
        $this->assertFileDoesNotExist($zip, 'Das Paket wird nach der Installation entfernt.');
    }

    public function testDerBisherigeStandWirdBeiseitegelegtUndNichtGeloescht()
    {
        mkdir($this->arbeit.'/Modules/Backup', 0777, true);
        file_put_contents($this->arbeit.'/Modules/Backup/alt.txt', 'wichtig');

        $zip = $this->archiv(array(
            'Backup/module.json' => json_encode(array('name' => 'Backup', 'alias' => 'backup', 'version' => '2.0.0')),
        ));

        $ergebnis = $this->installer()->install($zip, 'backup');

        $this->assertNotNull($ergebnis['backup']);
        $this->assertFileExists($ergebnis['backup'].'/alt.txt', 'Der alte Stand muss zurückholbar bleiben.');
        $this->assertFileExists($this->arbeit.'/Modules/Backup/module.json');
        $this->assertFileDoesNotExist($this->arbeit.'/Modules/Backup/alt.txt');
    }

    public function testEinPaketMitFalschemAliasWirdAbgelehnt()
    {
        // Signiert waere das trotzdem - beide Module sind von uns. Nur
        // angefordert war ein anderes.
        $zip = $this->archiv(array(
            'Vault/module.json' => json_encode(array('name' => 'Vault', 'alias' => 'vault', 'version' => '1.0.0')),
        ));

        $this->expectException(StoreException::class);
        $this->expectExceptionMessageMatches('/vault.*backup|backup.*vault/is');

        $this->installer()->install($zip, 'backup');
    }

    public function testZipSlipWirdAbgelehnt()
    {
        // Der klassische Angriff: ein Eintrag, der aus dem Zielverzeichnis
        // hinausschreibt. Die Signatur schuetzt davor nicht.
        $zip = $this->archiv(array(
            'Backup/module.json' => json_encode(array('name' => 'Backup', 'alias' => 'backup')),
            'Backup/../../../config/app.php' => '<?php // uebernommen',
        ));

        $this->expectException(StoreException::class);

        try {
            $this->installer()->install($zip, 'backup');
        } catch (StoreException $e) {
            $this->assertSame('unsafe_path', $e->errorCode());
            $this->assertFileDoesNotExist($this->arbeit.'/Modules/Backup');
            throw $e;
        }
    }

    public function testZweiObersteOrdnerWerdenAbgelehnt()
    {
        $zip = $this->archiv(array(
            'Backup/module.json' => json_encode(array('name' => 'Backup', 'alias' => 'backup')),
            'Sonstwas/datei.txt' => 'x',
        ));

        $this->expectException(StoreException::class);
        $this->installer()->install($zip, 'backup');
    }

    public function testOhneModuleJsonWirdNichtsInstalliert()
    {
        $zip = $this->archiv(array('Backup/Providers/X.php' => '<?php'));

        $this->expectException(StoreException::class);

        try {
            $this->installer()->install($zip, 'backup');
        } catch (StoreException $e) {
            $this->assertSame('no_module_json', $e->errorCode());
            $this->assertFileDoesNotExist($this->arbeit.'/Modules/Backup');
            throw $e;
        }
    }

    /**
     * Der Grund, warum das Update auf dem echten Host nie durchlief.
     *
     * Ausgepackt wurde nach sys_get_temp_dir(), getauscht wird mit rename() --
     * und rename() kann nicht ueber Dateisystemgrenzen. Auf CloudLinux (unser
     * eigener Host) hat der PHP-Prozess ein eigenes /tmp auf einem anderen
     * Datentraeger als das Konto. Ergebnis: Paket geholt, Signatur geprueft,
     * dann "liess sich nicht ersetzen". Jedes Mal, seit es das Modul gibt.
     *
     * Dieser Test prueft nicht das Symptom, sondern die Zusicherung: das
     * Arbeitsverzeichnis liegt IM Modulverzeichnis. Dann KANN dort keine
     * Grenze sein -- unabhaengig davon, wie der Host sein /tmp einhaengt.
     * Ein Test auf "gleiche Geraetenummer" waere hier wertlos: genau die
     * stimmte auf dem Host ueberein, und rename() scheiterte trotzdem.
     */
    public function testDasArbeitsverzeichnisLiegtImModulverzeichnis()
    {
        $installer = $this->installer();

        $auspack = $installer->arbeitsDirOeffentlich('.lastore-auspack-');

        $this->assertDirectoryExists($auspack);
        $this->assertSame(
            $installer->modulesPathOeffentlich(),
            dirname($auspack),
            'Das Arbeitsverzeichnis muss NEBEN dem Ziel liegen, sonst ist rename() eine Wette auf den Host.'
        );
        $this->assertStringStartsWith(
            '.',
            basename($auspack),
            'Ohne fuehrenden Punkt findet FreeScouts glob das halbfertige Verzeichnis als Modul.'
        );
    }

    public function testNachDerInstallationBleibtKeinArbeitsverzeichnisLiegen()
    {
        $zip = $this->archiv(array(
            'Backup/module.json' => json_encode(array('name' => 'Backup', 'alias' => 'backup', 'version' => '1.9.5')),
        ));

        $this->installer()->install($zip, 'backup');

        $this->assertSame(
            array(),
            (array) glob($this->arbeit.'/Modules/.lastore-auspack-*', GLOB_ONLYDIR),
            'Das Arbeitsverzeichnis wird am Ende entfernt -- auch das unsichtbare.'
        );
    }

    public function testLiegengebliebeneArbeitBleibtEineStundeVerschont()
    {
        // Bricht der Vorgang hart ab, bleibt ein Verzeichnis liegen. Alte
        // werden geraeumt; ein PARALLEL laufender Vorgang darf seines aber
        // nicht unter den Haenden verlieren.
        $alt = $this->arbeit.'/Modules/.lastore-auspack-altaltalt';
        $neu = $this->arbeit.'/Modules/.lastore-auspack-frischfrisch';
        mkdir($alt, 0700, true);
        mkdir($neu, 0700, true);
        touch($alt, time() - 7200);

        $this->installer()->wegraeumenOeffentlich();

        $this->assertDirectoryDoesNotExist($alt, 'Zwei Stunden alt: weg.');
        $this->assertDirectoryExists($neu, 'Gerade angelegt: das koennte ein laufender Vorgang sein.');
    }

}
