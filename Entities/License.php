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
    /**
     * Das Datum, das den Kunden interessiert - und ob es vorbei ist.
     *
     * Beim Abonnement ist es `valid_until`: danach endet die Nutzung. Beim
     * Einmalkauf ist es `updates_until`: das Modul laeuft weiter, aber
     * Updates und Support enden. Zwei verschiedene Bedeutungen, deshalb gibt
     * diese Methode auch die Art zurueck und nicht bloss ein Datum - der
     * Aufrufer soll nicht raten muessen, was er da anzeigt.
     *
     * **Woran die Art erkannt wird.** An `license_type` aus dem Token, nicht
     * daran, ob `valid_until` gesetzt ist. Der frühere Schluss war falsch:
     * der Server setzt bei einem Einmalkauf **beide** Datumsfelder, in der
     * Entwicklungsumgebung sogar auf denselben Wert. Damit galt jeder
     * Einmalkauf als Abonnement, und auf der Karte stand „Nutzbar bis
     * 30.08.2027" -- eine Auskunft, die das Gegenteil des Richtigen sagt:
     * das Modul hört an dem Datum nicht auf zu laufen.
     *
     * Fehlt `license_type` (Zeilen aus der Zeit vor dieser Spalte, bis zum
     * naechsten taeglichen Abgleich), greift der alte Schluss. Lieber die
     * bisherige Beschriftung als eine leere Zeile.
     *
     * Kein Text hier: die Beschriftung gehoert in die Ansicht, sonst stehen
     * Uebersetzungen im Modell.
     *
     * @return array{art: string, datum: ?\Carbon\Carbon, abgelaufen: bool}
     */
    public function ablaufDatum()
    {
        /*
         * Eine Testphase ist eine eigene Art, obwohl sie sich wie ein Abo
         * verhaelt (valid_until begrenzt die Nutzung). Der Unterschied ist
         * nicht technisch, sondern der wichtigste, den der Kunde kennen
         * muss: er hat nichts gekauft. "Nutzbar bis 18.09." liest sich wie
         * ein Vertrag; "Testphase bis 18.09." sagt, dass danach eine
         * Entscheidung faellig ist.
         */
        $testphase = $this->license_type === 'trial';

        $istAbo = $this->license_type !== null
            ? $this->license_type !== 'one_time'
            : $this->valid_until !== null;

        $datum = $istAbo ? $this->valid_until : $this->updates_until;

        return array(
            'art'        => $testphase ? 'testphase' : ($istAbo ? 'nutzung' : 'updates'),
            'datum'      => $datum,
            'abgelaufen' => $datum !== null && $datum->isPast(),
        );
    }

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

        // Die Kaufart stand im Token seit je ('typ' bei TokenIssuer) und
        // wurde hier verworfen. Ohne sie musste ablaufDatum() raten, und es
        // riet falsch -- siehe dort.
        $this->license_type = $verified->get('typ');

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
