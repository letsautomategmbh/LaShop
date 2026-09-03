<?php

namespace Modules\LaStore\Support;

/**
 * Die oeffentlichen Schluessel, gegen die Lizenz-Tokens und Paketsignaturen
 * geprueft werden.
 *
 * Zwei Punkte, die hier festgeschrieben sind und leicht falsch gemacht werden:
 *
 * 1. Es sind immer ZWEI Schluessel. Sonst gibt es spaeter keinen Weg, den
 *    Signaturschluessel zu wechseln, ohne jede Installation gleichzeitig zu
 *    aktualisieren - und genau das laesst sich bei selbst gehosteter Software
 *    nicht garantieren. Der Ablauf: der neue Schluessel wird als zweiter
 *    akzeptierter ausgeliefert, waehrend weiter mit dem alten signiert wird;
 *    erst wenn die Heartbeats zeigen, dass praktisch alle Installationen ihn
 *    kennen, wird umgestellt.
 *
 * 2. RSA-2048 mit SHA-256, nicht Ed25519. Die Spezifikation nannte
 *    urspruenglich Ed25519 - aber die ext-sodium fehlt in der offiziellen
 *    FreeScout-Umgebung (geprueft: PHP 8.5, CLI und FPM, kein sodium, und
 *    auch kein paragonie/sodium_compat im vendor-Baum). openssl ist dagegen
 *    immer vorhanden, weil FreeScout es fuer TLS und IMAP ohnehin braucht.
 *    Eine Pruefung kostet gemessene 0,06 ms.
 *
 * Das Feld "algo" steht bewusst je Schluessel und nicht global: derselbe
 * Rotationsmechanismus traegt damit spaeter auch einen Wechsel des Verfahrens.
 */
