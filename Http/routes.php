<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace'  => 'Modules\LaStore\Http\Controllers',
    'middleware' => ['web', 'auth'],
    'prefix'     => 'store',
    'as'         => 'lastore.',
], function () {
    Route::get('/', 'StoreController@index')->name('index');
    // Die Lizenzseite gibt es nicht mehr: Lizenzen verwaltet der Kunde im
    // Portal. Hier bleibt nur, was das Portal nicht kann - die
    // Offline-Lizenzdatei auf DIESEN Server legen und die Prüfung anstossen.
    // Ein Druck: Schlüssel prüfen, installieren, registrieren.
    Route::post('installieren', 'StoreController@install')->name('install');
    Route::post('autopilot', 'StoreController@autopilot')->name('autopilot');
    // LaShop selbst. Eigener Weg, weil er das Modul austauscht, in dem er
    // laeuft -- nach dem Tausch wird nichts mehr nachgeladen.
    Route::post('selbst-aktualisieren', 'StoreController@selfUpdate')->name('self_update');
    Route::post('lizenzen/aktivieren', 'LicenseController@activate')->name('licenses.activate');
    Route::post('lizenzen/offline', 'LicenseController@importOffline')->name('licenses.offline');
    Route::post('lizenzen/pruefen', 'LicenseController@refresh')->name('licenses.refresh');
    // Keine Produktseite im Modul: die Details stehen im LaShop, und zwei
    // Fassungen derselben Beschreibung sind zwei Wahrheiten.
});
