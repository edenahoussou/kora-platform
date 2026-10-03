<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicLegalController;
use App\Modules\AssistedBooking\Http\Controllers\AssistedBookingController;
use App\Modules\Booking\Http\Controllers\CounterSaleController;
use App\Modules\BusNetwork\Http\Controllers\BusAmenityController;
use App\Modules\BusNetwork\Http\Controllers\BusController;
use App\Modules\BusNetwork\Http\Controllers\LineController;
use App\Modules\BusNetwork\Http\Controllers\MapSettingsController;
use App\Modules\Cash\Http\Controllers\CashMediaController;
use App\Modules\Cash\Http\Controllers\CashSessionController;
use App\Modules\Departures\Http\Controllers\DepartureController;
use App\Modules\Departures\Http\Controllers\ManifestController;
use App\Modules\Departures\Http\Controllers\ManifestPdfController;
use App\Modules\Departures\Http\Controllers\ManifestPdfPreviewController;
use App\Modules\Departures\Http\Controllers\ManifestPrintController;
use App\Modules\Departures\Http\Controllers\ProgrammingController;
use App\Modules\DynamicPricing\Http\Controllers\FareController;
use App\Modules\DynamicPricing\Http\Controllers\PricingRuleController;
use App\Modules\DynamicPricing\Http\Controllers\PromotionController;
use App\Modules\DynamicPricing\Http\Controllers\TaxRuleController;
use App\Modules\Expenses\Http\Controllers\ExpenseController;
use App\Modules\Finance\Http\Controllers\RevenueController;
use App\Modules\Fiscalization\Http\Controllers\FiscalInvoiceController;
use App\Modules\Fiscalization\Http\Controllers\FiscalTestInvoiceController;
use App\Modules\IdentityAccess\Http\Controllers\AgencyController;
use App\Modules\IdentityAccess\Http\Controllers\TerminalP501Controller;
use App\Modules\Payments\Http\Controllers\AgencyPaymentAccountController;
use App\Modules\Ticketing\Http\Controllers\TicketIndexController;
use App\Modules\Ticketing\Http\Controllers\TicketPreviewController;
use App\Modules\Ticketing\Http\Controllers\TicketViewController;
use App\Modules\Tracking\Http\Controllers\FleetTrackingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('privacy-policy', [PublicLegalController::class, 'privacyPolicy'])
    ->name('privacy-policy');
Route::get('account-deletion', [PublicLegalController::class, 'accountDeletion'])
    ->name('account-deletion');

Route::get('tickets/{ticket:uuid}/pdf/signed', [TicketViewController::class, 'signedPdf'])
    ->middleware('signed')
    ->name('tickets.signed-pdf');

