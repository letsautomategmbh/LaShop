<?php

namespace Modules\LaStore\Tests\Fixtures;

use Modules\LaStore\Support\LicenseToken;

/**
 * Erzeugt echte, gueltig signierte Tokens fuer Tests und Fixtures.
 *
 * Spiegelt die Server-Seite. Bewusst hier und nicht im Modul: der Client darf
 * nichts signieren koennen, sonst waere jede Installation ihre eigene
 * Lizenzstelle.
 */
class TokenFactory
{
    /** @return string */
    public static function keyPath($kid = 'la1')
    {
        return __DIR__.'/keys/'.$kid.'.key';
    }

    /**
     * @param array  $claims
     * @param string $kid
     *
     * @return string
     */
    public static function sign(array $claims, $kid = 'la1')
    {
        $claims = array_merge(array('kid' => $kid), $claims);
        $payload = LicenseToken::b64encode(json_encode($claims));
        $key = openssl_pkey_get_private(file_get_contents(self::keyPath($kid)));

        openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256);

        return LicenseToken::VERSION.'.'.$payload.'.'.LicenseToken::b64encode($signature);
    }

    /**
     * Ein brauchbares Standard-Token, das einzelne Werte ueberschreiben kann.
     *
     * @param array  $overrides
     * @param string $kid
     *
     * @return string
     */
    public static function make(array $overrides = array(), $kid = 'la1')
    {
        $now = isset($overrides['iat']) ? $overrides['iat'] : 1788134400;

        $claims = array_merge(array(
            'iss'   => LicenseToken::ISSUER,
            'sub'   => 'b3f1c8a2-4e7d-4b19-9f30-2a6c5d81e044',
            'aud'   => 'bexiosubscriptions',
            'lic'   => '7f3a9c21d4e08b56',
            'typ'   => 'yearly_per_user',
            'seats' => 25,
            'iat'   => $now,
            'exp'   => $now + 365 * 86400,
            'grc'   => $now + 395 * 86400,
            'upd'   => $now + 365 * 86400,
        ), $overrides);

        return self::sign($claims, $kid);
    }
}
