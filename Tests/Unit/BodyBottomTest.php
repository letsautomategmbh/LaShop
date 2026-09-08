<?php

namespace Modules\LaStore\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Jede Ansicht, die body_bottom belegt, muss @parent weitergeben.
 *
 * **Warum das ein eigener Test ist.** FreeScout haengt seine schwebenden
 * Meldungen ueber @section('body_bottom') ein -- Erfolg, Warnung, Fehler,
 * alle. Blade setzt spaeteren Inhalt an die Stelle, an der @parent steht;
 * fehlt es, wird der spaetere Inhalt STILL verworfen. Kein Fehler, kein
 * Protokolleintrag, keine leere Stelle im Aufbau: die Meldung existiert und
 * erscheint nie.
 *
 * **Was es gekostet hat.** Genau das war in index.blade.php der Fall. Das
 * Modulupdate scheiterte auf unserem Host an etwas anderem (ein rename ueber
 * eine Dateisystemgrenze), und der Knopf sagte dazu nichts -- "Das
 * Modulverzeichnis liess sich nicht ersetzen" wurde gesetzt und hier
 * verschluckt. Ein Knopf, der nichts tut und nichts sagt, sieht aus wie
 * einer ohne Funktion. Booking stand deshalb wochenlang auf 0.11.0, waehrend
 * im Katalog 0.13.0 lag, und die Suche nach dem Grund begann an der
 * falschesten aller Stellen: beim Verdacht, der Knopf sei nicht verdrahtet.
 *
 * **Warum ein Test und nicht nur ein Kommentar.** Der Fehler ist nicht zu
 * sehen. Er faellt beim Lesen nicht auf, beim Klicken nicht auf, und im
 * Protokoll steht nichts. Ein Riegel, der ihn beim Bauen fangt, ist die
 * einzige Stelle, an der er ueberhaupt bemerkt werden kann.
 */
class BodyBottomTest extends TestCase
{
    /** @return array<string> Pfade aller Blade-Ansichten des Moduls */
    private function ansichten()
    {
        $wurzel = dirname(dirname(__DIR__)).'/Resources/views';

        $dateien = array();
        $lauf = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($wurzel));

        foreach ($lauf as $datei) {
            if (substr((string) $datei, -10) === '.blade.php') {
                $dateien[] = (string) $datei;
            }
        }

        return $dateien;
    }

    public function testJedesBodyBottomGibtWeiter()
    {
        $ansichten = $this->ansichten();

        $this->assertNotEmpty($ansichten, 'Ohne gefundene Ansichten prüft dieser Test nichts.');

        foreach ($ansichten as $pfad) {
            $inhalt = (string) file_get_contents($pfad);

            if (strpos($inhalt, "@section('body_bottom')") === false) {
                continue;
            }

            /*
             * Nur der Abschnitt selbst, nicht die ganze Datei: ein @parent in
             * einem anderen Abschnitt wuerde sonst hier als Beweis gelten.
             */
            $ab = strpos($inhalt, "@section('body_bottom')");
            $bis = strpos($inhalt, '@endsection', $ab);
            $abschnitt = $bis === false ? substr($inhalt, $ab) : substr($inhalt, $ab, $bis - $ab);

            /*
             * Und nicht das @parent im erklärenden Kommentar zählen. Der
             * Kommentar dort nennt es mehrfach beim Namen -- ohne diesen
             * Schritt wäre der Test durch seine eigene Erklärung grün.
             */
            $ohneKommentare = preg_replace('/\{\{--.*?--\}\}/s', '', $abschnitt);

            $this->assertContains(
                '@parent',
                preg_split('/\s+/', trim((string) $ohneKommentare)),
                'In '.basename($pfad).' fehlt @parent in body_bottom — dort verschwinden '
                .'alle schwebenden Meldungen von FreeScout, lautlos.'
            );
        }
    }
}
