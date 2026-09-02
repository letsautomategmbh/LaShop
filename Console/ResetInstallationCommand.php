<?php

namespace Modules\LaStore\Console;

use Illuminate\Console\Command;
use Modules\LaStore\Entities\CatalogEntry;
use Modules\LaStore\Entities\Installation;
use Modules\LaStore\Entities\License;

/**
 * Die Identitaet dieser Installation verwerfen und beim naechsten Abgleich
 * eine neue holen.
 *
 * Bewusst ein eigenes Kommando und kein stilles Neuanmelden bei
 * "unauthorized": eine neue Kennung laesst den alten Sitz auf dem Server
 * belegt zurueck. Der Kunde muesste ihn dann im Portal freigeben, und das
 * zaehlt gegen seine zwei Umzuege. Ein Client, der das von selbst tut,
 * verbraucht sie ohne Zutun des Kunden.
 *
 * Gebraucht wird es trotzdem: beim Wechsel der Serveradresse, und wenn eine
 * Sicherung auf einer zweiten Maschine eingespielt wurde und beide dieselbe
 * Kennung tragen.
 */
class ResetInstallationCommand extends Command
{
    protected $signature = 'lastore:reset-installation {--force : Ohne Rückfrage}';

    protected $description = 'Installations-Kennung und hinterlegte Lizenzen verwerfen';

    public function handle()
    {
        $installation = Installation::current();

        if ($installation->isRegistered()) {
            $this->warn('Aktuelle Kennung: '.$installation->installation_id);
            $this->line('Auf dem Server bleibt der Sitz dieser Kennung belegt. Vorher im');
            $this->line('Kundenportal freigeben, sonst ist der Schlüssel danach blockiert.');
            $this->line('');
        }

        if (!$this->option('force') && !$this->confirm('Kennung und hinterlegte Lizenzen wirklich verwerfen?', false)) {
            $this->info('Abgebrochen.');

            return 0;
        }

        License::query()->delete();
        CatalogEntry::query()->delete();
        $installation->delete();

        $this->info('Verworfen. Beim nächsten lastore:sync meldet sich diese Installation neu an.');

        return 0;
    }
}
