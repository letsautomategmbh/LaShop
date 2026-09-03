<?php

namespace Modules\LaStore\Support;

/**
 * Uebersetzen, aber niemals daran scheitern.
 *
 * __() holt den Uebersetzer aus dem Container. Das ist im laufenden FreeScout
 * immer da - aber nicht in einem Kommando vor dem vollen Boot und nicht in
 * einem reinen PHPUnit-Lauf.
 *
 * Der Grund, warum das ueberhaupt eine eigene Klasse ist: die Meldungen, um
 * die es hier geht, sind AUSNAHMETEXTE und Diagnosen. Wirft das Bauen einer
 * Fehlermeldung, verschluckt es den Fehler, den man lesen wollte. Genau so
 * kam beim Entwickeln ein "Class translator does not exist" statt "im Paket
 * fehlt module.json" - und man sucht dann eine Stunde an der falschen Stelle.
 *
 * Ohne Uebersetzer kommt der deutsche Ausgangstext zurueck, Platzhalter
 * eingesetzt. Untuebersetzt und lesbar ist besser als eine Ausnahme.
 */
class Text
{
    /**
     * @param string $text
     * @param array  $ersetzungen
     *
     * @return string
     */
    public static function get($text, array $ersetzungen = array())
    {
        if (function_exists('app')) {
            try {
                if (app()->bound('translator')) {
                    return __($text, $ersetzungen);
                }
            } catch (\Throwable $e) {
                // Auch ein kaputter Container darf hier nicht durchschlagen.
            }
        }

        foreach ($ersetzungen as $name => $wert) {
            $text = str_replace(':'.$name, (string) $wert, $text);
        }

        return $text;
    }
}
