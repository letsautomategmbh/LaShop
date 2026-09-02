<?php

namespace Modules\LaStore\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\LaStore\Entities\CatalogEntry;
use Modules\LaStore\Entities\Installation;
use Modules\LaStore\Entities\License;
use Illuminate\Http\Request;
use Modules\LaStore\Services\PackageInstaller;
use Modules\LaStore\Services\LicenseService;
use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\InstalledModules;

class StoreController extends Controller
{
    public function __construct()
    {
        $this->middleware('roles')->only([]);
    }

    public function index()
    {
        $this->authorizeAdmin();

        $service = new LicenseService();
        $installation = $service->installation();
        $error = null;

        // Den Katalog frisch holen, wenn er aelter als 15 Minuten ist -
        // dieselbe Frist, die FreeScout fuer seine eigene Modulliste
        // verwendet. Scheitert das, wird der zuletzt gesehene gezeigt: ein
        // leerer Katalog sieht aus wie ein Fehler, und der Kunde ruft an.
        if ($this->catalogIsStale($installation)) {
            try {
                CatalogEntry::replaceAll($service->client()->catalog());
                $installation->last_catalog_sync_at = \Carbon\Carbon::now();
                $installation->save();
            } catch (StoreException $e) {
                $error = $e->getMessage();
            }
        }

        return view('lastore::index', [
            'inventory'    => InstalledModules::inventory(),
            'adoptable'    => InstalledModules::adoptable(),
            /*
             * `credentials` wird der Ansicht nicht mehr uebergeben: die
             * Warnung ueber Zugangsdaten in fremden module.json ist aus der
             * Kundenansicht entfernt -- aendern kann das nur, wer die Module
             * baut. InstalledModules::withCredentials() bleibt bestehen und
             * ist der Einstieg, wenn die Pruefung im Betrieb gebraucht wird.
             */
            'labels'       => InstalledModules::stateLabels(),
            'installation' => $installation,
            'error'        => $error,
            'transport'    => config('lastore.transport'),
        ]);
    }

    /**
     * Autopilot ein- oder ausschalten.
     *
     * Eingeschaltet holt der naechtliche Lauf Updates fuer ALLE lizenzierten
     * Module und installiert sie - Signaturpruefung inbegriffen, denselben
     * Weg wie der Knopf von Hand.
     *
     * Standard ist AUS, und das ist Absicht: ein Update installiert Code auf
     * einer Anlage, die Tickets bearbeitet. Wer das ueber Nacht ohne Zuschauer
     * will, soll es sagen.
     */
    public function autopilot(Request $request)
    {
        $this->authorizeAdmin();

        $an = (bool) $request->input('autopilot');
        \Option::set('lastore.autopilot', $an ? 1 : 0);

        \Session::flash('flash_success_floating', $an
            ? __('Autopilot ist an. Updates werden nachts geholt und installiert.')
            : __('Autopilot ist aus. Updates bleiben Handarbeit.'));

        return redirect()->back();
    }

