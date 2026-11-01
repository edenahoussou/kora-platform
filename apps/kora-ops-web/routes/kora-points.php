<?php

use App\Modules\KoraPoints\Http\Controllers\KoraPartnerDocumentController;
use App\Modules\KoraPoints\Http\Controllers\KoraPointAgentController;
use App\Modules\KoraPoints\Http\Controllers\KoraPointController;
use App\Modules\KoraPoints\Http\Controllers\KoraPointFinanceController;
use App\Modules\KoraPoints\Http\Controllers\KoraPointOrganizationController;
use App\Modules\KoraPoints\Http\Controllers\KoraPointRefundController;
use App\Modules\KoraPoints\Http\Controllers\KoraPointRemittanceController;
use App\Modules\KoraPoints\Http\Controllers\KoraPointSaleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('kora-points', [KoraPointController::class, 'index'])->name('kora-points');
    Route::get('commissions', [KoraPointController::class, 'commissions'])->name('commissions');
    Route::get('settlements', [KoraPointRemittanceController::class, 'index'])->name('settlements');

    Route::prefix('kora-point')->name('kora-point.')->group(function (): void {
        Route::get('/', [KoraPointAgentController::class, 'dashboard'])->name('dashboard');
        Route::get('sale', [KoraPointSaleController::class, 'index'])->name('sale');
        Route::post('sale/quote', [KoraPointSaleController::class, 'quote'])->name('sale.quote');
        Route::post('sale/seats', [KoraPointSaleController::class, 'seats'])->name('sale.seats');
        Route::post('sale/hold', [KoraPointSaleController::class, 'hold'])->name('sale.hold');
        Route::post('sale/confirm', [KoraPointSaleController::class, 'confirm'])->name('sale.confirm');
        Route::post('sale/{reservation:uuid}/release', [KoraPointSaleController::class, 'release'])->name('sale.release');
        Route::post('sale/{reservation:uuid}/passengers', [KoraPointSaleController::class, 'storePassengers'])->name('sale.passengers');
        Route::post('sale/{reservation:uuid}/payment', [KoraPointSaleController::class, 'storePayment'])->name('sale.payment');
        Route::post('sale/{reservation:uuid}/tickets/retry', [KoraPointSaleController::class, 'retryTicketIssuance'])->name('sale.tickets.retry');
        Route::get('sale/{any}', function () {
            session()->reflash();

            return to_route('kora-point.sale');
        })->where('any', '.+')->name('sale.fallback');
        Route::get('cash', [KoraPointAgentController::class, 'cash'])->name('cash');
        Route::post('cash/open', [KoraPointAgentController::class, 'openCash'])->name('cash.open');
        Route::post('cash/close', [KoraPointAgentController::class, 'closeCash'])->name('cash.close');
        Route::get('tickets', [KoraPointAgentController::class, 'tickets'])->name('tickets');
        Route::get('profile', [KoraPointAgentController::class, 'profile'])->name('profile');
        Route::get('commissions', [KoraPointAgentController::class, 'commissions'])->name('commissions');
        Route::get('settlements', [KoraPointAgentController::class, 'settlements'])->name('settlements');
        Route::get('settlements/preview', [KoraPointAgentController::class, 'settlementPreview'])->name('settlements.preview');
        Route::post('settlements/declare', [KoraPointAgentController::class, 'declareSettlement'])->name('settlements.declare');
        Route::get('refunds', [KoraPointAgentController::class, 'refunds'])->name('refunds');
    });

    Route::prefix('kora-points')->name('kora-points.')->group(function (): void {
        Route::get('finance', [KoraPointFinanceController::class, 'index'])->name('finance');
        Route::post('/', [KoraPointController::class, 'store'])->name('store');
        Route::patch('defaults', [KoraPointController::class, 'defaults'])->name('defaults');
        Route::prefix('organizations')->name('organizations.')->group(function (): void {
            Route::get('/', [KoraPointOrganizationController::class, 'index'])->name('index');
            Route::post('/', [KoraPointOrganizationController::class, 'store'])->name('store');
            Route::get('{organization:uuid}', [KoraPointOrganizationController::class, 'show'])->name('show');
            Route::put('{organization:uuid}', [KoraPointOrganizationController::class, 'update'])->name('update');
            Route::patch('{organization:uuid}/terms', [KoraPointOrganizationController::class, 'terms'])->name('terms');
            Route::patch('{organization:uuid}/status', [KoraPointOrganizationController::class, 'status'])->name('status');
            Route::post('{organization:uuid}/users', [KoraPointOrganizationController::class, 'assign'])->name('users');
            Route::post('{organization:uuid}/documents', [KoraPartnerDocumentController::class, 'organization'])->name('documents');
        });
        Route::prefix('remittances')->name('remittances.')->group(function (): void {
            Route::get('/', [KoraPointRemittanceController::class, 'index'])->name('index');
            Route::post('/', [KoraPointRemittanceController::class, 'store'])->name('store');
            Route::get('{remittance:uuid}', [KoraPointRemittanceController::class, 'show'])->name('show');
            Route::post('{remittance:uuid}/submit', [KoraPointRemittanceController::class, 'submit'])->name('submit');
            Route::post('{remittance:uuid}/receive', [KoraPointRemittanceController::class, 'receive'])->name('receive');
            Route::post('{remittance:uuid}/proof', [KoraPartnerDocumentController::class, 'remittance'])->name('proof');
        });
        Route::get('documents/{document:uuid}/download', [KoraPartnerDocumentController::class, 'download'])->name('documents.download');
        Route::post('sales/{sale:uuid}/refunds', [KoraPointRefundController::class, 'store'])->name('refunds.store');
        Route::prefix('refunds/{refund:uuid}')->name('refunds.')->group(function (): void {
            Route::get('/', [KoraPointRefundController::class, 'show'])->name('show');
            Route::post('authorize', [KoraPointRefundController::class, 'authorizeRefund'])->name('authorize');
            Route::post('reject', [KoraPointRefundController::class, 'reject'])->name('reject');
            Route::post('execute', [KoraPointRefundController::class, 'execute'])->name('execute');
            Route::post('confirm', [KoraPointRefundController::class, 'confirm'])->name('confirm');
            Route::post('proof', [KoraPartnerDocumentController::class, 'refund'])->name('proof');
        });
        Route::get('{point:uuid}/sales/export', [KoraPointController::class, 'export'])->name('sales.export');
        Route::get('{point:uuid}', [KoraPointController::class, 'show'])->name('show');
        Route::put('{point:uuid}', [KoraPointController::class, 'update'])->name('update');
        Route::patch('{point:uuid}/terms', [KoraPointController::class, 'terms'])->name('terms');
        Route::patch('{point:uuid}/status', [KoraPointController::class, 'status'])->name('status');
        Route::post('{point:uuid}/users', [KoraPointController::class, 'assign'])->name('users');
        Route::post('{point:uuid}/documents', [KoraPartnerDocumentController::class, 'point'])->name('documents');
    });
});
