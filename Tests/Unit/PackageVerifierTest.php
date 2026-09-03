<?php

namespace Modules\LaStore\Tests\Unit;

use Modules\LaStore\Support\PackageVerifier;
use PHPUnit\Framework\TestCase;

/**
 * Die Pruefung eines Pakets.
 *
 * Gepruefft wird nicht, dass ein gutes Paket durchkommt - das ist der leichte
 * Teil. Gepruefft wird, dass jede Abweichung auffliegt, und zwar mit einem
 * Status, aus dem hervorgeht, WAS nicht stimmte.
 *
 * Ein eigenes Schluesselpaar je Lauf. Die Fixtures des Moduls werden bewusst
 * nicht benutzt: dieser Test muss auch dann laufen, wenn sie vor der ersten
 * Veroeffentlichung ersetzt werden.
 */
class PackageVerifierTest extends TestCase
{
    /** @var string */
    protected $privat;

    /** @var array */
    protected $schluessel;

    /** @var string */
    protected $datei;

    protected function setUp(): void
    {
        parent::setUp();

        $paar = openssl_pkey_new(array(
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ));
        openssl_pkey_export($paar, $this->privat);
        $details = openssl_pkey_get_details($paar);

        $this->schluessel = array('test1' => array('algo' => 'RS256', 'pem' => $details['key']));

        $this->datei = tempnam(sys_get_temp_dir(), 'lastore-test');
        file_put_contents($this->datei, 'Inhalt eines Pakets');
    }

    protected function tearDown(): void
    {
        @unlink($this->datei);
        parent::tearDown();
    }

    protected function signiere($hash, $privat = null)
    {
        $sig = '';
        openssl_sign($hash, $sig, $privat ?: $this->privat, OPENSSL_ALGO_SHA256);

        return base64_encode($sig);
    }

    public function testEinUnveraendertesUndSigniertesPaketKommtDurch()
    {
        $hash = hash_file('sha256', $this->datei);

        $e = PackageVerifier::check($this->datei, $hash, $this->signiere($hash), 'test1', $this->schluessel);

        $this->assertTrue($e->passed(), $e->explain());
        $this->assertSame(PackageVerifier::OK, $e->status());
        $this->assertSame('test1', $e->kid());
    }

    public function testEinVeraendertesPaketFaelltAuf()
    {
        $hash = hash_file('sha256', $this->datei);
        $sig = $this->signiere($hash);

        // Ein einziges Byte mehr. So sieht ein abgebrochener Download aus,
        // und so sieht ein untergeschobenes Paket aus.
        file_put_contents($this->datei, 'Inhalt eines Pakets ');

        $e = PackageVerifier::check($this->datei, $hash, $sig, 'test1', $this->schluessel);

        $this->assertFalse($e->passed());
        $this->assertSame(PackageVerifier::HASH_MISMATCH, $e->status());
    }

    public function testEinePassendeDateiMitFremderSignaturFaelltAuf()
    {
        // Der Angriff, gegen den die ganze Konstruktion gebaut ist: die Datei
        // stimmt, der Hash stimmt, nur der Unterzeichner ist ein anderer.
        $fremd = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
        openssl_pkey_export($fremd, $fremdPrivat);

        $hash = hash_file('sha256', $this->datei);
        $sig = $this->signiere($hash, $fremdPrivat);

        $e = PackageVerifier::check($this->datei, $hash, $sig, 'test1', $this->schluessel);

        $this->assertFalse($e->passed());
        $this->assertSame(PackageVerifier::BAD_SIGNATURE, $e->status());
    }

    public function testEineSignaturUeberEinenAnderenHashFaelltAuf()
    {
        // Gueltig signiert, aber fuer ein anderes Paket. Wer eine alte,
        // echte Signatur an eine neue Datei haengt, kommt hier nicht durch.
        $hash = hash_file('sha256', $this->datei);
        $sig = $this->signiere(hash('sha256', 'ein anderes Paket'));

        $e = PackageVerifier::check($this->datei, $hash, $sig, 'test1', $this->schluessel);

        $this->assertSame(PackageVerifier::BAD_SIGNATURE, $e->status());
    }

    public function testEineUnbekannteSchluesselkennungFuehrtZumModulUpdate()
    {
        $hash = hash_file('sha256', $this->datei);

        $e = PackageVerifier::check($this->datei, $hash, $this->signiere($hash), 'la99', $this->schluessel);

        $this->assertSame(PackageVerifier::UNKNOWN_KEY, $e->status());
        // Nach einem Schluesselwechsel ist genau das der Normalfall, darum
        // muss der Hinweis zum Update des MODULS fuehren -- und das Modul beim
        // Namen nennen, damit der Verwalter weiss, welches gemeint ist.
        $this->assertStringContainsString('LaShop', $e->explain());
    }

    public function testOhneSignaturWirdNichtInstalliert()
    {
        $hash = hash_file('sha256', $this->datei);

        $e = PackageVerifier::check($this->datei, $hash, '', 'test1', $this->schluessel);

        $this->assertSame(PackageVerifier::MISSING_SIGNATURE, $e->status());
    }

    public function testOhneHashWirdNichtInstalliert()
    {
        // Nicht pruefbar heisst nicht installieren, nicht "wohl in Ordnung".
        $e = PackageVerifier::check($this->datei, '', 'egal', 'test1', $this->schluessel);

        $this->assertSame(PackageVerifier::HASH_MISMATCH, $e->status());
    }

    public function testEineFehlendeDateiIstKeinErfolg()
    {
        $e = PackageVerifier::check('/gibt/es/nicht.zip', str_repeat('a', 64), 'x', 'test1', $this->schluessel);

        $this->assertSame(PackageVerifier::UNREADABLE, $e->status());
    }

    public function testGrossKleinschreibungDesHashesIstUnerheblich()
    {
        $hash = hash_file('sha256', $this->datei);

        $e = PackageVerifier::check($this->datei, strtoupper($hash), $this->signiere($hash), 'test1', $this->schluessel);

        $this->assertTrue($e->passed(), $e->explain());
    }
}
