<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace'  => 'Modules\LaStore\Http\Controllers',
    'middleware' => ['web', 'auth'],
    'prefix'     => 'store',
    'as'         => 'lastore.',
], function () {
    Route::get('/', 'StoreController@index')->name('index');
    Route::get('lizenzen', 'LicenseController@index')->name('licenses');
    Route::post('lizenzen/aktivieren', 'LicenseController@activate')->name('licenses.activate');
    Route::post('lizenzen/offline', 'LicenseController@importOffline')->name('licenses.offline');
    Route::post('lizenzen/pruefen', 'LicenseController@refresh')->name('licenses.refresh');
    Route::get('produkt/{alias}', 'StoreController@show')->name('product');
});
