<?php

namespace Modules\LaStore\Services;

use Modules\LaStore\Entities\CatalogEntry;
use Modules\LaStore\Entities\Installation;
use Modules\LaStore\Entities\License;
use Modules\LaStore\Support\LicenseKey;
use Modules\LaStore\Support\LicenseToken;
use Modules\LaStore\Support\SecretString;

/**
 * Registrierung, Aktivierung und Nachfuehrung der Lizenzen.
 *
 * Eine Regel zieht sich durch alles hier: ein Token wird NIE gespeichert,
 * bevor es geprueft ist. Ein Server, der Unsinn liefert - oder etwas, das
 * sich als Server ausgibt - darf keinen Zustand in dieser Installation
 * hinterlassen.
 */
class LicenseService
{
    /** @var StoreClient */
    protected $client;

    /** @var Installation */
    protected $installation;

    public function __construct(?StoreClient $client = null)
    {
        $this->client = $client === null ? new StoreClient() : $client;
        $this->installation = $this->client->installation();
    }

    /**
     * Die Installation beim Shop anmelden. Einmalig und still.
     *
     * @return Installation
     */
    public function register()
    {
        if ($this->installation->isRegistered()) {
            return $this->installation;
        }

        $response = $this->client->registerInstallation($this->facts());

        if (empty($response['installation_id'])) {
            throw new StoreException(__('Der Shop hat keine Installations-Kennung geliefert.'), 'bad_response');
        }

        $this->installation->installation_id = $response['installation_id'];
        $this->installation->setSecretPlain(isset($response['secret']) ? $response['secret'] : '');
        $this->installation->heartbeat_hours = isset($response['heartbeat_hours']) ? (int) $response['heartbeat_hours'] : 24;
        $this->installation->registered_at = \Carbon\Carbon::now();
        $this->installation->save();

        $this->installation->touchMaxSeen();

        return $this->installation;
    }

    /**
     * Einen Lizenzschluessel an diese Installation binden.
     *
     * @param string $rawKey
     * @param string $productAlias
     *
     * @return License
     */
    public function activate($rawKey, $productAlias)
    {
        // Sofort einpacken: ab hier steht der Schluessel in keinem Stacktrace
        // mehr, sondern nur noch der Klassenname des Traegers.
        $secret = SecretString::wrap($rawKey);
        $rawKey = null;

        // Zuerst lokal. Ein Tippfehler soll keinen der zehn Serverversuche je
        // Stunde verbrauchen - und die Fehlermeldung ist so auch besser.
        if (!LicenseKey::isValid($secret->reveal())) {
            throw new StoreException(
                __('Dieser Lizenzschlüssel ist unvollständig oder enthält einen Tippfehler.'),
                'malformed_key'
            );
        }

        $this->register();

        $key = LicenseKey::format($secret->reveal());
        $response = $this->client->activate(new SecretString($key), $productAlias);

        if (empty($response['token'])) {
            throw new StoreException(__('Der Shop hat kein Lizenz-Token geliefert.'), 'bad_response');
        }

        $verified = $this->verifyOrFail($response['token'], $productAlias);

        $license = License::firstOrNew(['product_alias' => $productAlias]);
        $license->setKeyPlain($key);
        $license->token = $response['token'];
        $license->last_error = null;
        $license->syncFrom($verified);
        $license->save();

        $this->installation->touchMaxSeen();

        return $license;
    }

    /**
     * Alle bekannten Lizenzen in einem Aufruf nachfuehren.
     *
     * @return array Alias => Zustand
     */
    public function refreshAll()
    {
        $licenses = License::all()->keyBy('product_alias');

        // Gefragt wird nicht nur nach den bekannten Lizenzen, sondern nach
        // allen installierten Modulen, die es im Katalog gibt.
        //
        // Der Grund ist ein Fall, der in der Praxis eintritt: der Schlüssel
        // lässt sich nicht wieder anzeigen, und nach dem Rückspielen einer
        // Sicherung oder einer Neuinstallation des Store-Moduls fehlt die
        // lokale Zeile. Der Sitz auf dem Server gehört dieser Installation
        // aber weiterhin - also kann sie ihre Lizenzen von dort
        // zurückbekommen, ohne den Schlüssel je wieder zu brauchen.
        $aliases = $licenses->keys()->all();

        // Ueber getAlias() suchen und NICHT ueber \Module::find(): das sucht
        // nach dem Modulnamen, nicht nach dem Alias. "bexio" wird dort nur
        // gefunden, weil Name und Alias zufaellig gleich sind - "invoicing"
        // heisst intern "Activities" und faellt still durch.
        $installed = array();

        foreach (\Module::all() as $module) {
            $installed[$module->getAlias()] = true;
        }

        foreach (CatalogEntry::pluck('alias') as $alias) {
            if (!in_array($alias, $aliases, true) && isset($installed[$alias])) {
                $aliases[] = $alias;
            }
        }

        if (!$aliases) {
            return [];
        }

        $response = $this->client->checkBatch($aliases, $this->userCount());

        $tokens = isset($response['tokens']) && is_array($response['tokens']) ? $response['tokens'] : [];
        $statuses = isset($response['statuses']) && is_array($response['statuses']) ? $response['statuses'] : [];
        $result = [];

        foreach ($aliases as $alias) {
            $license = $licenses->get($alias);

            if (!$license) {
                // Nur anlegen, wenn der Server wirklich ein Token liefert -
                // sonst entstünde für jedes installierte Modul eine leere
                // Zeile.
                if (!isset($tokens[$alias])) {
                    continue;
                }

                $license = License::firstOrNew(['product_alias' => $alias]);
            }

            if (isset($tokens[$alias])) {
                try {
                    $verified = $this->verifyOrFail($tokens[$alias], $alias);
                    $license->token = $tokens[$alias];
                    $license->last_error = null;
                    $license->syncFrom($verified);
                } catch (StoreException $e) {
                    // Ein unbrauchbares neues Token ersetzt kein brauchbares
                    // altes. Sonst nimmt ein Serverfehler dem Kunden eine
                    // Lizenz weg, die er bezahlt hat.
                    $license->last_error = $e->getMessage();

                    /*
                     * Aber: eine Zeile, die es vorher NICHT gab, entsteht auch
                     * nicht. Sonst legt ein Token, dessen Signatur nicht
                     * stimmt, eine Lizenzzeile an - mit dem Vorgabestatus
                     * "unknown". Und "unknown" galt in InstalledModules als
                     * gueltig, weil dort nur das klare Nein zaehlte. Ergebnis:
                     * gruenes "Lizenziert" fuer ein Modul, fuer das nie ein
                     * gueltiges Token da war. Genau so gesehen am 02.09.2026
                     * mit den abgelegten Antworten, deren Tokens auf "la1"
                     * lauten - eine Kennung, die dieses Modul nicht kennt.
                     *
                     * Ein Fehlschlag der Signaturpruefung darf keine Spur von
                     * Berechtigung hinterlassen.
                     */
                    if (!$license->exists) {
                        continue;
                    }
                }
            } elseif (isset($statuses[$alias])) {
                $license->status = (string) $statuses[$alias];
                $license->checked_at = \Carbon\Carbon::now();
            }

            $license->save();
            $result[$alias] = $license->status;
        }

        $this->installation->touchMaxSeen();

        return $result;
    }

