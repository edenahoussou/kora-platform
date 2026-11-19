<?php

use App\Modules\Parcels\Http\Controllers\OpsParcelController;
use App\Modules\Parcels\Http\Controllers\ParcelConfigurationController;
use Illuminate\Support\Facades\Route;

Route::controller(ParcelConfigurationController::class)->group(function (): void {
    Route::get('configuration', 'index')->name('configuration');
    Route::put('hubs', 'hub')->name('hubs.update');
    Route::post('hubs/{hub:uuid}/archive', 'archive')->name('hubs.archive');
    Route::get('routing/departures', 'routing')->name('routing.departures');
    Route::get('capacity', 'capacityIndex')->name('capacity.index');
    Route::put('corridors', 'corridor')->name('corridors.update');
    Route::post('corridors/{corridor:uuid}/tariffs', 'tariff')->name('tariffs.store');
    Route::put('departures/{departure:uuid}/capacity', 'capacity')->name('capacity.update');
});

Route::controller(OpsParcelController::class)->group(function (): void {
    Route::get('/', 'list')->name('index');
    Route::get('indicators', 'indicators')->name('indicators');
    Route::post('/', 'store')->name('store');
    Route::get('deposit-locations', 'depositLocations')->name('deposit-locations');
    Route::get('{parcel:uuid}', 'show')->name('show');
    Route::post('{parcel:uuid}/inspect', 'inspect')->name('inspect');
    Route::post('{parcel:uuid}/cancel', 'cancel')->name('cancel');
    Route::post('{parcel:uuid}/pay', 'pay')->name('pay');
    Route::post('{parcel:uuid}/ready', 'ready')->name('ready');
    Route::post('{parcel:uuid}/assign', 'assign')->name('assign');
    Route::post('{parcel:uuid}/unload-at-origin', 'correction')->name('unload-at-origin');
    Route::post('{parcel:uuid}/receive', 'receive')->name('receive');
    Route::post('{parcel:uuid}/reissue-code', 'reissue')->name('reissue');
    Route::post('{parcel:uuid}/release', 'release')->middleware('throttle:20,1')->name('release');
    Route::post('{parcel:uuid}/return-charge', 'returnCharge')->name('return-charge');
    Route::get('{parcel:uuid}/documents', 'documents')->name('documents');
    Route::post('{parcel:uuid}/incidents', 'incident')->name('incidents.store');
    Route::post('{parcel:uuid}/incidents/{incident:uuid}/resolve', 'resolveIncident')->name('incidents.resolve');
    Route::get('{parcel:uuid}/payments', 'payments')->name('payments.index');
    Route::post('{parcel:uuid}/refunds', 'authorizeRefund')->name('refunds.authorize');
    Route::post('{parcel:uuid}/refunds/{refund:uuid}/complete', 'completeRefund')->withoutScopedBindings()->name('refunds.complete');
});
