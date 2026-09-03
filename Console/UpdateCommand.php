<?php

namespace Modules\LaStore\Console;

use Illuminate\Console\Command;
use Modules\LaStore\Entities\License;
use Modules\LaStore\Services\PackageInstaller;
use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\InstalledModules;

/**
 * Module aktualisieren - mit Pruefung der Signatur.
 *
 * Bis dahin lief das Installieren ueber FreeScouts eigenen Updater, und der
 * prueft NICHTS. Die Signatur wurde erzeugt, ausgeliefert und nicht benutzt.
 * Wer den Auslieferungsweg unterwandert, bekam damit seinen Code auf jede
 * Installation.
 *
 * Ohne Argument werden alle lizenzierten Module geprueft. Mit einem Alias nur
 * dieses. --pruefen laedt herunter und prueft, installiert aber nicht - der
 * Weg, um vor einem Wartungsfenster zu wissen, was ansteht.
 */
class UpdateCommand extends Command
{
    protected $signature = 'lastore:update
                            {alias? : Nur dieses Modul}
                            {--pruefen : Herunterladen und prüfen, aber nicht installieren}
                            {--yes : Ohne Rückfrage installieren}';

    protected $description = 'Modul-Updates holen, Signatur prüfen und installieren';

    public function handle()
    {
        $installer = new PackageInstaller();
        $alias = $this->argument('alias');

        $aliase = $alias
            ? array(strtolower($alias))
            : License::pluck('product_alias')->all();

        if (!$aliase) {
            $this->info('Keine lizenzierten Module. Nichts zu tun.');

            return 0;
        }

        $installiert = $this->installierteFassungen();
        $fehler = 0;
        $getan = 0;

        foreach ($aliase as $a) {
            $this->line('');
            $this->line('<options=bold>'.$a.'</>');

            try {
                $meldung = $installer->available($a);
            } catch (StoreException $e) {
                $this->error('  Der Shop antwortet nicht wie erwartet: '.$e->getMessage());
                $fehler++;
                continue;
            }

            $neu = isset($meldung['version']) ? $meldung['version'] : '';
            $alt = isset($installiert[$a]) ? $installiert[$a] : null;

            $this->line(sprintf('  installiert: %-12s verfügbar: %s', $alt ?: '—', $neu ?: '—'));

            if ($alt !== null && $neu !== '' && version_compare($neu, $alt, '<=')) {
                /*
                 * <comment> und NICHT <fg=gray>: die Symfony-Fassung, die in
                 * FreeScout steckt, kennt "gray" nicht und bricht mit
                 * "Invalid foreground color specified" ab. Das traf genau den
                 * Zweig "nichts zu tun" -- also jede Nacht, in der es nichts
                 * zu tun gab. Aufgefallen erst, als der Selbstaktualisierer
                 * dieselbe Zeile kopierte und der Zweig zum ersten Mal
                 * betreten wurde.
                 */
                $this->line('  <comment>aktuell, nichts zu tun</>');
                continue;
            }

            try {
                $datei = $installer->fetchVerified($meldung);
            } catch (StoreException $e) {
                // Eine fehlgeschlagene Pruefung ist KEIN Betriebsfehler unter
                // vielen - sie ist der Grund, warum es diesen Befehl gibt.
                $this->error('  '.$e->getMessage());
                $this->line('  <comment>Code: '.$e->errorCode().'</>');
                $fehler++;
                continue;
            }

            $groesse = round(filesize($datei) / 1024);
            $this->info(sprintf('  geprüft: unverändert und signiert mit %s (%d KB)', $meldung['signing_kid'], $groesse));

            if ($this->option('pruefen')) {
                @unlink($datei);
                $this->line('  <comment>--pruefen: nicht installiert</>');
                continue;
            }

            if (!$this->option('yes') && !$this->confirm('  '.$a.' auf '.$neu.' aktualisieren?', false)) {
                @unlink($datei);
                $this->line('  übersprungen');
                continue;
            }

            try {
                $ergebnis = $installer->install($datei, $a);
            } catch (StoreException $e) {
                $this->error('  '.$e->getMessage());
                $fehler++;
                continue;
            }

            $this->info(sprintf('  installiert: %s %s', $ergebnis['name'], $ergebnis['version']));

            if ($ergebnis['backup']) {
                $this->line('  <comment>bisheriger Stand: '.basename($ergebnis['backup']).'</>');
            }

            $getan++;
        }

        $this->line('');

        if ($getan > 0) {
            $this->warn('FreeScout lädt die neuen Fassungen beim nächsten Aufruf. Danach:');
            $this->warn('  php artisan migrate --force');
            $this->warn('  php artisan freescout:clear-cache');
        }

        if ($fehler > 0) {
            $this->error($fehler.' Modul(e) nicht aktualisiert.');

            return 1;
        }

        return 0;
    }

    /**
     * Alias => installierte Fassung.
     *
     * Ueber getAlias() und NICHT ueber \Module::find(): das sucht nach dem
     * NAMEN. "invoicing" heisst intern "Activities" und faellt dort still
     * durch - derselbe Fehler, der im Abgleich schon einmal zugeschlagen hat.
     *
     * @return array
     */
    protected function installierteFassungen()
    {
        $raus = array();

        foreach (\Module::all() as $modul) {
            $raus[$modul->getAlias()] = $modul->get('version');
        }

        return $raus;
    }
}
