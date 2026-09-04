<?php

namespace Modules\LaStore\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\LaStore\Entities\License;
use Modules\LaStore\Services\LicenseService;
use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\SelfUpdater;
use Modules\LaStore\Support\LicenseToken;

class LicenseController extends Controller
{
    public function activate(Request $request)
    {
        $this->authorizeAdmin();

        $alias = (string) $request->input('product_alias');
        $key = (string) $request->input('license_key');

        try {
            $license = (new LicenseService())->activate($key, $alias);

            \Session::flash('flash_success_floating', __('Lizenz für :module aktiviert.', ['module' => $alias]));

            if ($license->status === LicenseToken::GRACE) {
                \Session::flash('flash_warning_floating', __('Die Lizenz läuft in der Gnadenfrist. Bitte im Kundenportal verlängern.'));
            }
        } catch (StoreException $e) {
            \Session::flash('flash_error_floating', $e->getMessage());
        }

        return redirect()->back();
    }

    public function importOffline(Request $request)
    {
        $this->authorizeAdmin();

        $file = $request->file('license_file');

        if (!$file || !$file->isValid()) {
            \Session::flash('flash_error_floating', __('Es wurde keine Datei hochgeladen.'));

            return redirect()->back();
        }

        // Eine Lizenzdatei ist ein Token, also wenige hundert Zeichen. Alles
        // Groessere ist keine - und soll gar nicht erst gelesen werden.
        if ($file->getSize() > 8192) {
            \Session::flash('flash_error_floating', __('Diese Datei ist zu gross für eine Lizenzdatei.'));

            return redirect()->back();
        }

        try {
            $license = (new LicenseService())->importOfflineFile(file_get_contents($file->getRealPath()));

            \Session::flash('flash_success_floating', __('Offline-Lizenz für :module übernommen.', ['module' => $license->product_alias]));
        } catch (StoreException $e) {
            \Session::flash('flash_error_floating', $e->getMessage());
        }

        return redirect()->back();
    }

    public function refresh()
    {
        $this->authorizeAdmin();

        try {
            (new LicenseService())->refreshAll();

            \Session::flash('flash_success_floating', __('Lizenzen geprüft.'));
        } catch (StoreException $e) {
            \Session::flash('flash_error_floating', $e->getMessage());
        }

        /*
         * Bei dieser Gelegenheit auch nach LaShop selbst sehen -- und die
         * Tagessperre dabei zurueckstellen.
         *
         * Der Grund: die Seite sieht von sich aus hoechstens einmal am Tag
         * nach. Wer einen Knopf mit der Aufschrift "jetzt pruefen" drueckt,
         * darf aber keine tagesalte Antwort bekommen. Genau das ist am
         * 04.09.2026 aufgefallen -- mehrere Fassungen waren veroeffentlicht,
         * die Seite zeigte nichts, weil ihr Blick am Vortag um 18:02 gelaufen
         * war.
         *
         * Still im Fehlerfall: die Lizenzpruefung ist die Hauptsache dieses
         * Knopfes, und ein Shop, der zum Client-Modul nichts sagt, darf sie
         * nicht mit einer roten Meldung ueberdecken.
         */
        try {
            SelfUpdater::pruefen();
            \Option::set('lastore.selbst_geprueft_am', time());
        } catch (\Exception $e) {
            // Still.
        }

        return redirect()->back();
    }

    protected function authorizeAdmin()
    {
        if (!auth()->user() || !auth()->user()->isAdmin()) {
            abort(403);
        }
    }
}
