<?php

namespace Modules\LaStore\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\LaStore\Entities\License;
use Modules\LaStore\Services\LicenseService;
use Modules\LaStore\Services\StoreException;
use Modules\LaStore\Support\LicenseToken;

class LicenseController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $service = new LicenseService();
        $installation = $service->installation();
        $licenses = License::orderBy('product_alias')->get();

        // Fuer die Anzeige jede Lizenz noch einmal lokal pruefen. Das kostet
        // nichts (gemessene 0,06 ms je Pruefung) und zeigt den WIRKLICHEN
        // Zustand statt der gespiegelten Spalten.
        $verified = [];

        foreach ($licenses as $license) {
            $verified[$license->product_alias] = $license->verify($installation);
        }

        return view('lastore::licenses', [
            'licenses'     => $licenses,
            'verified'     => $verified,
            'installation' => $installation,
            'badges'       => License::statusBadges(),
        ]);
    }

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

        return redirect()->back();
    }

    protected function authorizeAdmin()
    {
        if (!auth()->user() || !auth()->user()->isAdmin()) {
            abort(403);
        }
    }
}