    /**
     * Eine hochgeladene Offline-Lizenzdatei uebernehmen.
     *
     * @param string $contents
     *
     * @return License
     */
    public function importOfflineFile($contents)
    {
        $token = trim((string) $contents);
        $claims = LicenseToken::peek($token);

        if (empty($claims['aud'])) {
            throw new StoreException(__('Diese Datei ist keine Lizenzdatei.'), 'malformed');
        }

        $alias = (string) $claims['aud'];
        $verified = $this->verifyOrFail($token, $alias);

        $license = License::firstOrNew(['product_alias' => $alias]);
        $license->token = $token;
        $license->last_error = null;
        $license->syncFrom($verified);
        $license->save();

        // Eine Offline-Datei kommt per Hand; ab jetzt keine Verbindungen mehr.
        if ($verified->isOffline()) {
            $this->installation->mode = Installation::MODE_OFFLINE;
            $this->installation->save();
        }

        return $license;
    }

    /**
     * Pruefen, und bei jedem anderen Ergebnis als brauchbar abbrechen.
     *
     * @param string $token
     * @param string $alias
     *
     * @return LicenseToken
     */
    protected function verifyOrFail($token, $alias)
    {
        $verified = LicenseToken::verify(
            $token,
            $alias,
            $this->installation->installation_id,
            null,
            $this->installation->maxSeenTimestamp()
        );

        if (!$verified->isUsable()) {
            throw new StoreException($this->explain($verified->status()), $verified->status());
        }

        return $verified;
    }

    /**
     * @param string $status
     *
     * @return string
     */
    protected function explain($status)
    {
        switch ($status) {
            case LicenseToken::EXPIRED:
                return __('Diese Lizenz ist abgelaufen. Im Kundenportal lässt sie sich verlängern.');
            case LicenseToken::BAD_SIGNATURE:
                return __('Die Signatur dieser Lizenz stimmt nicht. Bitte meldet euch bei uns, bevor ihr etwas installiert.');
            case LicenseToken::UNKNOWN_KEY:
                return __('Diese Lizenz wurde mit einem Schlüssel signiert, den dieses Modul nicht kennt. Meist hilft ein Update von LaShop.');
            case LicenseToken::WRONG_AUDIENCE:
                return __('Diese Lizenz gehört zu einem anderen Modul.');
            case LicenseToken::WRONG_INSTALLATION:
                return __('Diese Lizenz ist einer anderen Installation zugewiesen. Im Kundenportal lässt sie sich freigeben und neu vergeben.');
            case LicenseToken::CLOCK_ROLLBACK:
                return __('Die Systemzeit dieses Servers liegt vor einem bereits gesehenen Zeitpunkt. Bitte die Uhr richtig stellen.');
            case LicenseToken::WRONG_ISSUER:
                return __('Diese Lizenz stammt nicht von let’s automate.');
        }

        return __('Diese Lizenz konnte nicht geprüft werden.');
    }

    /**
     * Was der Shop ueber diese Installation erfaehrt. Mehr nicht - und die
     * Nutzerzahl ist Vertragsgrundlage, nicht Telemetrie.
     *
     * @return array
     */
    public function facts()
    {
        return [
            'domain'      => parse_url(config('app.url'), PHP_URL_HOST),
            'app_version' => config('app.version'),
            'php_version' => PHP_VERSION,
            'user_count'  => $this->userCount(),
            'label'       => (string) $this->installation->label,
        ];
    }

    /** @return int */
    public function userCount()
    {
        try {
            return (int) \App\User::where('status', \App\User::STATUS_ACTIVE)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** @return array */
    public function installedModuleVersions()
    {
        $versions = [];

        try {
            foreach (\Module::all() as $module) {
                $versions[$module->getAlias()] = (string) $module->get('version');
            }
        } catch (\Exception $e) {
            // Ohne Modulliste ist der Heartbeat duenner, aber nicht kaputt.
        }

        return $versions;
    }

    /** @return Installation */
    public function installation()
    {
        return $this->installation;
    }

    /** @return StoreClient */
    public function client()
    {
        return $this->client;
    }
}
