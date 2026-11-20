<?php

use App\Modules\Parcels\Http\Controllers\OpsParcelController;
use App\Modules\Parcels\Http\Controllers\ParcelConfigurationController;
use App\Modules\Parcels\Http\Controllers\ParcelSupervisionController;
use Illuminate\Support\Facades\Route;

Route::controller(ParcelConfigurationController::class)->group(function (): void {
    Route::get('configuration', 'index')->name('configuration');
    Route::get('settings', 'settings')->name('settings.show');
    Route::put('settings', 'saveSettings')->name('settings.update');
    Route::put('hubs', 'hub')->name('hubs.update');
    Route::post('hubs/{hub:uuid}/archive', 'archive')->name('hubs.archive');
    Route::get('routing/departures', 'routing')->name('routing.departures');
    Route::get('capacity', 'capacityIndex')->name('capacity.index');
    Route::put('corridors', 'corridor')->name('corridors.update');
    Route::post('corridors/{corridor:uuid}/tariffs', 'tariff')->name('tariffs.store');
    Route::put('departures/{departure:uuid}/capacity', 'capacity')->name('capacity.update');
});

Route::controller(ParcelSupervisionController::class)->group(function (): void {
    Route::get('supervision', 'index')->name('supervision.index');
    Route::get('supervision/export', 'export')->name('supervision.export');
});

Route::controller(OpsParcelController::class)->group(function (): void {
    Route::get('/', 'list')->name('index');
    Route::get('indicators', 'indicators')->name('indicators');
    Route::post('/', 'store')->name('store');
    Route::post('quote', 'quote')->name('quote');
    Route::get('deposit-locations', 'depositLocations')->name('deposit-locations');
    Route::get('departures/{departure:uuid}/manifest', 'manifest')->name('departures.manifest');
    Route::get('{parcel:uuid}', 'show')->name('show');
    Route::get('{parcel:uuid}/assignment-options', 'assignmentOptions')->name('assignment-options');
    Route::post('{parcel:uuid}/inspect', 'inspect')->name('inspect');
    Route::post('{parcel:uuid}/cancel', 'cancel')->name('cancel');
    Route::post('{parcel:uuid}/pay', 'pay')->name('pay');
    Route::get('{parcel:uuid}/payment-options', 'paymentOptions')->name('payment-options');
    Route::get('{parcel:uuid}/receipt', 'receipt')->name('receipt');
    Route::post('{parcel:uuid}/fiscalization/retry', 'retryFiscalization')->name('fiscalization.retry');
    Route::post('{parcel:uuid}/ready', 'ready')->name('ready');
    Route::post('{parcel:uuid}/assign', 'assign')->name('assign');
    Route::post('{parcel:uuid}/unload-at-origin', 'correction')->name('unload-at-origin');
    Route::post('{parcel:uuid}/receive', 'receive')->name('receive');
    Route::post('{parcel:uuid}/reissue-code', 'reissue')->name('reissue');
    Route::post('{parcel:uuid}/release', 'release')->middleware('throttle:20,1')->name('release');
    Route::post('{parcel:uuid}/return-charge', 'returnCharge')->name('return-charge');
    Route::get('{parcel:uuid}/documents', 'documents')->name('documents');
    Route::get('{parcel:uuid}/incidents', 'incidents')->name('incidents.index');
    Route::get('{parcel:uuid}/incidents/{incident:uuid}', 'showIncident')->name('incidents.show');
    Route::post('{parcel:uuid}/incidents', 'incident')->name('incidents.store');
    Route::post('{parcel:uuid}/incidents/{incident:uuid}/resolve', 'resolveIncident')->name('incidents.resolve');
    Route::get('{parcel:uuid}/payments', 'payments')->name('payments.index');
    Route::post('{parcel:uuid}/refunds', 'authorizeRefund')->name('refunds.authorize');
    Route::post('{parcel:uuid}/refunds/{refund:uuid}/complete', 'completeRefund')->withoutScopedBindings()->name('refunds.complete');
});
