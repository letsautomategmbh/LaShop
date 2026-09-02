<?php

namespace Modules\LaStore\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\LaStore\Entities\CatalogEntry;
use Modules\LaStore\Entities\Installation;
use Modules\LaStore\Entities\License;
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
            'credentials'  => InstalledModules::withCredentials(),
            'labels'       => InstalledModules::stateLabels(),
            'installation' => $installation,
            'error'        => $error,
            'transport'    => config('lastore.transport'),
        ]);
    }

    public function show($alias)
    {
        $this->authorizeAdmin();

        $entry = CatalogEntry::where('alias', $alias)->first();

        if (!$entry) {
            abort(404);
        }

        return view('lastore::product', [
            'product'      => $entry->data(),
            'license'      => License::where('product_alias', $alias)->first(),
            'installation' => Installation::current(),
        ]);
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
