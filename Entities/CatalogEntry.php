<?php

namespace Modules\LaStore\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Der zuletzt gesehene Katalog. Eigene Tabelle statt Cache, damit die
 * Store-Seite auch ohne Server etwas zeigt - ein leerer Katalog sieht aus
 * wie ein Fehler, und der Kunde ruft dann an.
 */
class CatalogEntry extends Model
{
    protected $table = 'lastore_catalog_cache';

    protected $guarded = ['id'];

    protected $dates = ['fetched_at'];

    /** @return array */
    public function data()
    {
        $decoded = json_decode((string) $this->payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function value($name, $default = null)
    {
        $data = $this->data();

        return array_key_exists($name, $data) ? $data[$name] : $default;
    }

    /**
     * Den Katalog ersetzen. Eintraege, die der Server nicht mehr fuehrt,
     * verschwinden - sonst bleiben zurueckgezogene Produkte ewig stehen.
     *
     * @param array $items
     *
     * @return int
     */
    public static function replaceAll(array $items)
    {
        $seen = [];
        $now = \Carbon\Carbon::now();

        foreach ($items as $item) {
            if (empty($item['alias'])) {
                continue;
            }

            $alias = (string) $item['alias'];
            $seen[] = $alias;

            static::updateOrCreate(
                ['alias' => $alias],
                ['payload' => json_encode($item), 'fetched_at' => $now]
            );
        }

        if ($seen) {
            static::whereNotIn('alias', $seen)->delete();
        }

        return count($seen);
    }
}
