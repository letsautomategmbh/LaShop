<?php

namespace Modules\LaStore\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\LaStore\Support\LicenseKey;
use Modules\LaStore\Support\LicenseToken;

class License extends Model
{
    protected $table = 'lastore_licenses';

    protected $guarded = ['id'];

    protected $dates = ['valid_until', 'token_expires_at', 'grace_until', 'updates_until', 'checked_at'];

    /** @return string */
    public function keyPlain()
    {
        if (!$this->license_key) {
            return '';
        }

        try {
            return \Crypt::decryptString($this->license_key);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * @param string $value
     *
     * @return void
     */
    public function setKeyPlain($value)
    {
        $this->license_key = $value === '' || $value === null ? null : \Crypt::encryptString($value);
    }

    /**
     * Nur die ersten acht Zeichen, wie im Portal. Der ganze Schluessel gehoert
     * nirgends hin, wo er mitgelesen werden koennte.
     *
     * @return string
     */
    public function keyPrefix()
    {
        $plain = $this->keyPlain();

        return $plain ? LicenseKey::prefix($plain) : '';
    }

    /**
     * Das Token pruefen - lokal, ohne Netz.
     *
     * @param Installation $installation
     * @param int|null     $now
     *
     * @return LicenseToken
     */
    public function verify(Installation $installation, $now = null)
    {
        return LicenseToken::verify(
            $this->token,
            $this->product_alias,
            $installation->installation_id,
            $now,
            $installation->maxSeenTimestamp()
        );
    }

    /**
     * Die Spiegelwerte aus einem geprueften Token nachziehen.
     *
     * @param LicenseToken $verified
     *
     * @return void
     */
    public function syncFrom(LicenseToken $verified)
    {
        $this->status = $verified->status();
        $this->seats = $verified->get('seats');

        // vut ist das Vertragsende, exp nur die Gültigkeit dieses Tokens.
        // Die beiden zu verwechseln zeigt dem Kunden bei einer Jahreslizenz
        // ein Ablaufdatum in sechs Wochen. Fehlt vut, ist der Vertrag
        // unbefristet - dann bleibt valid_until leer.
        $this->valid_until = $this->toDate($verified->get('vut'));
        $this->token_expires_at = $this->toDate($verified->get('exp'));
        $this->grace_until = $this->toDate($verified->get('grc'));
        $this->updates_until = $this->toDate($verified->get('upd'));
        $this->checked_at = \Carbon\Carbon::now();
    }

    protected function toDate($timestamp)
    {
        return $timestamp ? \Carbon\Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    /**
     * Fuer die Liste: Beschriftung und Bootstrap-Klasse je Zustand.
     *
     * @return array
     */
    public static function statusBadges()
    {
        return [
            LicenseToken::OK                 => ['success', 'Gültig'],
            LicenseToken::GRACE              => ['warning', 'Gnadenfrist'],
            LicenseToken::EXPIRED            => ['danger', 'Abgelaufen'],
            LicenseToken::CLOCK_ROLLBACK     => ['danger', 'Systemzeit zurückgestellt'],
            LicenseToken::BAD_SIGNATURE      => ['danger', 'Signatur ungültig'],
            LicenseToken::UNKNOWN_KEY        => ['danger', 'Unbekannter Schlüssel'],
            LicenseToken::WRONG_AUDIENCE     => ['danger', 'Für ein anderes Modul'],
            LicenseToken::WRONG_INSTALLATION => ['danger', 'Für eine andere Installation'],
            LicenseToken::WRONG_ISSUER       => ['danger', 'Fremder Aussteller'],
            LicenseToken::MALFORMED          => ['danger', 'Unlesbar'],
            'unknown'                        => ['default', 'Nicht geprüft'],
        ];
    }
}
