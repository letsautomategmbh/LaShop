<?php

namespace Modules\LaStore\Tests\Unit;

use Modules\LaStore\Support\ModulAnmeldung;
use PHPUnit\Framework\TestCase;

/**
 * Das Anmelden beim Kern darf ein Update nie umbringen.
 *
 * Anlass ist ein Fund vom 04.09.2026: ein Modul-Update tauscht Dateien, und
 * NIEMAND zog die Datenbank nach -- weder unser Weg noch FreeScouts eigener.
 * `module:migrate` steht im Kern nur in `freescout:module-install`, und das
 * laeuft beim Aktivieren. Auf der Produktion lag darum
 * `add_license_type_to_lastore_licenses` nach zwei Selbstaktualisierungen
 * ungelaufen auf der Platte, waehrend der Code, der die Spalte schreibt,
 * schon lief.
 *
 * Seitdem meldet der Aktualisierer das Modul selbst an. Damit steht dieser
 * Schritt aber NACH dem Tausch -- an der einzigen Stelle, an der ein
 * Durchschlagen nicht mehr rueckholbar ist. Deshalb pruefen die Tests hier
 * nur eines, und das gruendlich: dass Scheitern ein RUECKGABEWERT ist und
 * kein Abbruch.
 *
 * Ohne Laravel und ohne Attrappen: dieser Test laeuft in einer nackten
 * PHPUnit-Umgebung, in der es weder \Module noch \Artisan gibt. Der Aufruf
 * loest damit echt aus, was er im Ernstfall aufloesen muesste -- einen
 * \Error, keine \Exception. Genau der wuerde an einem catch(\Exception)
 * vorbeigehen. Eine Attrappe haette das nie gezeigt, weil sie geworfen
 * haette, was man ihr sagt.
 */
class ModulAnmeldungTest extends TestCase
{
    public function testScheiternIstEinRueckgabewertUndKeinAbbruch()
    {
        $ergebnis = ModulAnmeldung::anmelden('lastore');

        $this->assertIsArray($ergebnis);
        $this->assertFalse($ergebnis['ok'], 'Ohne Kern kann nichts angemeldet sein.');
        $this->assertNotNull($ergebnis['fehler'], 'Der Grund muss beim Aufrufer ankommen.');
    }

    public function testDieAntwortHatImmerAlleDreiFelder()
    {
        // Der Aufrufer greift auf alle drei zu, ohne zu pruefen. Fehlt eines
        // im Fehlerfall, ist die Meldung an den Menschen selbst der Fehler.
        $ergebnis = ModulAnmeldung::anmelden('lastore');

        $this->assertArrayHasKey('ok', $ergebnis);
        $this->assertArrayHasKey('ausgabe', $ergebnis);
        $this->assertArrayHasKey('fehler', $ergebnis);
        $this->assertIsString($ergebnis['ausgabe']);
    }

    public function testDerBefehlVonHandNenntDenAlias()
    {
        // Er landet in einer Fehlermeldung, die jemand abtippt. Steht der
        // Alias nicht drin, ist die Meldung wertlos.
        $befehl = ModulAnmeldung::befehl('vault');

        $this->assertStringContainsString('freescout:module-install', $befehl);
        $this->assertStringContainsString('vault', $befehl);
        $this->assertStringStartsWith('php artisan ', $befehl);
    }

    public function testAliasWirdNichtInEinenModulnamenUmgedeutet()
    {
        /*
         * Drei Zeichenketten bezeichnen dasselbe Modul: Name "LaShop",
         * Ordner "LaStore", Alias "lastore". \Module::find() sucht den
         * NAMEN -- daran ist nach der Umbenennung schon einmal etwas still
         * durchgefallen. Diese Klasse gibt den Alias weiter, unveraendert,
         * und loest den Namen nicht selbst auf; das tut der Kern.
         */
        $this->assertSame(
            'php artisan freescout:module-install lastore',
            ModulAnmeldung::befehl('lastore')
        );
    }
}
