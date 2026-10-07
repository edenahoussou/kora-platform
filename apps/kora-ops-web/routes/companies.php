<?php

use App\Http\Controllers\CompanyAccountWebController;
use App\Modules\CompanyAccounts\Http\Controllers\CompanyPortalController;
use App\Modules\CompanyAccounts\Http\Controllers\CompanyPortalReservationController;
use App\Modules\CompanyAccounts\Http\Controllers\CompanyPortalSaleController;
use App\Modules\CompanyAccounts\Http\Controllers\CompanyPortalStatementController;
use App\Modules\CompanyAccounts\Http\Controllers\CompanyPortalTravelerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    // Comptes organisationnels — administration interne (DAF / admin).
    Route::prefix('companies')->name('companies.')->group(function (): void {
        Route::get('/', [CompanyAccountWebController::class, 'index'])->name('index');
        Route::post('/', [CompanyAccountWebController::class, 'store'])->name('store');
        Route::put('{companyAccount:uuid}', [CompanyAccountWebController::class, 'update'])->name('update');
        Route::patch('{companyAccount:uuid}/status', [CompanyAccountWebController::class, 'changeStatus'])->name('status');
        Route::post('{companyAccount:uuid}/members', [CompanyAccountWebController::class, 'addMember'])->name('members.store');
        Route::post('{companyAccount:uuid}/referent', [CompanyAccountWebController::class, 'inviteReferent'])->name('referent.store');
    });

    // Espace entreprise — self-service du référent d'organisation (MEZ-113).
    Route::prefix('entreprise')->name('company-portal.')->middleware('company.referent')->group(function (): void {
        Route::get('/', [CompanyPortalController::class, 'index'])->name('index');
        Route::get('achat', [CompanyPortalSaleController::class, 'index'])->name('sale.index');
        Route::post('achat/devis', [CompanyPortalSaleController::class, 'quote'])->name('sale.quote');
        Route::post('achat/sieges', [CompanyPortalSaleController::class, 'seats'])->name('sale.seats');
        Route::post('achat/hold', [CompanyPortalSaleController::class, 'hold'])->name('sale.hold');
        Route::post('achat/{reservation:uuid}/liberer', [CompanyPortalSaleController::class, 'release'])->name('sale.release');
        Route::post('achat/{reservation:uuid}/voyageurs', [CompanyPortalSaleController::class, 'storePassengers'])->name('sale.passengers');
        Route::post('achat/{reservation:uuid}/paiement', [CompanyPortalSaleController::class, 'storePayment'])->name('sale.payment');
        Route::post('achat/{reservation:uuid}/billets/reessayer', [CompanyPortalSaleController::class, 'retryTicketIssuance'])->name('sale.tickets.retry');
        Route::get('achats', [CompanyPortalReservationController::class, 'index'])->name('reservations.index');
        Route::get('achats/export', [CompanyPortalReservationController::class, 'export'])->name('reservations.export');
        Route::get('achats/{reservation:uuid}', [CompanyPortalReservationController::class, 'show'])->name('reservations.show');
        Route::get('releves', [CompanyPortalStatementController::class, 'index'])->name('statements.index');
        Route::get('releves/{statement:uuid}', [CompanyPortalStatementController::class, 'show'])->name('statements.show');
        Route::get('membres', [CompanyPortalTravelerController::class, 'index'])->name('travelers.index');
        Route::post('membres', [CompanyPortalTravelerController::class, 'store'])->name('travelers.store');
        Route::post('membres/import', [CompanyPortalTravelerController::class, 'import'])->name('travelers.import');
        Route::get('membres/modele', [CompanyPortalTravelerController::class, 'template'])->name('travelers.template');
        Route::get('membres/eligibles', [CompanyPortalTravelerController::class, 'eligible'])->name('travelers.eligible');
        Route::put('membres/{traveler:uuid}', [CompanyPortalTravelerController::class, 'update'])->name('travelers.update');
        Route::patch('membres/{traveler:uuid}/statut', [CompanyPortalTravelerController::class, 'changeStatus'])->name('travelers.status');
    });
});
