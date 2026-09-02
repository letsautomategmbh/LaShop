<?php

namespace Modules\LaStore\Tests\Unit;

use Modules\LaStore\Entities\License;
use Modules\LaStore\Support\InstalledModules;
use Modules\LaStore\Support\LicenseToken;
use PHPUnit\Framework\TestCase;

/**
 * Wann eine Lizenz als gueltig gilt.
 *
 * Der Anlass ist ein Fehler vom 02.09.2026: mit abgelegten Antworten
 * ("static") standen alle Module auf gruenem "Lizenziert", ohne dass je ein
 * Schluessel eingegeben wurde. Die Kette war:
 *
 *   1. die abgelegten Tokens lauten auf die Kennung "la1", die dieses Modul
 *      nicht kennt -> Pruefung schlaegt fehl,
 *   2. LicenseService legte die Zeile trotzdem an, mit dem Vorgabestatus
 *      "unknown",
 *   3. und "unknown" galt hier als gueltig, weil nur das klare "abgelaufen"
 *      zaehlte.
 *
 * Ueber Reflexion geprueft und nicht ueber die Oberflaeche: die Regel ist
 * eine Zeile Logik, und sie soll ohne Datenbank, Katalog und Modulliste
 * festgenagelt sein.
 */
class LizenzGiltTest extends TestCase
{
    private function gilt(array $werte): bool
    {
        // setRawAttributes und keine Zuweisung: der Datums-Setzer von Eloquent
        // braucht eine Verbindung, und dieser Test soll ohne Datenbank laufen.
        // Beim LESEN greift die Umwandlung nach Carbon trotzdem.
        $license = new License();
        $license->setRawAttributes($werte, true);

        $m = new \ReflectionMethod(InstalledModules::class, 'lizenzGilt');
        $m->setAccessible(true);

        return (bool) $m->invoke(null, $license);
    }

    public function test_gueltiges_token_gilt(): void
    {
        $this->assertTrue($this->gilt(['status' => LicenseToken::OK]));
    }

    public function test_gnadenfrist_gilt(): void
    {
        $this->assertTrue($this->gilt(['status' => LicenseToken::GRACE]));
    }

    public function test_nicht_geprueft_gilt_nicht(): void
    {
        // Der Vorgabewert der Spalte. Die Beschriftungsliste nennt ihn
        // "Nicht geprueft" - und nicht geprueft ist nicht lizenziert.
        $this->assertFalse($this->gilt(['status' => 'unknown']));
    }

    public function test_unbekannter_schluessel_gilt_nicht(): void
    {
        $this->assertFalse($this->gilt(['status' => LicenseToken::UNKNOWN_KEY]));
    }

    public function test_ungueltige_signatur_gilt_nicht(): void
    {
        $this->assertFalse($this->gilt(['status' => LicenseToken::BAD_SIGNATURE]));
    }

    public function test_abgelaufen_gilt_nicht(): void
    {
        $this->assertFalse($this->gilt(['status' => LicenseToken::EXPIRED]));
    }

    /*
     * Die verstrichene Gnadenfrist ist hier NICHT geprueft, mit Absicht:
     * `grace_until` zu lesen loest die Umwandlung nach Carbon aus, und die
     * fragt ueber getDateFormat() die Verbindung - ohne Datenbank endet das
     * in "Call to a member function connection() on null". Eine Verbindung
     * zu faelschen, nur damit ein Datum gelesen werden kann, prueft die
     * Faelschung und nicht die Regel.
     *
     * Diese Regel ist ausserdem unveraendert; geaendert wurde nur, welche
     * Status als gueltig zaehlen.
     */
}
