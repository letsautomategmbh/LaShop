<?php

namespace Modules\LaStore\Tests\Unit;

use Modules\LaStore\Services\Transport\HttpTransport;
use PHPUnit\Framework\TestCase;

/**
 * Der Ausweichweg darf die echte Fehlermeldung nicht verschlucken.
 *
 * Der Befund vom 04.09.2026: als Vorgabe stand
 * `https://cdn.letsautomate.ch/api/store/v1` in JEDER ausgelieferten
 * Installation. Die Unteradresse existiert nicht und wird es nicht geben.
 *
 * Teuer war daran nicht die Zeit -- NXDOMAIN kommt in etwa 10 Millisekunden
 * zurueck, gemessen. Teuer war, dass der zweite Versuch die Meldung des
 * ersten ERSETZT: beim Kunden landete "Could not resolve host:
 * cdn.letsautomate.ch", waehrend die wahre Ursache -- Shop nicht erreichbar,
 * TLS, Zeitueberschreitung -- verworfen wurde. Eine Fehlermeldung, die auf
 * einen Host zeigt, den es nie gab, kostet im Support mehr als der Ausfall.
 *
 * Darum steht die Vorgabe jetzt auf leer, und leer heisst AUS.
 *
 * Geprueft wird an der Vorgabe und an der reinen Funktion, NICHT an
 * ausweichadresse(): die ruft config() und damit den Dienstbehaelter, den es
 * in einer nackten PHPUnit-Umgebung nicht gibt. Genau daran ist der erste
 * Entwurf dieses Tests gescheitert.
 */
class AusweichadresseTest extends TestCase
{
    public function testDieVorgabeNenntKeinenHost()
    {
        // Der eigentliche Regressionsschutz. Steht hier je wieder eine
        // Adresse als VORGABE, ist der Fehler zurueck -- und er fiele wieder
        // erst beim Kunden auf, nicht bei uns.
        $this->assertSame('', HttpTransport::FALLBACK);
    }

    public function testDieHauptadresseBleibtGesetzt()
    {
        // Gegenprobe zur Zeile darueber: leer soll NUR der Ausweichweg sein.
        // Ein Suchen-und-Ersetzen, das beide Vorgaben leert, waere ein Modul,
        // das mit keinem Server mehr spricht.
        $this->assertStringStartsWith('https://', HttpTransport::BASE);
        $this->assertStringContainsString('shop.letsautomate.ch', HttpTransport::BASE);
    }

    /** @dataProvider keineAdressen */
    public function testWasNichtAlsAdresseZaehlt($wert)
    {
        $this->assertSame('', HttpTransport::normalisiereAdresse($wert));
    }

    public function keineAdressen()
    {
        return [
            'leer'          => [''],
            'Leerzeichen'   => ['   '],
            'Zeilenumbruch' => ["\n"],
            'null'          => [null],
            'Zahl'          => [0],
            'Feld'          => [[]],
        ];
    }

    public function testEineEchteAdresseKommtDurch()
    {
        $this->assertSame(
            'https://spiegel.example/api/store/v1',
            HttpTransport::normalisiereAdresse('  https://spiegel.example/api/store/v1  ')
        );
    }
}
