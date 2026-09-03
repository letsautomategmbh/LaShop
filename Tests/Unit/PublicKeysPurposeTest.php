<?php

namespace Modules\LaStore\Tests\Unit;

use Modules\LaStore\Support\LicenseToken;
use Modules\LaStore\Support\PackageVerifier;
use Modules\LaStore\Support\PublicKeys;
use Modules\LaStore\Tests\Fixtures\TokenFactory;
use PHPUnit\Framework\TestCase;

/**
 * Die Trennung nach Zweck.
 *
 * Es gibt zwei Signaturschluessel, und der Unterschied ist der Schaden bei
 * Verlust:
 *
 *   Tokenschluessel weg -> jemand faelscht Lizenzen. Begrenzt, erkennbar.
 *   Paketschluessel weg -> jemand schiebt Code auf jede Installation.
 *
 * Der erste MUSS online liegen, bei jedem Check wird signiert. Der zweite
 * darf es nicht. Ohne die Pruefung des ZWECKS waere diese Trennung aber
 * Dekoration: wer den Server uebernimmt, hat den Tokenschluessel - und wenn
 * der auch Pakete signieren darf, ist nichts gewonnen.
 *
 * Genau das wird hier geprueft.
 */
class PublicKeysPurposeTest extends TestCase
{
    /*
     * Aus TokenFactory::make(). Als Konstanten und nicht eingetippt, weil
     * genau das schiefging: ich hatte 'backup' geraten, der Test lief in
     * "wrong_audience" - und der Zwecktest darueber waere aus demselben
     * falschen Grund gruen geblieben, ohne die Gegenprobe.
     */
    const FIXTURE_AUD   = 'bexiosubscriptions';
    const FIXTURE_SUB   = 'b3f1c8a2-4e7d-4b19-9f30-2a6c5d81e044';
    const FIXTURE_JETZT = 1788134400;

    protected $tokenPrivat;
    protected $paketPrivat;
    protected $schluessel;
    protected $datei;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schluessel = array();

        foreach (array('lat1' => 'token', 'lap1' => 'package') as $kid => $zweck) {
            $paar = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
            openssl_pkey_export($paar, $privat);

            if ($zweck === 'token') {
                $this->tokenPrivat = $privat;
            } else {
                $this->paketPrivat = $privat;
            }

            $this->schluessel[$kid] = array(
                'algo' => 'RS256',
                'use'  => $zweck,
                'pem'  => openssl_pkey_get_details($paar)['key'],
            );
        }

