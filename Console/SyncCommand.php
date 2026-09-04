<?php

namespace Modules\LaStore\Console;

use Illuminate\Console\Command;
use Modules\LaStore\Entities\CatalogEntry;
use Modules\LaStore\Services\LicenseService;
use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\Hinweise;

/**
 * Ein Einstiegspunkt fuer alles Laufende: Heartbeat, Lizenzpruefung,
 * Katalogabgleich. Statt vier Kommandos eines, das sich im Betrieb auch von
 * Hand nachfahren laesst.
 */
class SyncCommand extends Command
{
    protected $signature = 'lastore:sync
                            {--dry-run : Nur zeigen, was passieren würde}
                            {--force : Auch dann, wenn der Turnus noch nicht abgelaufen ist}';

    protected $description = 'Katalog, Lizenzen und Heartbeat mit dem Shop abgleichen';

    public function handle()
    {
        $service = new LicenseService();
        $installation = $service->installation();

        if ($installation->isOffline()) {
            $this->info('Diese Installation ist auf Offline-Betrieb gestellt. Nichts zu tun.');

            return 0;
        }

        $dry = (bool) $this->option('dry-run');

        if ($dry) {
            $this->comment('Probelauf — es wird nichts gespeichert.');
        }

        $this->step('Installation anmelden', function () use ($service, $dry, $installation) {
            if ($installation->isRegistered()) {
                return 'bereits angemeldet als '.$installation->installation_id;
            }

            if ($dry) {
                return 'würde sich anmelden';
            }

            return 'angemeldet als '.$service->register()->installation_id;
        });

        $this->step('Katalog abgleichen', function () use ($service, $dry) {
            $products = $service->client()->catalog();

            if ($dry) {
                return count($products).' Produkte gefunden';
            }

            $count = CatalogEntry::replaceAll($products);
            $installation = $service->installation();
            $installation->last_catalog_sync_at = \Carbon\Carbon::now();
            $installation->save();

            return $count.' Produkte übernommen';
        });

        $this->step('Lizenzen prüfen', function () use ($service, $dry) {
            if ($dry) {
                return 'würde alle Lizenzen in einem Aufruf prüfen';
            }

            $result = $service->refreshAll();

            if (!$result) {
                return 'keine Lizenzen hinterlegt';
            }

            $parts = [];

            foreach ($result as $alias => $status) {
                $parts[] = $alias.': '.$status;
            }

            return implode(', ', $parts);
        });

        $this->step('Heartbeat senden', function () use ($service, $dry, $installation) {
            if (!$installation->isRegistered()) {
                return 'übersprungen, noch nicht angemeldet';
            }

            if (!$this->option('force') && !$dry && $installation->last_heartbeat_at
                && $installation->last_heartbeat_at->diffInHours(\Carbon\Carbon::now()) < $installation->heartbeat_hours) {
                return 'noch nicht fällig';
            }

            if ($dry) {
                return 'würde senden';
            }

            $facts = $service->facts();
            $facts['modules'] = $service->installedModuleVersions();

            $response = $service->client()->heartbeat($facts);

            $installation->last_heartbeat_at = \Carbon\Carbon::now();

            if (!empty($response['next_in_hours'])) {
                $installation->heartbeat_hours = (int) $response['next_in_hours'];
            }

            $installation->save();

            /*
             * Festhalten, nicht nur ausgeben.
             *
             * Bis zum 04.09.2026 landeten die Hinweise ausschliesslich in der
             * Ausgabe dieses Befehls -- also auf einer Kommandozeile, die
             * nachts läuft. Der wichtigste davon ist "12 Nutzer, lizenziert
             * sind 5", und den erfuhr ein Kunde ohne Serverzugang nie. Ein
             * Hinweis, den niemand sieht, ist kein Hinweis.
             *
             * Gemerkt wird auch die LEERE Liste: verschwindet eine
             * Überbelegung, schickt der Shop keinen Hinweis mehr, und der
             * alte muss weg.
             */
            Hinweise::merke(
                isset($response['notices']) && is_array($response['notices']) ? $response['notices'] : array()
            );

            foreach (isset($response['notices']) ? $response['notices'] : [] as $notice) {
                $this->warn('  Hinweis vom Shop: '.(isset($notice['text']) ? $notice['text'] : ''));
            }

            return 'gesendet';
        });

        return 0;
    }

    /**
     * Ein Schritt darf den ganzen Lauf nicht abbrechen - der Heartbeat soll
     * auch dann noch rausgehen, wenn der Katalog gerade nicht erreichbar ist.
     *
     * @param string   $label
     * @param callable $work
     *
     * @return void
     */
    protected function step($label, $work)
    {
        try {
            $this->info(str_pad($label, 24).' … '.$work());
        } catch (StoreException $e) {
            $this->error(str_pad($label, 24).' … '.$e->errorCode().': '.$e->getMessage());
        } catch (\Exception $e) {
            $this->error(str_pad($label, 24).' … '.$e->getMessage());
        }
    }
}
