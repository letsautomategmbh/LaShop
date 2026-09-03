<?php

namespace Modules\LaStore\Support;

/**
 * Das signierte Lizenz-Token: v1.<payload>.<sig>
 *
 * Beide Teile base64url, ohne Fuellzeichen. Bewusst KEIN JWT-Kopf und kein
 * Algorithmusfeld im Token selbst - das Verfahren steht in PublicKeys und
 * haengt nur an der Kennung "kid". Ein Angreifer kann damit weder auf "none"
 * herabstufen noch ein schwaecheres Verfahren erzwingen; er kann nur ein "kid"
 * nennen, das wir kennen oder nicht kennen.
 *
 * Geprueft wird ausschliesslich lokal. Im Seitenaufbau faellt kein einziger
 * HTTP-Aufruf an - das ist der Grund fuer die ganze Konstruktion.
 */
class LicenseToken
{
    const VERSION = 'v1';
    const ISSUER = 'shop.letsautomate.ch';

    const OK = 'valid';
    const GRACE = 'grace';
    const EXPIRED = 'expired';
    const MALFORMED = 'malformed';
    const UNKNOWN_KEY = 'unknown_key';
    const BAD_SIGNATURE = 'bad_signature';
    const WRONG_ISSUER = 'wrong_issuer';
    const WRONG_AUDIENCE = 'wrong_audience';
    const WRONG_INSTALLATION = 'wrong_installation';
    const CLOCK_ROLLBACK = 'clock_rollback';

    /** @var array */
    protected $claims;

    /** @var string */
    protected $status;

    protected function __construct($status, array $claims = array())
    {
        $this->status = $status;
        $this->claims = $claims;
    }

    /**
     * Token pruefen.
     *
     * @param string   $token
     * @param string   $expectedAudience   Modul-Alias, klein geschrieben
     * @param string   $expectedInstallation
     * @param int|null $now                Unix-Zeit, fuer Tests setzbar
     * @param int|null $maxSeen            hoechste je gesehene Zeit
     * @param array|null $keys              Schluessel statt PublicKeys::all(),
     *                                      nur fuer Tests. Als Parameter und
     *                                      nicht als statischer Umschalter:
     *                                      was Tests umbiegen koennen, kann
     *                                      auch im Betrieb umgebogen werden.
     *
     * @return self
     */
    public static function verify($token, $expectedAudience, $expectedInstallation, $now = null, $maxSeen = null, ?array $keys = null)
    {
        $now = $now === null ? time() : (int) $now;

        // Die Uhr des Kunden ist die einzige Groesse in dieser Pruefung, die
        // der Kunde selbst stellt. Wer sie zurueckdreht, verlaengert seine
        // Lizenz sonst beliebig - besonders offline, wo nie ein Server
        // widerspricht. Deshalb zuerst: eine Zeit hinter dem hoechsten je
        // gesehenen Stand wird nicht geglaubt.
        if ($maxSeen !== null && $now < (int) $maxSeen) {
            return new self(self::CLOCK_ROLLBACK);
        }

        $parts = explode('.', (string) $token);

        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            return new self(self::MALFORMED);
        }

        $claims = json_decode(self::b64decode($parts[1]), true);

        if (!is_array($claims) || empty($claims['kid'])) {
            return new self(self::MALFORMED);
        }

        // Beide Wege durch pick(), damit die Zweckpruefung an einer Stelle
        // steht: ein Paketschluessel darf niemals ein Token freigeben.
        $key = $keys === null
            ? PublicKeys::find($claims['kid'], 'token')
            : PublicKeys::pick($keys, $claims['kid'], 'token');

        if (!$key) {
            return new self(self::UNKNOWN_KEY, $claims);
        }

        $algo = PublicKeys::opensslAlgo($key['algo']);

        if ($algo === null) {
            return new self(self::UNKNOWN_KEY, $claims);
        }

        // Signiert wird der base64url-Text, nicht das entschluesselte JSON -
        // sonst haenge die Pruefung davon ab, dass beide Seiten exakt gleich
        // kodieren, und ein umsortiertes Feld wuerde sie brechen.
        if (openssl_verify($parts[1], self::b64decode($parts[2]), $key['pem'], $algo) !== 1) {
            return new self(self::BAD_SIGNATURE, $claims);
        }

        if (self::claim($claims, 'iss') !== self::ISSUER) {
            return new self(self::WRONG_ISSUER, $claims);
        }

        if (self::claim($claims, 'aud') !== (string) $expectedAudience) {
            return new self(self::WRONG_AUDIENCE, $claims);
        }

        if (self::claim($claims, 'sub') !== (string) $expectedInstallation) {
            return new self(self::WRONG_INSTALLATION, $claims);
        }

        $exp = (int) self::claim($claims, 'exp', 0);
        $grc = (int) self::claim($claims, 'grc', $exp);

        if ($now >= $grc) {
            return new self(self::EXPIRED, $claims);
        }

        if ($now >= $exp) {
            return new self(self::GRACE, $claims);
        }

        return new self(self::OK, $claims);
    }

    /**
     * Die Angaben eines Tokens lesen, OHNE zu pruefen.
     *
     * Nur fuer Anzeigezwecke, an denen nichts haengt - etwa um in einer Liste
     * zu zeigen, worum es ueberhaupt ginge. Nie fuer eine Entscheidung.
     *
     * @param string $token
     *
     * @return array
     */
    public static function peek($token)
    {
        $parts = explode('.', (string) $token);

        if (count($parts) !== 3) {
            return array();
        }

        $claims = json_decode(self::b64decode($parts[1]), true);

        return is_array($claims) ? $claims : array();
    }

    /** @return string */
    public function status()
    {
        return $this->status;
    }

    /**
     * Darf das Modul arbeiten?
     *
     * Gnadenfrist zaehlt als ja. Ein Ausfall unseres Servers darf niemals das
     * Ticketsystem eines Kunden beeintraechtigen.
     *
     * @return bool
     */
    public function isUsable()
    {
        return $this->status === self::OK || $this->status === self::GRACE;
    }

    /** @return bool */
    public function inGrace()
    {
        return $this->status === self::GRACE;
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return self::claim($this->claims, $name, $default);
    }

    /** @return array */
    public function claims()
    {
        return $this->claims;
    }

    /** @return \DateTime|null */
    public function expiresAt()
    {
        $exp = (int) $this->get('exp', 0);

        return $exp ? (new \DateTime())->setTimestamp($exp) : null;
    }

    /** @return \DateTime|null */
    public function graceUntil()
    {
        $grc = (int) $this->get('grc', 0);

        return $grc ? (new \DateTime())->setTimestamp($grc) : null;
    }

    /** @return bool */
    public function isOffline()
    {
        return (bool) $this->get('off', false);
    }

    protected static function claim(array $claims, $name, $default = null)
    {
        return array_key_exists($name, $claims) ? $claims[$name] : $default;
    }

    /**
     * @param string $s
     *
     * @return string
     */
    public static function b64decode($s)
    {
        $s = strtr((string) $s, '-_', '+/');
        $pad = strlen($s) % 4;

        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($s, true);

        return $decoded === false ? '' : $decoded;
    }

    /**
     * @param string $s
     *
     * @return string
     */
    public static function b64encode($s)
    {
        return rtrim(strtr(base64_encode((string) $s), '+/', '-_'), '=');
    }
}