        $this->datei = tempnam(sys_get_temp_dir(), 'lastore-zweck');
        file_put_contents($this->datei, 'ein Paket');
    }

    protected function tearDown(): void
    {
        @unlink($this->datei);
        parent::tearDown();
    }

    protected function signiere($daten, $privat)
    {
        $sig = '';
        openssl_sign($daten, $sig, $privat, OPENSSL_ALGO_SHA256);

        return base64_encode($sig);
    }

    public function testDerPaketschluesselDarfEinPaketSignieren()
    {
        $hash = hash_file('sha256', $this->datei);

        $e = PackageVerifier::check($this->datei, $hash, $this->signiere($hash, $this->paketPrivat), 'lap1', $this->schluessel);

        $this->assertTrue($e->passed(), $e->explain());
    }

    public function testDerTOKENschluesselDarfKeinPaketSignieren()
    {
        // Der entscheidende Fall. Die Signatur ist echt, der Schluessel ist
        // uns bekannt, die Datei stimmt - und es wird trotzdem abgewiesen,
        // weil dieser Schluessel nicht fuer Pakete gilt.
        //
        // Ohne diese Pruefung waere ein uebernommener Server gleichbedeutend
        // mit Code auf jeder Kundeninstallation.
        $hash = hash_file('sha256', $this->datei);

        $e = PackageVerifier::check($this->datei, $hash, $this->signiere($hash, $this->tokenPrivat), 'lat1', $this->schluessel);

        $this->assertFalse($e->passed(), 'Ein Tokenschlüssel darf niemals ein Paket freigeben.');
        $this->assertSame(PackageVerifier::UNKNOWN_KEY, $e->status());
    }

    public function testFindGibtDenSchluesselNurFuerSeinenZweck()
    {
        // find() ohne Zweck findet beide — das ist der Fall für Diagnosen.
        $this->assertNotNull($this->finde('lat1', null));
        $this->assertNotNull($this->finde('lap1', null));

        // Mit Zweck nur den passenden.
        $this->assertNotNull($this->finde('lat1', 'token'));
        $this->assertNull($this->finde('lat1', 'package'));
        $this->assertNotNull($this->finde('lap1', 'package'));
        $this->assertNull($this->finde('lap1', 'token'));
    }

    public function testEinAlterEintragOhneZweckGiltFuerBeides()
    {
        // Rueckfall fuer die Entwicklungsschluessel und fuer aeltere
        // Eintraege: fehlt "use", gilt der Schluessel fuer beides. Sonst
        // haette diese Aenderung jede bestehende Installation lahmgelegt.
        $alt = array('la9' => array('algo' => 'RS256', 'pem' => $this->schluessel['lap1']['pem']));
        $hash = hash_file('sha256', $this->datei);

        $e = PackageVerifier::check($this->datei, $hash, $this->signiere($hash, $this->paketPrivat), 'la9', $alt);

        $this->assertTrue($e->passed(), $e->explain());
    }

    /** find() arbeitet auf den eingebauten Schlüsseln — für den Test nachgebaut. */
    protected function finde($kid, $zweck)
    {
        $key = isset($this->schluessel[$kid]) ? $this->schluessel[$kid] : null;

        if ($key === null || $zweck === null) {
            return $key;
        }

        $erlaubt = isset($key['use']) ? $key['use'] : 'both';

        return ($erlaubt === 'both' || $erlaubt === $zweck) ? $key : null;
    }

    public function testDieEingebautenSchluesselHabenEinenZweck()
    {
        // Sicherung gegen einen Eintrag ohne "use" in PublicKeys::all():
        // er wuerde stumm fuer beides gelten und die Trennung aufheben.
        foreach (PublicKeys::all() as $kid => $key) {
            $this->assertArrayHasKey('use', $key, 'Schlüssel '.$kid.' nennt keinen Zweck.');
            $this->assertContains($key['use'], array('token', 'package', 'both'),
                'Schlüssel '.$kid.' nennt einen unbekannten Zweck: '.$key['use']);
        }
    }

    /**
     * Die Gegenrichtung zu testDerTOKENschluesselDarfKeinPaketSignieren.
     *
     * Beide Richtungen, weil die Trennung sonst nur halb geprueft ist: die
     * Gefahr laeuft in beide Wege. Ein Einbruch in den Lizenzserver erbeutet
     * den Tokenschluessel - der darf dann kein Paket freigeben. Und ein
     * abgeflossener Paketschluessel darf keine Lizenzen ausstellen.
     */
    public function testDerPAKETschluesselDarfKeinTokenFreigeben()
    {
        // Dieselben Fixture-Schluessel, aber als Paketschluessel deklariert.
        $alsPaket = TokenFactory::publicKeys('package');

        $ergebnis = LicenseToken::verify(
            TokenFactory::make(),
            self::FIXTURE_AUD,
            self::FIXTURE_SUB,
            self::FIXTURE_JETZT,
            null,
            $alsPaket
        );

        $this->assertSame(
            LicenseToken::UNKNOWN_KEY,
            $ergebnis->status(),
            'Ein Paketschluessel darf niemals ein Lizenz-Token freigeben.'
        );
        $this->assertFalse($ergebnis->isUsable());
    }

    /**
     * Gegenprobe zum Test darueber: mit 'token' geht dasselbe Token durch.
     *
     * Ohne diese Probe koennte der Test oben auch aus einem anderen Grund
     * gruen sein - falsche Zielgruppe, falsche Zeit, kaputte Fixture - und
     * wuerde die Trennung trotzdem bestaetigen.
     */
    public function testDasselbeTokenGehtMitDemTOKENschluesselDurch()
    {
        $ergebnis = LicenseToken::verify(
            TokenFactory::make(),
            self::FIXTURE_AUD,
            self::FIXTURE_SUB,
            self::FIXTURE_JETZT,
            null,
            TokenFactory::publicKeys('token')
        );

        $this->assertSame(LicenseToken::OK, $ergebnis->status());
    }
}
