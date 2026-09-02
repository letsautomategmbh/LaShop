<?php

namespace Modules\LaStore\Console;

use Illuminate\Console\Command;
use Modules\LaStore\Entities\Installation;
use Modules\LaStore\Services\LicenseService;
use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\InstalledModules;

/**
 * Bestandsaufnahme und Uebernahme.
 *
 * Zeigt, was auf dieser Installation laeuft, was davon eine Lizenz hat und
 * was noch uebernommen werden muss. Mit --key wird ein einzelnes Modul
 * uebernommen, ohne dass es dafuer die Oberflaeche braucht - beim Umstellen
 * eines Kunden per SSH ist das der schnellere Weg.
 */
class AdoptCommand extends Command
{
    protected $signature = 'lastore:adopt
                            {alias? : Modul-Alias}
                            {--key= : Lizenzschlüssel für dieses Modul}';

    protected $description = 'Installierte Module aufnehmen und mit Lizenzen verbinden';

    public function handle()
    {
        $installation = Installation::current();

        $this->line('Installation: '.($installation->installation_id ?: 'noch nicht angemeldet'));

        if (!$installation->isRegistered()) {
            $this->warn('Zuerst php artisan lastore:sync ausführen.');

            return 1;
        }

        if ($this->argument('alias')) {
            return $this->adoptOne((string) $this->argument('alias'));
        }

        $this->showInventory();
        $this->showCredentialWarning();

        return 0;
    }

    protected function adoptOne($alias)
    {
        $key = (string) $this->option('key');

        if ($key === '') {
            $this->error('Ohne --key kann nichts übernommen werden.');

            return 1;
        }

        try {
            $license = (new LicenseService())->activate($key, $alias);
        } catch (StoreException $e) {
            $this->error($e->errorCode().': '.$e->getMessage());

            return 1;
        }

        $this->info(sprintf(
            '%s übernommen — %s, gültig bis %s.',
            $alias,
            $license->status,
            $license->valid_until ? $license->valid_until->format('d.m.Y') : 'unbefristet'
        ));
        $this->line('Das Modul läuft unverändert weiter. Es ändert sich nur, woher die Updates kommen.');

        return 0;
    }

    protected function showInventory()
    {
        $labels = InstalledModules::stateLabels();
        $rows = array();

        foreach (InstalledModules::inventory() as $row) {
            if ($row['state'] === InstalledModules::STATE_FOREIGN) {
                continue;
            }

            $rows[] = array(
                $row['alias'],
                $row['installed'] ?: '—',
                $row['available'] ?: '—',
                $labels[$row['state']][1],
                $row['credentials'] ?: '',
            );
        }

        $this->line('');
        $this->table(array('Alias', 'Installiert', 'Im Katalog', 'Zustand', 'module.json'), $rows);

        $adoptable = InstalledModules::adoptable();

        if ($adoptable) {
            $this->line('');
            $this->warn(count($adoptable).' Modul(e) laufen ohne Lizenz aus dem Store:');

            foreach ($adoptable as $row) {
                $this->line('  php artisan lastore:adopt '.$row['alias'].' --key=LA-…');
            }
        }
    }

    protected function showCredentialWarning()
    {
        $withCredentials = InstalledModules::withCredentials();

        if (!$withCredentials) {
            return;
        }

        $this->line('');
        $this->warn(count($withCredentials).' Modul(e) tragen noch Zugangsdaten in der module.json.');
        $this->line('Sie stehen damit auch in der Git-Historie der jeweiligen Repositories.');
        $this->line('Erst wenn alle Installationen übernommen sind, darf das Token dort weg:');
        $this->line('');

        foreach ($withCredentials as $row) {
            $this->line(sprintf('  %-24s %s', $row['alias'], $row['credentials']));
        }
    }
}
