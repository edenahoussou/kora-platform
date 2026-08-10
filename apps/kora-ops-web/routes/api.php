<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'kora-platform',
            'scope' => 'mvp-bus',
        ]);
    })->name('health');
});
