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
     * ACHTUNG: das sind die ENTWICKLUNGSSCHLUESSEL.
     * Vor der ersten oeffentlichen Auslieferung durch die Produktivschluessel
     * ersetzen. Der zugehoerige private Schluessel darf niemals in dieses
     * Repository, in GitHub Actions oder in einen Passwortspeicher am selben
     * Netz gelangen - er liegt auf dem Lizenzserver und versiegelt im Tresor.
     */
    public static function all()
    {
        return array(
            'la1' => array(
                'algo' => 'RS256',
                'pem'  => <<<'PEM'
        -----BEGIN PUBLIC KEY-----
        MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAytC0EhS3wVU3uw7Ol03o
        nCwdPKYD8zFaq59TIo9cHjnbWgI8U4Ca8TeEav92+H/92kuXRQvtfdJM6SSEj5Fw
        ZtQuMmgnChdyQ7mnuq9zl9fsAtXOD/g2TYsoIluGCJBnrC4tDOYX4PByBuf852bZ
        5Gb6d9/C8PfZ2nHKsRnCwHpLoYjm/IAE1i22aylUZx0R+sv5mMMvT7wwu9HSN4zO
        9Puw2sc7XQVK67baNojI/Plsl7j2/xa59c9JW/9ZUU3xkmHsfEL3qv08Dp2q9S0z
        5GuP7JCJrkU13s3PYTvq22S9MdvRJ23LRcHo8epZbbyghUR+7FsMHQF6W2xRNVCE
        /QIDAQAB
        -----END PUBLIC KEY-----
        PEM,
            ),
            'la2' => array(
                'algo' => 'RS256',
                'pem'  => <<<'PEM'
        -----BEGIN PUBLIC KEY-----
        MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAxwKRNiMxp5CIOzuMzfqO
        rn3KsU44bUilAOv8MbCFMC1YVFOJTeeMYFS/06ZPyfqJ2CkmU4GxCDLvkJ8WE1Gd
        6QSKhstBvyEmEyF7Wna0qCY84iR8mHhJY7B306YSVjl22V1S5oy7WWTVwitVijP4
        l06ChaxkpRlxwA7vc1uVF87vB4f1+HyXWSulhATrr7hMcfKIEKCGu8A0KGs4K69j
        O9u+w4enOTjaFjJpvubOysNlj213bypOOUesBOTQBzoWaLZarDrihlGUXDHgzrLd
        81tRpuKA+ty41aAXgVr36opIkxAZAN9KwXjBmoLqv7bB12u8Uq1htfZAzY9MwBXK
        KQIDAQAB
        -----END PUBLIC KEY-----
        PEM,
            ),
        );
    }

    /**
     * @param string $kid
     *
     * @return array|null
     */
    public static function find($kid)
    {
        $all = self::all();

        return isset($all[$kid]) ? $all[$kid] : null;
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