    /**
     * Schluessel eintragen, pruefen, installieren, registrieren - ein Druck.
     *
     * Genau der Ablauf, den FreeScout fuer seine eigenen Module hat. Vorher
     * war es bei uns zweigeteilt: die Lizenz liess sich hier uebernehmen, das
     * Modul aber nur ueber die Kommandozeile installieren (lastore:update).
     * Fuer einen Kunden, der ein Modul gekauft hat, war das ein Schritt zu
     * viel - und einer, den er ohne Serverzugang gar nicht gehen kann.
     *
     * FreeScouts eigener Knopf laesst sich dafuer NICHT benutzen: der Host
     * seiner Schnittstelle steht fest im Kern (config/app.php,
     * 'freescout_api' => 'https://api.freescout.net/wp-json/'), und der Kunde
     * fährt Standard-FreeScout. Die Schrittfolge ist aber dieselbe, mit einem
     * Unterschied: FreeScout vertraut beim Download auf TLS, wir pruefen
     * zusaetzlich die Signatur des Pakets.
     */
    public function install(Request $request)
    {
        $this->authorizeAdmin();

        $alias = strtolower(trim((string) $request->input('product_alias')));
        $key = (string) $request->input('license_key');

        if ($alias === '') {
            \Session::flash('flash_error_floating', __('Es fehlt der Modul-Alias.'));

            return redirect()->back();
        }

        $service = new LicenseService();
        $vorhanden = License::where('product_alias', $alias)->first();

        /*
         * Der Schluessel ist NUR beim ersten Mal noetig.
         *
         * Beim Aktualisieren liegt die Lizenz lokal schon vor, und der Client
         * weist sich mit ihrem Token aus - nicht mit dem Schluessel. Den hier
         * trotzdem zu verlangen war ein Fehler: FreeScouts getLicense() gibt
         * fuer unsere Module einen LEEREN Text zurueck, mein Aktualisieren-
         * Knopf haette also immer mit "Es fehlt der Lizenzschluessel"
         * abgebrochen. Aufgefallen erst beim Rendern der echten Seite.
         */
        if (trim($key) === '' && !$vorhanden) {
            \Session::flash('flash_error_floating', __('Es fehlt der Lizenzschlüssel.'));

            return redirect()->back();
        }

        // 1. Die Lizenz - falls ein Schluessel dabei ist. Schlaegt sie fehl,
        //    wird NICHTS heruntergeladen: ein Paket zu holen, das man nicht
        //    benutzen darf, hilft niemandem.
        $license = $vorhanden;

        if (trim($key) !== '') {
            try {
                $license = $service->activate($key, $alias);
            } catch (StoreException $e) {
                \Session::flash('flash_error_floating', $e->getMessage());

                return redirect()->back();
            }
        }

        // 2. Herunterladen, Signatur pruefen, entpacken.
        $installer = new PackageInstaller($service->client());

        try {
            $meldung = $installer->available($alias);
            $zip = $installer->fetchVerified($meldung);
            $installer->install($zip, $alias);
        } catch (StoreException $e) {
            /*
             * Die Lizenz bleibt aktiviert. Das ist gewollt: sie ist
             * bezahlt und gehoert dem Kunden, auch wenn der Download
             * gerade scheitert. Er sieht das Modul danach unter "Zu
             * uebernehmen" und kann es erneut versuchen.
             */
            \Session::flash('flash_error_floating', __('Lizenz übernommen, aber das Installieren ist gescheitert: :fehler', array('fehler' => $e->getMessage())));

            return redirect()->back();
        }

        // 3. Bei FreeScout anmelden - dieselben Aufrufe, die sein eigener
        //    Knopf macht. Ohne sie liegt das Modul nur im Verzeichnis: der
        //    Kern kennt es nicht, die Wanderungen laufen nicht, und die
        //    oeffentlichen Dateien fehlen.
        \Module::clearCache();

        if (trim($key) !== '') {
            \App\Module::activateLicense($alias, $key);
        }

        \App\Module::setActive($alias, true);
        \Artisan::call('freescout:module-install', array('module_alias' => $alias));
        \Artisan::call('freescout:clear-cache');

        \Session::flash('flash_success_floating', __(':modul :version installiert und lizenziert.', array(
            'modul'   => $alias,
            'version' => isset($meldung['version']) ? $meldung['version'] : '',
        )));

        if ($license && $license->status === \Modules\LaStore\Support\LicenseToken::GRACE) {
            \Session::flash('flash_warning_floating', __('Die Lizenz läuft in der Gnadenfrist. Bitte im Kundenportal verlängern.'));
        }

        return redirect()->back();
    }


    protected function catalogIsStale(Installation $installation)
    {
        if ($installation->isOffline()) {
            return false;
        }

        if (!$installation->last_catalog_sync_at) {
            return true;
        }

        return $installation->last_catalog_sync_at->diffInMinutes(\Carbon\Carbon::now()) >= 15;
    }

    protected function authorizeAdmin()
    {
        if (!auth()->user() || !auth()->user()->isAdmin()) {
            abort(403);
        }
    }
}
