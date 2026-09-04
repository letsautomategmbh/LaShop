<?php

namespace Modules\LaStore\Support;

/**
 * Die Hinweise des Shops -- aufbewahrt, damit die Seite sie zeigt.
 *
 * **Der Anlass.** Der Heartbeat bekommt vom Shop Hinweise zurück, und einer
 * davon ist der wichtigste, den ein Kunde je erhält: "12 Nutzer, lizenziert
 * sind 5." Der Server sperrt dafür nicht -- Sperren bestraft genau die
 * Kunden, die wachsen -- er sagt es. Nur landete das Gesagte bis zum
 * 04.09.2026 ausschliesslich in der Ausgabe von `lastore:sync`, also auf
 * einer Kommandozeile. Wer keinen Serverzugang hat, erfuhr es nie; und wer
 * einen hat, liest die nächtliche Ausgabe nicht.
 *
 * Ein Hinweis, den niemand sieht, ist kein Hinweis. Also wird er hinterlegt
 * und auf der Seite gezeigt.
 *
 * **Warum in einer Option und nicht frisch geholt.** Dieselbe Überlegung wie
 * beim Selbstaktualisierer: der Seitenaufbau soll nicht in einen HTTP-Aufruf
 * fallen. Der Heartbeat läuft nachts, schreibt hierher, und die Seite liest
 * nur. Ist der Shop nicht erreichbar, steht der Hinweis von gestern -- und
 * das ist richtig, denn die Überbelegung von gestern besteht heute noch.
 */
class Hinweise
{
    /** Wo die Hinweise liegen. JSON, weil es mehrere sein können. */
    const MERKER = 'lastore.hinweise';

    /** Wann sie zuletzt gesetzt wurden -- fürs Veralten. */
    const MERKER_STAND = 'lastore.hinweise_stand';

    /**
     * Nach diesen Tagen gilt ein Hinweis als überholt.
     *
     * Ohne Verfall bliebe eine Überbelegung für immer stehen, auch wenn der
     * Kunde längst aufgestockt hat: der Shop schickt dann keinen Hinweis mehr,
     * und "kein Hinweis" ist eine leere Liste, die nichts überschreibt --
     * ausser sie wird geschrieben. Sie WIRD geschrieben, siehe merke(). Der
     * Verfall ist der zweite Riegel für den Fall, dass der Heartbeat lange
     * nicht durchkommt.
     */
    const VERFALL_TAGE = 14;

    /**
     * Was der Shop geantwortet hat, festhalten.
     *
     * Auch eine LEERE Liste wird geschrieben. Das ist der Kern: verschwindet
     * eine Überbelegung, schickt der Shop keinen Hinweis mehr, und der alte
     * muss weg. Nur bei einem gescheiterten Heartbeat wird nichts angefasst
     * -- dann weiss niemand etwas Neues.
     *
     * @param array<int, array<string, mixed>> $hinweise
     *
     * @return void
     */
    public static function merke(array $hinweise)
    {
        $sauber = array();

        foreach ($hinweise as $h) {
            $text = isset($h['text']) ? trim((string) $h['text']) : '';

            if ($text === '') {
                continue;
            }

            $sauber[] = array(
                // Nur zwei Stufen, und alles Unbekannte wird zur milderen.
                // Ein Shop, der eines Tages "critical" schickt, soll keine
                // Ansicht zerlegen.
                'level' => (isset($h['level']) && $h['level'] === 'warning') ? 'warning' : 'info',
                'text'  => $text,
            );
        }

        /*
         * Das Feld direkt, NICHT json_encode().
         *
         * \Option macht das selbst -- und beim Lesen wieder rückwärts:
         * `\Option::get()` gibt für einen JSON-Wert ein entschlüsseltes Feld
         * zurück, keinen Text. Der erste Entwurf hier kodierte selbst und
         * prüfte beim Lesen auf is_string(). Geschrieben wurde damit korrekt,
         * gelesen nie -- die Seite blieb still leer, und still ist die
         * schlechteste Eigenschaft, die ein Hinweis haben kann.
         *
         * ACHTUNG: der Wert veraltet INNERHALB einer Anfrage. Ein set() und
         * ein anschliessendes get() im selben Prozess liefern noch den alten
         * Stand. Für den Betrieb ist das gleichgültig -- merke() läuft im
         * nächtlichen Befehl, offen() im Web --, aber wer es in einem Test
         * hintereinander aufruft, sieht Gespenster.
         */
        \Option::set(self::MERKER, $sauber);
        \Option::set(self::MERKER_STAND, time());
    }

    /**
     * Was gezeigt werden soll.
     *
     * @return array<int, array<string, string>>
     */
    public static function offen()
    {
        $stand = (int) \Option::get(self::MERKER_STAND, 0);

        if ($stand > 0 && $stand < time() - self::VERFALL_TAGE * 86400) {
            return array();
        }

        $roh = \Option::get(self::MERKER, array());

        // Beide Gestalten annehmen: \Option gibt normalerweise ein Feld
        // zurueck, aber ein von Hand geschriebener Wert oder ein alter Stand
        // kann Text sein. Eine Ansicht soll daran nicht scheitern.
        $liste = is_string($roh) ? json_decode($roh, true) : $roh;

        if (!is_array($liste)) {
            return array();
        }

        // Gegen eine kaputte oder veraltete Ablage: nur Einträge, die die
        // beiden Felder wirklich haben, gehen in die Ansicht.
        $raus = array();

        foreach ($liste as $h) {
            if (is_array($h) && isset($h['text']) && is_string($h['text']) && $h['text'] !== '') {
                $raus[] = array(
                    'level' => (isset($h['level']) && $h['level'] === 'warning') ? 'warning' : 'info',
                    'text'  => $h['text'],
                );
            }
        }

        return $raus;
    }
}