class PublicKeys
{
    /**
     * Die PRODUKTIVSCHLUESSEL. Je Zweck zwei, und das ist keine Zierde:
     *
     *   lat1/lat2  token    - unterschreiben Lizenz-Tokens, bei jeder Pruefung
     *   lap1/lap2  package  - unterschreiben Auslieferungen, selten und offline
     *
     * Warum je ZWEI: eine Installation beim Kunden aktualisiert sich nicht auf
     * Zuruf. Muss ein Schluessel ersetzt werden, gibt es ohne einen bereits
     * akzeptierten Zweiten keinen Weg, der nicht vorher jede Installation
     * erreicht - und genau das laesst sich bei selbst gehosteter Software
     * nicht erzwingen. Die Reserve wird also mit ausgeliefert und liegt
     * ungenutzt, bis sie gebraucht wird.
     *
     * Deshalb liegt vom Tokenschluessel auch KEINE Sicherungskopie neben dem
     * Server: die Reserve ist der bessere Notweg. Eine Kopie hilft nur gegen
     * Verlust, die Reserve auch gegen Verrat - und jede Kopie ist ein
     * zweiter Ort, von dem der Schluessel abfliessen kann.
     *
     * Warum die Trennung nach Zweck: der Tokenschluessel muss am Netz liegen,
     * er zeichnet bei jedem Lizenzabruf. Der Paketschluessel nicht - er
     * zeichnet ein paar Mal im Jahr. Waere es einer, haette ein Einbruch in
     * den Lizenzserver auch die Macht, jeder Installation beliebigen Code
     * unterzuschieben. Durchgesetzt wird das in pick(), nicht hier.
     *
     * Die ENTWICKLUNGSSCHLUESSEL (la1/la2, 'both') stehen bewusst nicht mehr
     * in dieser Liste. Ihre privaten Haelften liegen auf Entwicklungsrechnern;
     * in der ausgelieferten Liste waeren sie ein Generalschluessel. Fuer die
     * Entwicklung kommen sie ueber extra() aus einer Datei, die es nur dort
     * gibt.
     *
     * Kein Heredoc fuer die PEMs: die Blocks standen hier mit eingerueckt
     * geschlossenem Marker, und das ist "flexible heredoc" ab PHP 7.3.
     * FreeScout traegt ab 7.1 - php:7.1-cli hat die Datei nicht einmal
     * geparst ("unexpected end of file"), das Modul waere dort gar nicht
     * geladen. implode() auf ein Array von Zeilen kann jede Fassung.
     *
     * RS256 und nicht Ed25519: ext-sodium fehlt in der offiziellen
     * FreeScout-Umgebung (geprueft: PHP 8.5, CLI und FPM, auch kein
     * sodium_compat im vendor-Baum). openssl ist immer da. Eine Pruefung
     * kostet gemessene 0,06 ms.
     *
     * "algo" steht je Schluessel und nicht global, damit derselbe Mechanismus
     * spaeter auch einen Wechsel des Verfahrens traegt.
     */
    public static function all()
    {
        return array(
            'lat1' => array(
                'algo' => 'RS256',
                'use'  => 'token',
                // Fingerabdruck oCERKz8oGp4LaEM95ivT9JGY - privat auf dem Lizenzserver
                'pem'  => implode("\n", array(
                    "-----BEGIN PUBLIC KEY-----",
                    "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0gjIH9PdvpWsbMISu39a",
                    "4PsWIo7XfzkxFtyvGuQIdsphgmaiCHc6+OfyBAKiNyGadmr7qsFVvdOaJJbuGXS0",
                    "yi6fvILduvB94GTgE2N1R96aoAKqvI3vCJuMygJxFywvK6jQt91IJivzn/2cEIW8",
                    "ZKS6Z3PKRENoBzpZqGIIiKMVKHiyz/ju66h/nAKvWPqjdlfS7M2xCz5EVKn9RCxz",
                    "Uutc6fcvG0vOl6ir4gA3XACbd6DOsT6CC/DoCBN1shTCNfyL+K83yiVTeb777Uwz",
                    "o4xvgDri0d/jO7ccjLxr2NpqaaExBU5OeqY55tM+5ynMmqiSLNo1vY17iT66w76q",
                    "AwIDAQAB",
                    "-----END PUBLIC KEY-----",
                )),
            ),
            'lat2' => array(
                'algo' => 'RS256',
                'use'  => 'token',
                // Fingerabdruck DFm3dNyMrAoX0YF3OeIsnyqx - Reserve, privat nur versiegelt
                'pem'  => implode("\n", array(
                    "-----BEGIN PUBLIC KEY-----",
                    "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArmFbo8ttBC+ovRW3LtdF",
                    "jWF1puB3kIl+wZq0ADqVEW2D00XtugatZfzc8uDPGa8lNp8pUANYGGcCv3Xt1Zwg",
                    "XvA1J2PWImk1cyF6DePeUsjUzoTY1K7sNXnxA0yu751nC/+s3GL8xL0eqiNTA2NI",
                    "46iMUKYLxDj7nd1RGnYtDeO821yzLW09OPRYHYoKt2gpM+p00m3akgr0pyp9VNDM",
                    "ytuOH5dU+z1PecGFwZN0nl0KZzFyzfZfmsazmJMPyfvI31S7U/n8Hxe+g20MaraO",
                    "bh2pYO8HeyQp4gEyFGQ+xrRew81bdFE0ubLqlkRUlZ05a5E+XaZ/GyRLloqfd+AZ",
                    "mwIDAQAB",
                    "-----END PUBLIC KEY-----",
                )),
            ),
            'lap1' => array(
                'algo' => 'RS256',
                'use'  => 'package',
                // Fingerabdruck kU2Dn6XUK1jMvlJQplNNpBse - privat versiegelt, nie am Netz
                'pem'  => implode("\n", array(
                    "-----BEGIN PUBLIC KEY-----",
                    "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAukRpHJ7Y1LVN6LdHy+JA",
                    "Aa9bJJ3VPwkKMjkWqJ+g6d8I0xvb+iJqbcldGsUzpIr/OyZs5E8s5DFxE2qKM1Vt",
                    "g09p8e9h6IVZNgo+gpxlOwteviQM8oNOqRtHCT31XckWeA6iqdeAX603B1MClyC9",
                    "a2GW9ebWbfCkDxzII9xIH086y1o45JWvpakxNPWQiW3SmpaxgIa/r7CmdN4rv+rX",
                    "BDwPH23I9e08NMEJ+1EQ0nPuuJ1MgMZcek8U/qYmySCVumCsbIOQiqwitDmV8+o3",
                    "YHJ7eClNVAWVRnhaJAldh5eWguDaZd1XgT0cHa49Fo3D+eYWUDTfcXg8ZOTbNwX+",
                    "NwIDAQAB",
                    "-----END PUBLIC KEY-----",
                )),
            ),
            'lap2' => array(
                'algo' => 'RS256',
                'use'  => 'package',
                // Fingerabdruck liW+mhoIZSzeVnO/2+XfFb4E - Reserve, privat nur versiegelt
                'pem'  => implode("\n", array(
                    "-----BEGIN PUBLIC KEY-----",
                    "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuqQcPYDt+UEAyEsfxQ7p",
                    "wvCwhLjHbMiJ2uJLDZ2DF/1mb1LvMGcQ57EVVFJnEMeH1iekFyBIChg/eZkd8eaX",
                    "AQKDQblMri2wAKa2obouccWNflu1+y50tJMWtW6JLwjBpfmbwsQ4tLioTEFckgkx",
                    "ea1jG1AwwgtEW0tKj4PeTep/kaIip1JrDKb2xGAamuVZcbIj9miR9iI/HjRWydmJ",
                    "ANHOra+xNW0JLwsz9XFgUm6AZegJMwI5jPZ8nmxUfA+ekEbh4cK63ugdZNjJhm/5",
                    "02dN7vmaUywm5CEEfTVHnLGcG0Rj74nIciXbpkToS/C00zQ3C1sP/pHDqDmWrOFI",
                    "QwIDAQAB",
                    "-----END PUBLIC KEY-----",
                )),
            ),
        );
    }