// Ticket preview — static mock data for visual validation
Route::get('tickets/preview/{scenario}', [TicketPreviewController::class, 'show'])
    ->name('tickets.preview');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // =========================================================
    // V1 MVP — Bus core (pages stubs, to be implemented)
    // =========================================================
    Route::get('sale', [CounterSaleController::class, 'index'])->name('sale');
    Route::post('sale/quote', [CounterSaleController::class, 'quote'])->name('sale.quote');
    Route::post('sale/hold', [CounterSaleController::class, 'hold'])->name('sale.hold');
    Route::post('sale/seats', [CounterSaleController::class, 'seats'])->name('sale.seats');
    Route::post('sale/confirm', [CounterSaleController::class, 'confirm'])->name('sale.confirm');
    Route::post('sale/{reservation:uuid}/release', [CounterSaleController::class, 'releaseHold'])->name('sale.release');
    Route::patch('sale/{reservation:uuid}/draft', [CounterSaleController::class, 'saveDraft'])->name('sale.draft');
    Route::post('sale/{reservation:uuid}/passengers', [CounterSaleController::class, 'storePassengers'])->name('sale.passengers');
    Route::post('sale/{reservation:uuid}/payment', [CounterSaleController::class, 'storePayment'])->name('sale.payment');
    Route::post('sale/{reservation:uuid}/tickets/retry', [CounterSaleController::class, 'retryTicketIssuance'])->name('sale.tickets.retry');
    Route::prefix('assisted-bookings')->name('assisted-bookings.')->group(function (): void {
        Route::get('/', [AssistedBookingController::class, 'index'])->name('index');
        Route::get('{assistedBooking:uuid}', [AssistedBookingController::class, 'show'])->name('show');
        Route::get('{assistedBooking:uuid}/detail', [AssistedBookingController::class, 'detail'])->name('detail');
        Route::post('{assistedBooking:uuid}/claim', [AssistedBookingController::class, 'claim'])->name('claim');
        Route::post('{assistedBooking:uuid}/release', [AssistedBookingController::class, 'release'])->name('release');
        Route::post('{assistedBooking:uuid}/reassign', [AssistedBookingController::class, 'reassign'])->name('reassign');
        Route::post('{assistedBooking:uuid}/refuse', [AssistedBookingController::class, 'refuse'])->name('refuse');
        Route::post('{assistedBooking:uuid}/prepare-sale', [AssistedBookingController::class, 'prepareSale'])->name('prepare-sale');
    });
    Route::get('fiscal-invoices', [FiscalInvoiceController::class, 'index'])->name('fiscal-invoices.index');
    Route::get('fiscal-invoices/export', [FiscalInvoiceController::class, 'export'])->name('fiscal-invoices.export');
    Route::get('fiscal-invoices/{fiscalInvoice:uuid}', [FiscalInvoiceController::class, 'show'])->name('fiscal-invoices.show');
    Route::get('fiscal-invoices/{fiscalInvoice:uuid}/pdf', [FiscalInvoiceController::class, 'pdf'])->name('fiscal-invoices.pdf');
    Route::post('fiscal-invoices/{fiscalInvoice:uuid}/retry', [FiscalInvoiceController::class, 'retry'])->name('fiscal-invoices.retry');
    Route::post('fiscal-invoices/{fiscalInvoice:uuid}/credit', [FiscalInvoiceController::class, 'credit'])->name('fiscal-invoices.credit');
    Route::get('fiscalization/test-invoices', [FiscalTestInvoiceController::class, 'index'])->name('fiscal-test-invoices.index');
    Route::post('fiscalization/test-invoices', [FiscalTestInvoiceController::class, 'store'])->name('fiscal-test-invoices.store');
    Route::get('fiscalization/test-invoices/{run:uuid}', [FiscalTestInvoiceController::class, 'show'])->name('fiscal-test-invoices.show');
    Route::get('fiscalization/test-invoices/{run:uuid}/pdf', [FiscalTestInvoiceController::class, 'pdf'])->name('fiscal-test-invoices.pdf');
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('expenses/{expense:uuid}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::post('expenses/{expense:uuid}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('expenses/{expense:uuid}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    Route::post('expenses/{expense:uuid}/reverse', [ExpenseController::class, 'reverse'])->name('expenses.reverse');
    Route::get('expenses/{expense:uuid}/media/{mediaUuid}', [ExpenseController::class, 'media'])->name('expenses.media');
    Route::get('revenue', [RevenueController::class, 'index'])->name('revenue');
    Route::get('revenue/export', [RevenueController::class, 'export'])->name('revenue.export');
    Route::get('cash', [CashSessionController::class, 'index'])->name('cash');
    Route::get('cash/export', [CashSessionController::class, 'export'])->name('cash.export');
    Route::post('cash/open', [CashSessionController::class, 'store'])->name('cash.open');
    Route::post('cash/{cashSession:uuid}/close', [CashSessionController::class, 'close'])->name('cash.close');
    Route::post('cash/{cashSession:uuid}/approve-variance', [CashSessionController::class, 'approveVariance'])->name('cash.approve-variance');
    Route::post('cash/movements', [CashSessionController::class, 'storeMovement'])->name('cash.movements.store');
    Route::post('cash/movements/{movement:uuid}/approve', [CashSessionController::class, 'approveMovement'])->name('cash.movements.approve');
    Route::post('cash/movements/{movement:uuid}/reject', [CashSessionController::class, 'rejectMovement'])->name('cash.movements.reject');
    Route::post('cash/transfers', [CashSessionController::class, 'storeTransfer'])->name('cash.transfers.store');
    Route::post('cash/transfers/{cashTransfer:uuid}/approve', [CashSessionController::class, 'approveTransfer'])->name('cash.transfers.approve');
    Route::post('cash/transfers/{cashTransfer:uuid}/reject', [CashSessionController::class, 'rejectTransfer'])->name('cash.transfers.reject');
    Route::get('cash/media/{mediaUuid}', [CashMediaController::class, 'show'])->name('cash.media');

    // V1 MVP — Départs (CRUD)
    Route::prefix('programming')->name('programming.')->group(function (): void {
        Route::get('/', [ProgrammingController::class, 'index'])->name('index');
        Route::get('templates/create', [ProgrammingController::class, 'createTemplate'])->name('templates.create');
        Route::get('templates/{template:uuid}/edit', [ProgrammingController::class, 'editTemplate'])->name('templates.edit');
        Route::post('templates', [ProgrammingController::class, 'saveTemplate'])->name('templates.store');
        Route::put('templates/{template:uuid}', [ProgrammingController::class, 'saveTemplate'])->name('templates.update');
        Route::patch('templates/{template:uuid}/archive', [ProgrammingController::class, 'archiveTemplate'])->name('templates.archive');
        Route::get('programmes/create', [ProgrammingController::class, 'createProgramme'])->name('programmes.create');
        Route::post('programmes/preview', [ProgrammingController::class, 'previewProgramme'])->name('programmes.preview');
        Route::post('programmes', [ProgrammingController::class, 'storeProgramme'])->name('programmes.store');
        Route::post('programmes/{programme:uuid}/actions', [ProgrammingController::class, 'programmeAction'])->name('programmes.actions');
        Route::get('items/{item:uuid}', [ProgrammingController::class, 'item'])->name('items.show');
        Route::post('actions', [ProgrammingController::class, 'mutate'])->name('actions');
    });

    Route::get('departures', [DepartureController::class, 'index'])->name('departures.index');
    Route::get('departures/{departure:uuid}', [DepartureController::class, 'show'])->name('departures.show');
    Route::post('departures', [DepartureController::class, 'store'])->name('departures.store');
    Route::put('departures/{departure:uuid}', [DepartureController::class, 'update'])->name('departures.update');
    Route::patch('departures/{departure:uuid}/status', [DepartureController::class, 'changeStatus'])->name('departures.status');
    Route::patch('departures/{departure:uuid}/delay', [DepartureController::class, 'recordDelay'])->name('departures.delay');
    Route::post('departures/{departure:uuid}/no-show', [DepartureController::class, 'noShow'])->name('departures.no-show');
    Route::post('departures/{departure:uuid}/early-exit', [DepartureController::class, 'earlyExit'])->name('departures.early-exit');
    Route::post('departures/{departure:uuid}/close-no-shows', [DepartureController::class, 'closeNoShows'])->name('departures.close-no-shows');
    Route::post('departures/{departure:uuid}/reverse-boarding', [DepartureController::class, 'reverseBoarding'])->name('departures.reverse-boarding');
    Route::inertia('boarding', 'boarding')->name('boarding');
    Route::get('operations/fleet-tracking', [FleetTrackingPageController::class, 'index'])
        ->name('operations.fleet-tracking');
    // Note : pas de page « liste de manifestes » — le manifeste d'un départ
    // s'ouvre depuis la fiche départ (bouton Manifeste → manifest.show).
    Route::get('manifest/reservations/{reservation:uuid}', [ManifestController::class, 'reservation'])->name('manifest.reservations.show');
    // Prévisualisation du futur manifeste PDF A4 (données fictives, validation
    // visuelle) — doit rester déclarée AVANT le binding {departure:uuid}.
    Route::get('manifest/pdf-preview', ManifestPdfPreviewController::class)->name('manifest.pdf-preview');
    Route::get('manifest/{departure:uuid}/print', ManifestPrintController::class)->name('manifest.print');
    Route::get('manifest/{departure:uuid}/pdf', ManifestPdfController::class)->name('manifest.pdf');
    Route::get('manifest/{departure:uuid}', [ManifestController::class, 'show'])->name('manifest.show');
    Route::get('fares', [FareController::class, 'index'])->name('fares.index');
    Route::get('fares/{fare:uuid}', [FareController::class, 'show'])->name('fares.show');
    Route::post('fares', [FareController::class, 'store'])->name('fares.store');
    Route::put('fares/{fare:uuid}', [FareController::class, 'update'])->name('fares.update');
    Route::patch('fares/{fare:uuid}/status', [FareController::class, 'changeStatus'])->name('fares.status');

    // Règles de pricing dynamique (CDC §3.1.3.4, §9.2)
    Route::get('dynamic-pricing', [PricingRuleController::class, 'index'])->name('pricing-rules.index');
    Route::post('dynamic-pricing', [PricingRuleController::class, 'store'])->name('pricing-rules.store');
    Route::put('dynamic-pricing/{pricingRule:uuid}', [PricingRuleController::class, 'update'])->name('pricing-rules.update');
    Route::patch('dynamic-pricing/{pricingRule:uuid}/status', [PricingRuleController::class, 'changeStatus'])->name('pricing-rules.status');
    Route::patch('dynamic-pricing/settings', [PricingRuleController::class, 'updateSettings'])->name('pricing-rules.settings');

    // Taux fiscaux paramétrables (CDC §9.8) — configuration uniquement, non câblé
    // au calcul de devis public (hors périmètre) ; alimentera la facturation MECeF.
    Route::get('tax-rules', [TaxRuleController::class, 'index'])->name('tax-rules.index');
    Route::post('tax-rules', [TaxRuleController::class, 'store'])->name('tax-rules.store');
    Route::put('tax-rules/{taxRule:uuid}', [TaxRuleController::class, 'update'])->name('tax-rules.update');
    Route::patch('tax-rules/{taxRule:uuid}/status', [TaxRuleController::class, 'changeStatus'])->name('tax-rules.status');
    Route::get('tickets', [TicketIndexController::class, 'index'])->name('tickets');
    Route::get('tickets/{ticket:uuid}', [TicketViewController::class, 'show'])->name('tickets.show');
    Route::get('tickets/{ticket:uuid}/pdf', [TicketViewController::class, 'pdf'])->name('tickets.pdf');
    Route::post('tickets/{ticket:uuid}/whatsapp', [TicketViewController::class, 'sendWhatsApp'])->name('tickets.whatsapp');

    // V1 MVP — Réseau (Lignes & arrêts)
    Route::get('network', [LineController::class, 'index'])->name('network.index');
    Route::get('network/settings/map', [MapSettingsController::class, 'edit'])->name('network.map-settings.edit');
    Route::patch('network/settings/map', [MapSettingsController::class, 'update'])->name('network.map-settings.update');
    Route::post('network', [LineController::class, 'store'])->name('network.store');
    Route::put('network/{line:uuid}', [LineController::class, 'update'])->name('network.update');
    Route::patch('network/{line:uuid}/status', [LineController::class, 'changeStatus'])->name('network.status');

    // V1 MVP — Flotte (Ressources)
    Route::get('fleet', [BusController::class, 'index'])->name('fleet.index');
    Route::get('fleet/{bus:uuid}', [BusController::class, 'show'])->name('fleet.show');
    Route::post('fleet', [BusController::class, 'store'])->name('fleet.store');
    Route::put('fleet/{bus:uuid}', [BusController::class, 'update'])->name('fleet.update');
    Route::patch('fleet/{bus:uuid}/status', [BusController::class, 'changeStatus'])->name('fleet.status');

    // Commodités (référentiel réutilisable, table intermédiaire bus <-> commodité)
    // Route top-level (pas nichée sous /fleet) pour ne pas être capturée par le lien
    // "Flotte" de la sidebar (isActive() fait un startsWith sur le pathname).
    Route::get('amenities', [BusAmenityController::class, 'index'])->name('amenities.index');
    Route::post('amenities', [BusAmenityController::class, 'store'])->name('amenities.store');
    Route::put('amenities/{amenity:uuid}', [BusAmenityController::class, 'update'])->name('amenities.update');
    Route::patch('amenities/{amenity:uuid}/status', [BusAmenityController::class, 'changeStatus'])->name('amenities.status');

    // V1 MVP — Agences (Ressources — référentiel parent de la flotte et des utilisateurs)
    Route::get('agencies', [AgencyController::class, 'index'])->name('agencies.index');
    Route::get('agencies/{agency:uuid}', [AgencyController::class, 'show'])->name('agencies.show');
    Route::post('agencies', [AgencyController::class, 'store'])->name('agencies.store');
    Route::put('agencies/{agency:uuid}', [AgencyController::class, 'update'])->name('agencies.update');
    Route::patch('agencies/{agency:uuid}/status', [AgencyController::class, 'changeStatus'])->name('agencies.status');
    Route::post('agencies/{agency:uuid}/payment-accounts', [AgencyPaymentAccountController::class, 'store'])->name('agencies.payment-accounts.store');
    Route::put('agencies/{agency:uuid}/payment-accounts/{account:uuid}', [AgencyPaymentAccountController::class, 'update'])->withoutScopedBindings()->name('agencies.payment-accounts.update');
    Route::patch('agencies/{agency:uuid}/payment-accounts/{account:uuid}/status', [AgencyPaymentAccountController::class, 'changeStatus'])->withoutScopedBindings()->name('agencies.payment-accounts.status');
    Route::post('agencies/{agency:uuid}/cash-registers', [AgencyController::class, 'storeCashRegister'])->name('agencies.cash-registers.store');
    Route::put('cash-registers/{cashRegister:uuid}', [AgencyController::class, 'updateCashRegister'])->name('agencies.cash-registers.update');
    Route::patch('cash-registers/{cashRegister:uuid}/status', [AgencyController::class, 'changeCashRegisterStatus'])->name('agencies.cash-registers.status');

    // Terminaux P501 — endpoints JSON consommés par le panneau Inertia et
    // conservés pour les consommateurs backend existants.
    Route::prefix('terminals')->name('terminals.')->group(function (): void {
        Route::get('data', [TerminalP501Controller::class, 'index'])->name('index');
        Route::post('/', [TerminalP501Controller::class, 'store'])->name('store');
        Route::post('{terminal:uuid}/activate', [TerminalP501Controller::class, 'activate'])->name('activate');
        Route::post('{terminal:uuid}/deactivate', [TerminalP501Controller::class, 'deactivate'])->name('deactivate');
        Route::post('{terminal:uuid}/maintenance', [TerminalP501Controller::class, 'maintenance'])->name('maintenance');
        Route::post('{terminal:uuid}/lost', [TerminalP501Controller::class, 'reportLost'])->name('lost');
        Route::post('{terminal:uuid}/assign', [TerminalP501Controller::class, 'assign'])->name('assign');
        Route::post('{terminal:uuid}/unassign', [TerminalP501Controller::class, 'unassign'])->name('unassign');
        Route::post('{terminal:uuid}/recover', [TerminalP501Controller::class, 'recover'])->name('recover');
        Route::post('{terminal:uuid}/stock', [TerminalP501Controller::class, 'returnToStock'])->name('stock');
        Route::post('{terminal:uuid}/revoke-sessions', [TerminalP501Controller::class, 'revokeSessions'])->name('revoke-sessions');
    });

    // =========================================================
    // V1 secondary — Colis & Wallet Lite (stubs)
    // =========================================================
    Route::inertia('parcels', 'parcels')->name('parcels');

    // =========================================================
    // V2 — Placeholders (coming-soon page)
    // =========================================================
    $comingSoon = function (string $title, string $description, string $module = 'Bientôt disponible') {
        return fn () => inertia('coming-soon', [
            'title' => $title,
            'description' => $description,
            'module' => $module,
        ]);
    };

    // Exploitation — V2 sub-modules
    Route::get('operations', $comingSoon('Exploitation', 'Planifiez, affectez et supervisez chaque départ.', 'V2'))
        ->name('operations');

    // KORA RIDE — V2
    Route::get('ride', $comingSoon('KORA RIDE', 'Supervisez les courses urbaines, la disponibilité et la qualité de service.', 'KORA RIDE — V2'))->name('ride');
    Route::get('ride/dashboard', $comingSoon('Pilotage KORA RIDE', 'Supervisez les courses urbaines, la disponibilité et la qualité de service.', 'KORA RIDE — V2'))->name('ride.dashboard');
    Route::get('ride/matching', $comingSoon('Matching RIDE', 'Supervisez les recherches, tentatives, délais d’acceptation et zones sans chauffeur.', 'KORA RIDE — V2'))->name('ride.matching');
    Route::get('ride/courses', $comingSoon('Courses RIDE', 'Suivez chaque demande de la recherche chauffeur jusqu’au règlement final.', 'KORA RIDE — V2'))->name('ride.courses');
    Route::get('ride/drivers', $comingSoon('Chauffeurs RIDE', 'Contrôlez l’éligibilité, la disponibilité, les documents et la performance.', 'KORA RIDE — V2'))->name('ride.drivers');
    Route::get('ride/onboarding', $comingSoon('Dossiers chauffeurs', 'Validez chaque document et suivez le délai de traitement de 24 à 48 heures.', 'KORA RIDE — V2'))->name('ride.onboarding');
    Route::get('ride/training', $comingSoon('Formation chauffeurs', 'Suivez la formation obligatoire avant activation des comptes chauffeurs.', 'KORA RIDE — V2'))->name('ride.training');
    Route::get('ride/champions', $comingSoon('KORA Champions', 'Pilotez les niveaux recalculés chaque mois sur les 30 derniers jours.', 'KORA RIDE — V2'))->name('ride.champions');
    Route::get('ride/vehicles', $comingSoon('Véhicules RIDE', 'Gérez les motos, tricycles et voitures autorisés sur la plateforme.', 'KORA RIDE — V2'))->name('ride.vehicles');
    Route::get('ride/leasing', $comingSoon('Leasing KORA', 'Suivez contrats, cautions, échéanciers, paiements et impayés de la flotte propre.', 'KORA RIDE — V2'))->name('ride.leasing');
    Route::get('ride/zones', $comingSoon('Zones et couverture', 'Pilotez les zones desservies, la disponibilité et les restrictions.', 'KORA RIDE — V2'))->name('ride.zones');
    Route::get('ride/categories', $comingSoon('Catégories RIDE', 'Administrez Moto, Keke, Go, Comfort et XL.', 'KORA RIDE — V2'))->name('ride.categories');
    Route::get('ride/pricing', $comingSoon('Tarification RIDE', 'Configurez prise en charge, distance, durée et majorations encadrées.', 'KORA RIDE — V2'))->name('ride.pricing');
    Route::get('ride/payments', $comingSoon('Paiements RIDE', 'Suivez les encaissements, remboursements et reversements des courses.', 'KORA RIDE — V2'))->name('ride.payments');
    Route::get('ride/commissions', $comingSoon('Commissions chauffeurs', 'Calculez et validez les commissions et soldes chauffeurs.', 'KORA RIDE — V2'))->name('ride.commissions');
    Route::get('ride/incidents', $comingSoon('Incidents et sécurité RIDE', 'Traitez les alertes SOS, litiges, annulations et signalements.', 'KORA RIDE — V2'))->name('ride.incidents');
    Route::get('ride/ratings', $comingSoon('Qualité RIDE', 'Analysez les évaluations passagers et chauffeurs et les motifs d’insatisfaction.', 'KORA RIDE — V2'))->name('ride.ratings');
    Route::get('ride/reconciliation', $comingSoon('Rapprochements RIDE', 'Comparez les courses, paiements, commissions et reversements.', 'KORA RIDE — V2'))->name('ride.reconciliation');
    Route::get('ride/profitability', $comingSoon('Rentabilité RIDE', 'Mesurez la contribution par zone, catégorie et période.', 'KORA RIDE — V2'))->name('ride.profitability');

    // Wallet & fidélité — V2
    Route::get('wallet', $comingSoon('Wallet & fidélité', 'Suivez les soldes, crédits, points et usages sur tous les services KORA.', 'Wallet — V2'))->name('wallet');
    Route::get('wallet/dashboard', $comingSoon('Pilotage KORA Wallet', 'Suivez les soldes, crédits, points et usages sur tous les services KORA.', 'Wallet — V2'))->name('wallet.dashboard');
    Route::get('wallet/accounts', $comingSoon('Comptes Wallet', 'Consultez les soldes, statuts, activité et sécurité des portefeuilles.', 'Wallet — V2'))->name('wallet.accounts');
    Route::get('wallet/transactions', $comingSoon('Mouvements Wallet', 'Contrôlez crédits, débits, remboursements, points et paiements combinés.', 'Wallet — V2'))->name('wallet.transactions');
    Route::get('wallet/rules', $comingSoon('Règles de fidélité', 'Configurez acquisition, utilisation, communication et expiration des points.', 'Wallet — V2'))->name('wallet.rules');
    Route::get('wallet/expiry', $comingSoon('Expirations Wallet', 'Suivez les alertes J-30, J-7 et J-1 et les prolongations d’activité.', 'Wallet — V2'))->name('wallet.expiry');
    Route::get('wallet/reconciliation', $comingSoon('Rapprochements Wallet', 'Rapprochez les mouvements Wallet avec billets, colis, RIDE et Mobile Money.', 'Wallet — V2'))->name('wallet.reconciliation');

    // Réseau commercial — V2 (sauf dynamic-pricing qui est V1)
    Route::get('network-commercial', $comingSoon('Réseau commercial', 'Suivez l’activité et la performance du réseau de distribution.', 'Réseau commercial — V2'))->name('network-commercial');
    Route::get('kora-points', $comingSoon('Réseau KORA POINT', 'Suivez l’activité et la performance de chaque point.', 'Réseau commercial — V2'))->name('kora-points');
    Route::get('commissions', $comingSoon('Commissions', 'Contrôlez les commissions calculées sur les opérations éligibles.', 'Réseau commercial — V2'))->name('commissions');
    Route::get('settlements', $comingSoon('Reversements', 'Rapprochez et validez les reversements du réseau.', 'Réseau commercial — V2'))->name('settlements');
    // Promotions à code (MEZ-73) : offres commerciales déclenchées par la
    // saisie du code au devis — jamais rétroactives sur les ventes confirmées.
    Route::prefix('promotions')->name('promotions.')->group(function (): void {
        Route::get('/', [PromotionController::class, 'index'])->name('index');
        Route::post('/', [PromotionController::class, 'store'])->name('store');
        Route::put('{promotion:uuid}', [PromotionController::class, 'update'])->name('update');
        Route::patch('{promotion:uuid}/status', [PromotionController::class, 'changeStatus'])->name('status');
    });
    // Ressources — V2 (sauf fleet qui est V1)
    Route::get('resources', $comingSoon('Ressources', 'Pilotez la disponibilité et les affectations des véhicules.', 'Ressources — V2'))->name('resources');
    Route::get('maintenance', $comingSoon('Maintenance', 'Planifiez les interventions et maîtrisez les immobilisations.', 'Ressources — V2'))->name('maintenance');
    Route::get('garages', $comingSoon('Garages agréés', 'Gérez les partenaires atelier, leurs spécialités, tarifs et délais.', 'Ressources — V2'))->name('garages');
    Route::get('hr', $comingSoon('RH opérationnelle', 'Gérez les disponibilités, affectations et habilitations.', 'Ressources — V2'))->name('hr');

    // Finance — V2
    Route::get('finance', $comingSoon('Finance', 'Analysez les encaissements par canal et par activité.', 'Finance — V2'))->name('finance');
    Route::get('reconciliation', $comingSoon('Rapprochements', 'Identifiez et traitez les écarts entre les sources.', 'Finance — V2'))->name('reconciliation');
    Route::get('casskai', $comingSoon('CassKai', 'Supervisez les transactions et les rapprochements CassKai.', 'Finance — V2'))->name('casskai');
    Route::get('mecef', $comingSoon('Conformité MECeF', 'Suivez les transmissions et traitez les anomalies fiscales.', 'Finance — V2'))->name('mecef');
    Route::get('payroll', $comingSoon('Paie et déclarations', 'Contrôlez les pointages, coûts employeur et exports CNSS et ITS.', 'Finance — V2'))->name('payroll');

    // Rentabilité — V2
    Route::get('profitability', $comingSoon('Rentabilité', 'Analysez la contribution des lignes, départs et véhicules.', 'V2'))->name('profitability');

    // Rapports — V2
    Route::get('reports', $comingSoon('Rapports', 'Consultez et préparez les rapports de votre périmètre.', 'V2'))->name('reports');

    // Alertes — V2
    Route::get('alerts', $comingSoon('Centre des alertes', 'Priorisez les situations qui nécessitent votre attention.', 'V2'))->name('alerts');
});

require __DIR__.'/administration.php';
require __DIR__.'/companies.php';
require __DIR__.'/settings.php';
