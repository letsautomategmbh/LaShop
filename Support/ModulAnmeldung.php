<?php

namespace Modules\LaStore\Support;

/**
 * Ein getauschtes Modul beim Kern anmelden.
 *
 * **Warum es diese Klasse gibt.** Ein Modul-Update tauscht Dateien. Damit ist
 * die Arbeit NICHT getan, und die Annahme, FreeScout ziehe das Uebrige selbst
 * nach, ist falsch. Am 04.09.2026 nachgesehen und belegt:
 *
 *   - `ModulesController::ajax()` laedt herunter, entpackt, ruft
 *     `\Module::clearCache()`, aktiviert die Lizenz -- und war fertig.
 *   - `module:migrate` steht im ganzen Kern an genau zwei Stellen, beide in
 *     `freescout:module-install`. Dieser Befehl laeuft beim AKTIVIEREN
 *     (`ModulesController` Zeile 398), nicht beim Aktualisieren.
 *
 * Ein bereits aktives Modul bekam seine neuen Wanderungen also nie -- weder
 * ueber FreeScouts eigenen Knopf noch ueber unseren. Aufgefallen ist es an der
 * Produktion: `add_license_type_to_lastore_licenses` lag nach zwei
 * Selbstaktualisierungen auf der Platte und war nicht ausgefuehrt. Der Code,
 * der die Spalte schreibt, war schon da.
 *
 * **Was hier NICHT selbst getan wird.** Kein eigenes `Schema::table`, kein
 * eigener Migrator, kein eigenes Symlink-Setzen. Aufgerufen wird derselbe
 * Befehl, den FreeScouts eigener Knopf aufruft:
 *
 *     freescout:module-install <alias>
 *         -> cache:clear
 *         -> module:migrate <Name> --force
 *         -> Public-Symlink neu setzen
 *         -> freescout:clear-cache
 *
 * Zwei Stellen, die dasselbe auf zwei Weisen tun, gehen irgendwann
 * auseinander. Diese hier geht mit dem Kern mit.
 *
 * **Reihenfolge.** Vorher muss `\Module::clearCache()` gelaufen sein, sonst
 * loest `findByAlias()` im Befehl noch die alte `module.json` auf -- und wenn
 * sich der Modulname geaendert hat (LaStore -> LaShop), wanderte die falsche
 * oder gar keine Migration.
 *
 * **Der Symlink.** `public/modules/<alias>` zeigt auf einen Pfad, nicht auf
 * eine Inode. Ein Tausch, der das Verzeichnis gleich benennt, laesst ihn
 * darum heil. Er fehlt aber, wenn ein Modul `Public/` erst mit dem Update
 * bekommt -- auch das setzt der Befehl gerade.
 */
class ModulAnmeldung
{
    /**
     * \Throwable und NICHT \Exception, an beiden Stellen.
     *
     * Was hier schiefgehen kann, ist selten eine Exception: eine Wanderung,
     * die eine Klasse nicht findet, wirft einen \Error. Der ginge an einem
     * catch(\Exception) vorbei -- und zwar genau NACH dem Tausch, wenn die
     * Dateien schon liegen. Aus einem behandelbaren Befund waere damit ein
     * Abbruch geworden.
     *
     * @param string $alias Modul-Alias, klein, wie in module.json
     *
     * @return array{ok: bool, ausgabe: string, fehler: ?string}
     */
    public static function anmelden($alias)
    {
        // Der Kern muss die neue module.json sehen, bevor der Befehl den
        // Modulnamen daraus aufloest.
        try {
            \Module::clearCache();
        } catch (\Throwable $e) {
            // Kein Grund abzubrechen: der Befehl leert selbst auch Caches.
        }

        $puffer = new \Symfony\Component\Console\Output\BufferedOutput();

        try {
            $code = \Artisan::call(
                'freescout:module-install',
                ['module_alias' => $alias],
                $puffer
            );
        } catch (\Throwable $e) {
            /*
             * Eine gescheiterte Wanderung darf das Update nicht
             * zurueckdrehen -- die Dateien liegen schon, und ein Ruecktausch
             * an dieser Stelle liesse eine halb gewanderte Datenbank unter
             * altem Code zurueck. Schlimmer als der gemeldete Fehler.
             *
             * Der Aufrufer sagt es dem Menschen, samt dem Befehl von Hand.
             */
            return [
                'ok'      => false,
                'ausgabe' => trim($puffer->fetch()),
                'fehler'  => $e->getMessage(),
            ];
        }

        return [
            'ok'      => $code === 0 || $code === null,
            'ausgabe' => trim($puffer->fetch()),
            'fehler'  => null,
        ];
    }

    /**
     * Der Befehl, den ein Mensch tippen muesste, wenn es hier scheitert.
     *
     * @param string $alias
     *
     * @return string
     */
    public static function befehl($alias)
    {
        return 'php artisan freescout:module-install '.$alias;
    }
}
