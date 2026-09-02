<?php

namespace Modules\LaStore\Tests\Unit;

use Modules\LaStore\Support\LicenseToken;
use Modules\LaStore\Tests\Fixtures\TokenFactory;
use PHPUnit\Framework\TestCase;

class LicenseTokenTest extends TestCase
{
    const SUB = 'b3f1c8a2-4e7d-4b19-9f30-2a6c5d81e044';
    const AUD = 'bexiosubscriptions';

    /** Mitten in der Laufzeit des Standard-Tokens. */
    protected function inside()
    {
        return 1788134400 + 100 * 86400;
    }

    protected function verify($token, $now = null, $maxSeen = null)
    {
        return LicenseToken::verify($token, self::AUD, self::SUB, $now === null ? $this->inside() : $now, $maxSeen);
    }

    public function testValidTokenIsAccepted()
    {
        $result = $this->verify(TokenFactory::make());

        $this->assertSame(LicenseToken::OK, $result->status());
        $this->assertTrue($result->isUsable());
        $this->assertFalse($result->inGrace());
        $this->assertSame(25, $result->get('seats'));
    }

    public function testSecondKeyIsAlsoAccepted()
    {
        // Der ganze Sinn der zwei Schluessel: ein Wechsel muss moeglich sein,
        // ohne dass alle Installationen gleichzeitig aktualisiert werden.
        $result = $this->verify(TokenFactory::make(array(), 'la2'));

        $this->assertSame(LicenseToken::OK, $result->status());
    }

    public function testTamperedPayloadIsRejected()
    {
        $token = TokenFactory::make();
        $parts = explode('.', $token);

        // Aus 25 Sitzen 2500 machen und die Signatur unveraendert lassen.
        $claims = json_decode(LicenseToken::b64decode($parts[1]), true);
        $claims['seats'] = 2500;
        $forged = $parts[0].'.'.LicenseToken::b64encode(json_encode($claims)).'.'.$parts[2];

        $this->assertSame(LicenseToken::BAD_SIGNATURE, $this->verify($forged)->status());
    }

    public function testSignatureFromAnotherKeyIsRejected()
    {
        // Mit la2 signiert, aber la1 als kid ausgegeben.
        $real = TokenFactory::make(array(), 'la2');
        $parts = explode('.', $real);
        $claims = json_decode(LicenseToken::b64decode($parts[1]), true);
        $claims['kid'] = 'la1';
        $forged = $parts[0].'.'.LicenseToken::b64encode(json_encode($claims)).'.'.$parts[2];

        $this->assertSame(LicenseToken::BAD_SIGNATURE, $this->verify($forged)->status());
    }

    public function testUnknownKeyIdIsRejected()
    {
        $token = TokenFactory::make(array('kid' => 'fremd'));

        $this->assertSame(LicenseToken::UNKNOWN_KEY, $this->verify($token)->status());
    }

    /**
     * Ein Token fuer ein anderes Modul darf dieses Modul nicht freischalten -
     * sonst reicht eine einzige billige Lizenz fuer den ganzen Katalog.
     */
    public function testTokenForAnotherModuleIsRejected()
    {
        $token = TokenFactory::make(array('aud' => 'invoicing'));

        $this->assertSame(LicenseToken::WRONG_AUDIENCE, $this->verify($token)->status());
    }

    /**
     * Und ein Token einer anderen Installation genauso wenig - sonst laesst
     * sich eine Lizenz per Kopie der Datenbank vervielfaeltigen.
     */
    public function testTokenForAnotherInstallationIsRejected()
    {
        $token = TokenFactory::make(array('sub' => '00000000-0000-0000-0000-000000000000'));

        $this->assertSame(LicenseToken::WRONG_INSTALLATION, $this->verify($token)->status());
    }

    public function testWrongIssuerIsRejected()
    {
        $token = TokenFactory::make(array('iss' => 'shop.example.com'));

        $this->assertSame(LicenseToken::WRONG_ISSUER, $this->verify($token)->status());
    }

    public function testMalformedTokensAreRejected()
    {
        foreach (array('', 'kaputt', 'v1.zwei', 'v2.a.b', 'v1...', 'v1.'.LicenseToken::b64encode('kein json').'.x') as $bad) {
            $this->assertSame(LicenseToken::MALFORMED, $this->verify($bad)->status(), 'Angenommen: '.var_export($bad, true));
        }
    }

    public function testGracePeriodStillWorksButIsFlagged()
    {
        $token = TokenFactory::make();

        // Einen Tag nach Ablauf, aber innerhalb der 30 Tage Gnadenfrist.
        $result = $this->verify($token, 1788134400 + 366 * 86400);

        $this->assertSame(LicenseToken::GRACE, $result->status());
        $this->assertTrue($result->isUsable(), 'In der Gnadenfrist muss das Modul weiterlaufen');
        $this->assertTrue($result->inGrace());
    }

    public function testAfterGraceItIsExpired()
    {
        $result = $this->verify(TokenFactory::make(), 1788134400 + 400 * 86400);

        $this->assertSame(LicenseToken::EXPIRED, $result->status());
        $this->assertFalse($result->isUsable());
    }

    /**
     * Der einzige billige Angriff auf eine Offline-Lizenz: die Systemuhr
     * zurueckdrehen. Ohne diese Pruefung laeuft ein abgelaufenes Token ewig.
     */
    public function testClockRollbackIsRejected()
    {
        $token = TokenFactory::make();
        $maxSeen = $this->inside();

        // Ein Jahr zurueckgestellt, um dem Ablauf zu entgehen.
        $result = $this->verify($token, $maxSeen - 365 * 86400, $maxSeen);

        $this->assertSame(LicenseToken::CLOCK_ROLLBACK, $result->status());
        $this->assertFalse($result->isUsable());
    }

    public function testClockAtOrAheadOfHighWaterMarkIsFine()
    {
        $token = TokenFactory::make();
        $now = $this->inside();

        $this->assertSame(LicenseToken::OK, $this->verify($token, $now, $now)->status());
        $this->assertSame(LicenseToken::OK, $this->verify($token, $now + 86400, $now)->status());
    }

    public function testOfflineTokenIsRecognised()
    {
        $now = 1788134400;
        $token = TokenFactory::make(array('off' => true, 'exp' => $now + 365 * 86400, 'grc' => $now + 365 * 86400));

        $result = $this->verify($token);

        $this->assertSame(LicenseToken::OK, $result->status());
        $this->assertTrue($result->isOffline());
    }

    /**
     * Ohne grc waere ein Token nach exp sofort tot. Fehlt das Feld, muss grc
     * auf exp fallen - nicht auf unendlich.
     */
    public function testMissingGraceFallsBackToExpiry()
    {
        $now = 1788134400;
        $token = TokenFactory::sign(array(
            'iss' => LicenseToken::ISSUER,
            'sub' => self::SUB,
            'aud' => self::AUD,
            'exp' => $now + 10,
        ));

        $this->assertSame(LicenseToken::OK, $this->verify($token, $now)->status());
        $this->assertSame(LicenseToken::EXPIRED, $this->verify($token, $now + 11)->status());
    }

    public function testPeekNeverDecides()
    {
        $forged = 'v1.'.LicenseToken::b64encode(json_encode(array('seats' => 9999))).'.'.LicenseToken::b64encode('muell');

        $this->assertSame(9999, LicenseToken::peek($forged)['seats'], 'peek liest, ohne zu pruefen');
        $this->assertFalse($this->verify($forged)->isUsable(), 'verify darf dasselbe Token nicht durchlassen');
    }
}
