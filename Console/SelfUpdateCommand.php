<?php

namespace Modules\LaStore\Console;

use Illuminate\Console\Command;
use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\SelfUpdater;

/**
 * LaShop selbst aktualisieren.
 *
 * Getrennt von lastore:update, und zwar mit Grund: dieser Befehl tauscht das
 * Modul aus, in dem er selbst steht. Ihn in dieselbe Schleife zu haengen, die
 * die lizenzierten Module aktualisiert, hiesse, mitten in einem Durchlauf den
 * Boden zu wechseln -- danach koennte keine Klasse mehr nachgeladen werden.
 *
 * Deshalb: eigener Befehl, letzte Handlung, danach nichts mehr.
 */
class SelfUpdateCommand extends Command
{
    protected $signature = 'lastore:self-update
                            {--pruefen : Nur nachsehen, nichts tauschen}
                            {--yes : Ohne Rückfrage}';

    protected $description = 'LaShop selbst auf die neueste Fassung bringen';

    public function handle()
    {
        try {
            $bericht = SelfUpdater::pruefen();
        } catch (StoreException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->line(sprintf('  installiert: %-10s verfügbar: %s',
            $bericht['jetzt'] ?: '—', $bericht['version']));

        if ($bericht['status'] === SelfUpdater::AKTUELL) {
            $this->line('  <comment>aktuell, nichts zu tun</>');

            return 0;
        }

        if ($this->option('pruefen')) {
            $this->line('  <fg=yellow>Eine neuere Fassung liegt bereit.</>');

            return 0;
        }

        if (!$this->option('yes') && !$this->confirm('Jetzt auf '.$bericht['version'].' aktualisieren?', false)) {
            $this->line('  abgebrochen');

            return 0;
        }

        try {
            $ergebnis = SelfUpdater::aktualisieren();
        } catch (StoreException $e) {
            // Eine fehlgeschlagene Pruefung ist KEIN Betriebsfehler: sie ist
            // das Ergebnis, das die Pruefung liefern soll. Der Rueckgabewert
            // sagt es dem Aufrufer, die Zeile dem Menschen.
            $this->error('  '.$e->getMessage());

            return 1;
        }

        $this->info('  auf '.$ergebnis['version'].' gebracht');

        if ($ergebnis['alt'] !== '' && $ergebnis['alt'] !== null) {
            $this->line('  bisheriger Stand liegt in '.basename($ergebnis['alt']));
        }

        $this->line('  <comment>Konfigurations- und Ansichtszwischenspeicher geleert.</>');

        // Der Tausch ist nur die halbe Arbeit: ohne freescout:module-install
        // laufen die Wanderungen des neuen Standes nie. Scheitert das, ist
        // der Code neu und die Datenbank alt -- das muss der Mensch sehen.
        $anmeldung = isset($ergebnis['anmeldung']) ? $ergebnis['anmeldung'] : null;

        if ($anmeldung && !$anmeldung['ok']) {
            $this->error('  Beim Kern anmelden ist gescheitert: '.$anmeldung['fehler']);
            $this->warn('  Bitte von Hand: '.SelfUpdater::anmeldebefehl());

            return 1;
        }

        if ($anmeldung) {
            $this->line('  <comment>Wanderungen gelaufen, Symlink gesetzt, Cache geleert.</>');
        }

        return 0;
    }
}
