<?php

use App\Modules\Booking\Http\Controllers\CounterSaleController;
use App\Modules\BusNetwork\Http\Controllers\BusController;
use App\Modules\BusNetwork\Http\Controllers\LineController;
use App\Modules\Departures\Http\Controllers\DepartureController;
use App\Modules\DynamicPricing\Http\Controllers\FareController;
use App\Modules\IdentityAccess\Http\Controllers\AgencyController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => Auth::check()
    ? redirect()->route('dashboard')
    : redirect()->route('login')
)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // =========================================================
    // V1 MVP — Bus core (pages stubs, to be implemented)
    // =========================================================
    Route::get('sale', [CounterSaleController::class, 'index'])->name('sale');
    Route::post('sale/quote', [CounterSaleController::class, 'quote'])->name('sale.quote');
    Route::post('sale/hold', [CounterSaleController::class, 'hold'])->name('sale.hold');
    Route::inertia('cash', 'cash')->name('cash');
    Route::inertia('boarding', 'boarding')->name('boarding');
    Route::inertia('manifest', 'manifest')->name('manifest');

    // V1 MVP — Départs (CRUD)
    Route::get('departures', [DepartureController::class, 'index'])->name('departures.index');
    Route::get('departures/{departure:uuid}', [DepartureController::class, 'show'])->name('departures.show');
    Route::post('departures', [DepartureController::class, 'store'])->name('departures.store');
    Route::put('departures/{departure:uuid}', [DepartureController::class, 'update'])->name('departures.update');
    Route::patch('departures/{departure:uuid}/status', [DepartureController::class, 'changeStatus'])->name('departures.status');
    Route::patch('departures/{departure:uuid}/delay', [DepartureController::class, 'recordDelay'])->name('departures.delay');
    Route::inertia('boarding', 'boarding')->name('boarding');
    Route::inertia('manifest', 'manifest')->name('manifest');
    Route::get('fares', [FareController::class, 'index'])->name('fares.index');
    Route::get('fares/{fare:uuid}', [FareController::class, 'show'])->name('fares.show');
    Route::post('fares', [FareController::class, 'store'])->name('fares.store');
    Route::put('fares/{fare:uuid}', [FareController::class, 'update'])->name('fares.update');
    Route::patch('fares/{fare:uuid}/status', [FareController::class, 'changeStatus'])->name('fares.status');
    Route::inertia('passengers', 'passengers')->name('passengers');
    Route::inertia('tickets', 'tickets')->name('tickets');

    // V1 MVP — Réseau (Lignes & arrêts)
    Route::get('network', [LineController::class, 'index'])->name('network.index');
    Route::get('network/{line:uuid}', [LineController::class, 'show'])->name('network.show');
    Route::post('network', [LineController::class, 'store'])->name('network.store');
    Route::put('network/{line:uuid}', [LineController::class, 'update'])->name('network.update');
    Route::patch('network/{line:uuid}/status', [LineController::class, 'changeStatus'])->name('network.status');
    Route::post('network/{line:uuid}/stops', [LineController::class, 'storeStop'])->name('network.stops.store');
    Route::put('network/{line:uuid}/stops/{stop:uuid}', [LineController::class, 'updateStop'])->name('network.stops.update');
    Route::patch('network/{line:uuid}/stops/{stop:uuid}/deactivate', [LineController::class, 'deactivateStop'])->name('network.stops.deactivate');

    // V1 MVP — Flotte (Ressources)
    Route::get('fleet', [BusController::class, 'index'])->name('fleet.index');
    Route::get('fleet/{bus:uuid}', [BusController::class, 'show'])->name('fleet.show');
    Route::post('fleet', [BusController::class, 'store'])->name('fleet.store');
    Route::put('fleet/{bus:uuid}', [BusController::class, 'update'])->name('fleet.update');
    Route::patch('fleet/{bus:uuid}/status', [BusController::class, 'changeStatus'])->name('fleet.status');

    // V1 MVP — Agences (Ressources — référentiel parent de la flotte et des utilisateurs)
    Route::get('agencies', [AgencyController::class, 'index'])->name('agencies.index');
    Route::post('agencies', [AgencyController::class, 'store'])->name('agencies.store');
    Route::put('agencies/{agency:uuid}', [AgencyController::class, 'update'])->name('agencies.update');
    Route::patch('agencies/{agency:uuid}/status', [AgencyController::class, 'changeStatus'])->name('agencies.status');

    // V1 MVP — Tarification dynamique (Réseau commercial)
    Route::inertia('dynamic-pricing', 'dynamic-pricing')->name('dynamic-pricing');

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
    Route::get('promotions', $comingSoon('Promotions', 'Configurez les offres et codes promotionnels applicables aux nouvelles ventes.', 'Réseau commercial — V2'))->name('promotions');
    Route::get('companies', $comingSoon('Comptes entreprise', 'Gérez les plafonds de crédit, référents, factures et déplacements des organisations.', 'Réseau commercial — V2'))->name('companies');

    // Ressources — V2 (sauf fleet qui est V1)
    Route::get('resources', $comingSoon('Ressources', 'Pilotez la disponibilité et les affectations des véhicules.', 'Ressources — V2'))->name('resources');
    Route::get('maintenance', $comingSoon('Maintenance', 'Planifiez les interventions et maîtrisez les immobilisations.', 'Ressources — V2'))->name('maintenance');
    Route::get('garages', $comingSoon('Garages agréés', 'Gérez les partenaires atelier, leurs spécialités, tarifs et délais.', 'Ressources — V2'))->name('garages');
    Route::get('hr', $comingSoon('RH opérationnelle', 'Gérez les disponibilités, affectations et habilitations.', 'Ressources — V2'))->name('hr');

    // Finance — V2
    Route::get('finance', $comingSoon('Finance', 'Analysez les encaissements par canal et par activité.', 'Finance — V2'))->name('finance');
    Route::get('revenue', $comingSoon('Recettes consolidées', 'Analysez les encaissements par canal et par activité.', 'Finance — V2'))->name('revenue');
    Route::get('expenses', $comingSoon('Dépenses opérationnelles', 'Suivez les dépenses et leur circuit de validation.', 'Finance — V2'))->name('expenses');
    Route::get('reconciliation', $comingSoon('Rapprochements', 'Identifiez et traitez les écarts entre les sources.', 'Finance — V2'))->name('reconciliation');
    Route::get('casskai', $comingSoon('CassKai', 'Supervisez les transactions et les rapprochements CassKai.', 'Finance — V2'))->name('casskai');
    Route::get('mecef', $comingSoon('Conformité MECeF', 'Suivez les transmissions et traitez les anomalies fiscales.', 'Finance — V2'))->name('mecef');
    Route::get('payroll', $comingSoon('Paie et déclarations', 'Contrôlez les pointages, coûts employeur et exports CNSS et ITS.', 'Finance — V2'))->name('payroll');

    // Rentabilité — V2
    Route::get('profitability', $comingSoon('Rentabilité', 'Analysez la contribution des lignes, départs et véhicules.', 'V2'))->name('profitability');

    // Rapports — V2
    Route::get('reports', $comingSoon('Rapports', 'Consultez et préparez les rapports de votre périmètre.', 'V2'))->name('reports');

    // Notifications — V2
    Route::get('notifications', $comingSoon('Notifications', 'Suivez les confirmations, rappels et alertes envoyés sur chaque canal.', 'V2'))->name('notifications');

    // Administration — V2 (sauf settings qui existe déjà)
    Route::get('administration', $comingSoon('Administration', 'Gérez les comptes, rôles, paramètres et intégrations.', 'Administration'))->name('administration');
    Route::get('users', $comingSoon('Utilisateurs', 'Gérez les comptes, leurs statuts et leurs périmètres.', 'Administration'))->name('users');
    Route::get('roles', $comingSoon('Rôles et permissions', 'Maîtrisez précisément les habilitations de chaque profil.', 'Administration'))->name('roles');
    Route::get('terminals', $comingSoon('Terminaux P501', 'Administrez les terminaux autorisés par numéro de série et affectation.', 'Administration'))->name('terminals');
    Route::get('integrations', $comingSoon('Intégrations', 'Supervisez CassKai, MECeF, Mobile Money et les files de synchronisation.', 'Administration'))->name('integrations');
    Route::get('audit', $comingSoon('Journal d’audit', 'Retrouvez chaque action sensible et son contexte.', 'Administration'))->name('audit');

    // Alertes — V2
    Route::get('alerts', $comingSoon('Centre des alertes', 'Priorisez les situations qui nécessitent votre attention.', 'V2'))->name('alerts');
});

require __DIR__.'/settings.php';
