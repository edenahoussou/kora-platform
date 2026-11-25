<?php

use App\Modules\Parcels\Http\Controllers\OpsParcelController;
use App\Modules\Parcels\Http\Controllers\ParcelConfigurationController;
use App\Modules\Parcels\Http\Controllers\ParcelSupervisionController;
use Illuminate\Support\Facades\Route;

Route::controller(ParcelConfigurationController::class)->group(function (): void {
    Route::get('configuration', 'index')->middleware('can:parcels.view')->name('configuration');
    Route::get('settings', 'settings')->middleware('can:parcels.network')->name('settings.show');
    Route::put('settings', 'saveSettings')->middleware(['can:parcels.network', 'can:parcels.configure'])->name('settings.update');
    Route::put('hubs', 'hub')->middleware(['can:parcels.network', 'can:parcels.configure'])->name('hubs.update');
    Route::post('hubs/{hub:uuid}/archive', 'archive')->middleware(['can:parcels.network', 'can:parcels.configure'])->name('hubs.archive');
    Route::get('routing/departures', 'routing')->middleware('can:parcels.view')->name('routing.departures');
    Route::get('capacity', 'capacityIndex')->middleware('can:parcels.network')->name('capacity.index');
    Route::put('corridors', 'corridor')->middleware('can:parcels.configure')->name('corridors.update');
    Route::post('corridors/{corridor:uuid}/tariffs', 'tariff')->middleware('can:parcels.tariffs')->name('tariffs.store');
    Route::put('departures/{departure:uuid}/capacity', 'capacity')->middleware('can:parcels.configure')->name('capacity.update');
});

Route::controller(ParcelSupervisionController::class)->group(function (): void {
    Route::get('supervision', 'index')->middleware('can:parcels.view')->name('supervision.index');
    Route::get('supervision/export', 'export')->middleware('can:parcels.view')->name('supervision.export');
});

Route::controller(OpsParcelController::class)->group(function (): void {
    Route::get('/', 'list')->middleware('can:parcels.view')->name('index');
    Route::get('indicators', 'indicators')->middleware('can:parcels.view')->name('indicators');
    Route::post('/', 'store')->middleware('can:parcels.intake')->name('store');
    Route::post('quote', 'quote')->middleware('can:parcels.intake')->name('quote');
    Route::get('deposit-locations', 'depositLocations')->middleware('can:parcels.intake')->name('deposit-locations');
    Route::get('departures/{departure:uuid}/manifest', 'manifest')->name('departures.manifest');
    Route::get('{parcel:uuid}', 'show')->middleware('can:parcels.view')->name('show');
    Route::get('{parcel:uuid}/tracking', 'tracking')->middleware('can:parcels.view')->name('tracking');
    Route::get('{parcel:uuid}/assignment-options', 'assignmentOptions')->middleware('can:parcels.assign')->name('assignment-options');
    Route::post('{parcel:uuid}/inspect', 'inspect')->middleware('can:parcels.intake')->name('inspect');
    Route::post('{parcel:uuid}/cancel', 'cancel')->middleware('can:parcels.intake')->name('cancel');
    Route::post('{parcel:uuid}/pay', 'pay')->middleware('can:parcels.intake')->name('pay');
    Route::get('{parcel:uuid}/payment-options', 'paymentOptions')->middleware('can:parcels.intake')->name('payment-options');
    Route::get('{parcel:uuid}/receipt', 'receipt')->middleware('can:parcels.intake')->name('receipt');
    Route::post('{parcel:uuid}/fiscalization/retry', 'retryFiscalization')->middleware('can:parcels.finance')->name('fiscalization.retry');
    Route::post('{parcel:uuid}/ready', 'ready')->middleware('can:parcels.intake')->name('ready');
    Route::post('{parcel:uuid}/assign', 'assign')->middleware('can:parcels.assign')->name('assign');
    Route::post('{parcel:uuid}/unload-at-origin', 'correction')->middleware('can:parcels.override')->name('unload-at-origin');
    Route::post('{parcel:uuid}/receive', 'receive')->middleware('can:parcels.handoff')->name('receive');
    Route::post('{parcel:uuid}/reissue-code', 'reissue')->middleware('can:parcels.override')->name('reissue');
    Route::post('{parcel:uuid}/release', 'release')->middleware(['can:parcels.handoff', 'throttle:20,1'])->name('release');
    Route::post('{parcel:uuid}/return-charge', 'returnCharge')->middleware('can:parcels.intake')->name('return-charge');
    Route::get('{parcel:uuid}/documents', 'documents')->middleware('can:parcels.view')->name('documents');
    Route::get('{parcel:uuid}/incidents', 'incidents')->middleware('can:parcels.view')->name('incidents.index');
    Route::get('{parcel:uuid}/incidents/{incident:uuid}', 'showIncident')->middleware('can:parcels.view')->name('incidents.show');
    Route::post('{parcel:uuid}/incidents', 'incident')->name('incidents.store');
    Route::post('{parcel:uuid}/incidents/{incident:uuid}/resolve', 'resolveIncident')->middleware('can:parcels.override')->name('incidents.resolve');
    Route::get('{parcel:uuid}/payments', 'payments')->middleware('can:parcels.finance')->name('payments.index');
    Route::post('{parcel:uuid}/refunds', 'authorizeRefund')->middleware('can:parcels.finance')->name('refunds.authorize');
    Route::post('{parcel:uuid}/refunds/{refund:uuid}/complete', 'completeRefund')->middleware('can:parcels.finance')->withoutScopedBindings()->name('refunds.complete');
});
