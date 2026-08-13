<?php

use App\Modules\BusNetwork\Http\Controllers\BusCatalogController;
use App\Modules\Departures\Http\Controllers\BusDepartureController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'kora-platform',
            'scope' => 'mvp-bus',
        ]);
    })->name('health');

    Route::prefix('bus')
        ->name('bus.')
        ->middleware('throttle:bus-search')
        ->group(function (): void {
            Route::get('cities', [BusCatalogController::class, 'cities'])->name('cities.index');
            Route::get('lines', [BusCatalogController::class, 'lines'])->name('lines.index');
            Route::get('lines/{line:uuid}', [BusCatalogController::class, 'line'])->name('lines.show');
            Route::get('departures', [BusDepartureController::class, 'index'])->name('departures.index');
            Route::get('departures/{departure:uuid}', [BusDepartureController::class, 'show'])->name('departures.show');
            Route::get('departures/{departure:uuid}/seats', [BusDepartureController::class, 'seats'])->name('departures.seats.index');
        });
});