    /**
     * @param string $kid
     *
     * @return array|null
     */
    /**
     * Zusaetzliche Schluessel aus der Konfiguration.
     *
     * Nur fuer die Entwicklung: der Entwicklungsserver signiert mit eigenen
     * Schluesseln, und die haben in einem ausgelieferten Modul nichts zu
     * suchen. Ueber lastore.extra_public_keys (Pfad auf eine JSON-Datei)
     * kommen sie dort hinzu, wo sie gebraucht werden.
     *
     * @return array
     */
    public static function extra()
    {
        // Diese Methode laeuft bei JEDER Token-Pruefung, also im Seitenaufbau.
        // config() holt den Container - und der steht nicht immer: nicht in
        // einem Kommando vor dem vollen Boot, nicht in einem reinen
        // PHPUnit-Lauf. Ohne dieses try waere aus einer
        // Entwicklungsbequemlichkeit ein Absturz auf dem heissen Pfad
        // geworden. Genau so ist es beim Bauen aufgefallen: 13 Tests, alle
        // mit "Class config does not exist".
        try {
            if (!function_exists('config')) {
                return array();
            }

            $pfad = config('lastore.extra_public_keys');
        } catch (\Throwable $e) {
            return array();
        }

        if (!$pfad || !is_string($pfad) || !is_file($pfad)) {
            return array();
        }

        $json = json_decode(file_get_contents($pfad), true);

        return is_array($json) ? $json : array();
    }

    /**
     * Einen Schluessel suchen - und pruefen, ob er fuer diesen ZWECK gilt.
     *
     * Das ist der Kern der Schluesseltrennung. Ohne die Zweckpruefung koennte
     * der Tokenschluessel, der auf dem Server online liegen MUSS, auch ein
     * Paket signieren - und dann waere die Trennung Dekoration. Wer den
     * Server uebernimmt, bekaeme wieder Code auf jede Installation.
     *
     * @param string      $kid
     * @param string|null $zweck 'token', 'package' oder null fuer beliebig
     *
     * @return array|null
     */
    public static function find($kid, $zweck = null)
    {
        return self::pick(array_merge(self::all(), self::extra()), $kid, $zweck);
    }

    /**
     * Aus einer GEGEBENEN Menge suchen, mit Zweckpruefung.
     *
     * Eine eigene Methode, weil es sonst zwei Wege gaebe: einen ueber find()
     * mit Pruefung und einen fuer Tests ohne. Genau das war beim Bauen der
     * Fall, und der Test, der die Trennung beweisen sollte, ging deshalb
     * durch - ein Testeinstieg, der sich anders verhaelt als die Produktion,
     * ist schlimmer als keiner.
     *
     * @param array       $alle
     * @param string      $kid
     * @param string|null $zweck 'token', 'package' oder null fuer beliebig
     *
     * @return array|null
     */
    public static function pick(array $alle, $kid, $zweck = null)
    {
        $kid = (string) $kid;

        if (!isset($alle[$kid])) {
            return null;
        }

        $key = $alle[$kid];

        if ($zweck === null) {
            return $key;
        }

        // Fehlt die Angabe, gilt der Schluessel fuer BEIDES. Rueckfall fuer
        // die Entwicklungsschluessel und aeltere Eintraege - ohne ihn haette
        // diese Aenderung jede bestehende Installation lahmgelegt.
        $erlaubt = isset($key['use']) ? $key['use'] : 'both';

        if ($erlaubt !== 'both' && $erlaubt !== $zweck) {
            return null;
        }

        return $key;
    }

    /**
     * Die openssl-Konstante zu einem Algorithmusnamen.
     *
     * @param string $algo
     *
     * @return int|null
     */
    public static function opensslAlgo($algo)
    {
        switch ($algo) {
            case 'RS256':
                return OPENSSL_ALGO_SHA256;
            case 'RS384':
                return OPENSSL_ALGO_SHA384;
            case 'RS512':
                return OPENSSL_ALGO_SHA512;
        }

        return null;
    }
}
