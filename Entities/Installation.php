<?php

namespace Modules\LaStore\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Die Identitaet dieser FreeScout-Instanz gegenueber dem Shop.
 * Genau eine Zeile.
 */
class Installation extends Model
{
    const MODE_ONLINE = 'online';
    const MODE_OFFLINE = 'offline';

    protected $table = 'lastore_installation';

    protected $guarded = ['id'];

    protected $dates = [
        'registered_at', 'last_heartbeat_at', 'last_catalog_sync_at', 'max_seen_at',
    ];

    /**
     * Die eine Zeile, notfalls angelegt.
     *
     * @return self
     */
    public static function current()
    {
        $row = static::query()->orderBy('id')->first();

        if (!$row) {
            $row = new static();
            $row->mode = self::MODE_ONLINE;
            $row->save();
        }

        return $row;
    }

    /** @return bool */
    public function isRegistered()
    {
        return (bool) $this->installation_id;
    }

    /** @return bool */
    public function isOffline()
    {
        return $this->mode === self::MODE_OFFLINE;
    }

    /**
     * Das Geheimnis. Verschluesselt abgelegt, damit ein Datenbankauszug
     * allein nicht genuegt, um Pakete zu ziehen.
     *
     * @return string
     */
    public function secretPlain()
    {
        if (!$this->secret) {
            return '';
        }

        try {
            return \Crypt::decryptString($this->secret);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * @param string $value
     *
     * @return void
     */
    public function setSecretPlain($value)
    {
        $this->secret = $value === '' || $value === null ? null : \Crypt::encryptString($value);
    }

    /**
     * Die Zeitmarke fortschreiben, gegen die eine zurueckgestellte Systemuhr
     * auffaellt. Springt nie zurueck - das ist der ganze Zweck.
     *
     * @param int|null $timestamp
     *
     * @return void
     */
    public function touchMaxSeen($timestamp = null)
    {
        $candidate = $timestamp === null ? time() : (int) $timestamp;
        $current = $this->max_seen_at ? $this->max_seen_at->timestamp : 0;

        if ($candidate > $current) {
            $this->max_seen_at = \Carbon\Carbon::createFromTimestamp($candidate);
            $this->save();
        }
    }

    /** @return int|null */
    public function maxSeenTimestamp()
    {
        return $this->max_seen_at ? $this->max_seen_at->timestamp : null;
    }
}
