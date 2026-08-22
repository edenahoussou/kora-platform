import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../booking/domain/entities/city.dart';
import '../bloc/home_bloc.dart';
import '../widgets/home_hero.dart';
import '../widgets/home_quick_grid.dart';
import '../widgets/home_ride_banner.dart';
import '../widgets/home_search_card.dart';
import '../widgets/next_trip_card.dart';
import '../widgets/travel_reminder.dart';
import 'city_picker_page.dart';
import 'date_picker_page.dart';

/// Onglet « Accueil » — écran de recherche de départs Bus.
///
/// Correspond à l'écran `HOME-01` de la maquette
/// (`docs/maquettes/kora-app-passenger`, `js/app.js` → `home`).
///
/// Les données affichées viennent d'un jeu de test (voir
/// `features/booking/data/datasources/booking_mock_data.dart`) : le
/// branchement sur l'API réelle est prévu au Sprint 2 et ne touchera pas
/// ce fichier.
class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider<HomeBloc>(
      create: (_) => getIt<HomeBloc>()..add(const HomeStarted()),
      child: const _HomeView(),
    );
  }
}

class _HomeView extends StatelessWidget {
  const _HomeView();

  /// TODO(auth): remplacer par le prénom du compte connecté dès que la
  /// feature `auth` existe (voir GUIDE.md §13).
  static const String _firstName = 'Aïcha';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            HomeHero(
              greeting: 'Bonjour $_firstName,',
              // Coupures de ligne explicites : la maquette compose ce
              // titre sur 3 lignes, et un retour automatique dépendrait
              // de la largeur de l'écran.
              title: 'Votre prochain\nvoyage commence\nici.',
              onNotificationsTap: () =>
                  context.push(AppRouter.notificationsPath),
            ),
            // La carte de recherche chevauche l'en-tête de 49px
            // (`.floating-search { margin-top: -49px }`). `Transform` ne
            // modifiant pas la hauteur occupée, l'espace ainsi libéré en
            // bas de page est repris dans le `SizedBox` final.
            Transform.translate(
              offset: const Offset(0, -49),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 18),
                child: BlocBuilder<HomeBloc, HomeState>(
                  builder: (context, state) => switch (state.status) {
                    HomeStatus.loading => const _HomeLoading(),
                    HomeStatus.failure => _HomeError(
                      message: state.errorMessage,
                      onRetry: () =>
                          context.read<HomeBloc>().add(const HomeStarted()),
                    ),
                    HomeStatus.success => _HomeContent(state: state),
                  },
                ),
              ),
            ),
            // Dégage la barre de navigation basse (76px + ses marges),
            // moins les 49px déjà libérés par le décalage ci-dessus.
            const SizedBox(height: 50),
          ],
        ),
      ),
    );
  }
}

/// Contenu de l'accueil une fois les données chargées.
class _HomeContent extends StatelessWidget {
  const _HomeContent({required this.state});

  final HomeState state;

  @override
  Widget build(BuildContext context) {
    final nextTrip = state.nextTrip;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        HomeSearchCard(
          origin: state.origin,
          destination: state.destination,
          date: state.date,
          passengers: state.passengers,
          onOriginTap: () => _pickCity(context, isOrigin: true),
          onDestinationTap: () => _pickCity(context, isOrigin: false),
          onSwapTap: () =>
              context.read<HomeBloc>().add(const HomeRouteSwapped()),
          onDateTap: () => _pickDate(context),
          onPassengersTap: () => _pickPassengers(context),
          onSearch: state.canSearch ? () => _search(context) : null,
        ),
        const SectionTitle(title: 'Accès rapides'),
        HomeQuickGrid(
          items: [
            QuickAccessItem(
              label: 'Ride',
              highlighted: true,
              icon: const KoraVehicleIcon(
                type: KoraVehicleType.standard,
                size: 24,
                colorFilter: ColorFilter.mode(AppColors.kora, BlendMode.srcIn),
              ),
              onTap: () => _notAvailableYet(
                context,
                'KORA Ride arrive après le MVP Bus.',
              ),
            ),
            QuickAccessItem(
              label: 'Colis',
              icon: _quickIcon(context, KoraNavIconType.parcel),
              onTap: () => context.go('/colis'),
            ),
            QuickAccessItem(
              label: 'Wallet',
              icon: _quickIcon(context, KoraNavIconType.wallet),
              onTap: () => context.go('/wallet'),
            ),
            QuickAccessItem(
              label: 'Mes billets',
              icon: _quickIcon(context, KoraNavIconType.tickets),
              onTap: () => context.go('/voyages'),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        HomeRideBanner(
          onTap: () =>
              _notAvailableYet(context, 'KORA Ride arrive après le MVP Bus.'),
        ),
        SectionTitle(
          title: 'Prochain voyage',
          actionLabel: 'Voir tout',
          onActionTap: () => context.go('/voyages'),
        ),
        if (nextTrip == null)
          NextTripEmptyCard(
            onSearchTap: state.canSearch ? () => _search(context) : null,
          )
        else ...[
          NextTripCard(
            trip: nextTrip,
            // Date de référence figée au chargement (voir HomeState.today)
            // et non `DateTime.now()` : sans ça, « Dans 7 jours » se
            // calculerait sur une horloge différente de celle qui a
            // produit les données.
            now: state.today ?? DateTime.now(),
            onTap: () => context.go('/voyages'),
          ),
          const SizedBox(height: 10),
          const TravelReminder(
            title: 'Rappel utile',
            message: 'Présentez-vous 30 min avant le départ.',
          ),
        ],
      ],
    );
  }

  Widget _quickIcon(BuildContext context, KoraNavIconType type) {
    return KoraNavIcon(
      type: type,
      size: 17,
      color: context.koraColors.textPrimary,
    );
  }

  /// Ouvre l'écran plein de sélection de ville et attend son résultat.
  ///
  /// `push` et non `go` : on empile un écran dont on veut récupérer la
  /// valeur de retour — la ville choisie, renvoyée par le `pop` de
  /// [CityPickerPage].
  Future<void> _pickCity(BuildContext context, {required bool isOrigin}) async {
    final bloc = context.read<HomeBloc>();

    final city = await context.push<City>(
      isOrigin ? AppRouter.originPath : AppRouter.destinationPath,
      extra: CityPickerArgs(
        cities: state.cities,
        recentCities: state.recentCities,
        selected: isOrigin ? state.origin : state.destination,
      ),
    );
    if (city == null) return;

    bloc.add(
      isOrigin ? HomeOriginSelected(city) : HomeDestinationSelected(city),
    );
  }

  Future<void> _pickDate(BuildContext context) async {
    final bloc = context.read<HomeBloc>();
    final today = state.today ?? DateTime.now();

    final picked = await context.push<DateTime>(
      AppRouter.datePath,
      extra: DatePickerArgs(selected: state.date ?? today, today: today),
    );
    if (picked == null) return;

    bloc.add(HomeDateSelected(picked));
  }

  Future<void> _pickPassengers(BuildContext context) async {
    final bloc = context.read<HomeBloc>();

    final picked = await context.push<int>(
      AppRouter.passengersPath,
      extra: state.passengers,
    );
    if (picked == null) return;

    bloc.add(HomePassengersSelected(picked));
  }

  void _search(BuildContext context) {
    final criteria = state.criteria;
    if (criteria == null) return;

    // `push` : la liste des départs ouvre un tunnel de réservation empilé
    // au-dessus de l'accueil, dont on veut pouvoir revenir au formulaire.
    context.push(AppRouter.departuresUrlFor(criteria));
  }
}

/// État de chargement : l'en-tête est déjà affiché (il ne dépend d'aucune
/// donnée), le reste de l'écran est esquissé.
class _HomeLoading extends StatelessWidget {
  const _HomeLoading();

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          height: 210,
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            color: koraColors.surface,
            borderRadius: BorderRadius.circular(19),
            boxShadow: koraColors.cardShadow,
          ),
          alignment: Alignment.center,
          child: const KoraLoadingIndicator(
            variant: KoraLoadingIndicatorVariant.skeleton,
            skeletonLines: 4,
          ),
        ),
        const SectionTitle(title: 'Accès rapides'),
        const KoraLoadingIndicator(
          variant: KoraLoadingIndicatorVariant.skeleton,
          skeletonLines: 3,
        ),
      ],
    );
  }
}

/// État d'erreur : message renvoyé par la couche domain + réessai.
class _HomeError extends StatelessWidget {
  const _HomeError({required this.message, required this.onRetry});

  final String? message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Icon(Icons.cloud_off_rounded, size: 34, color: koraColors.textMuted),
          const SizedBox(height: AppSpacing.md),
          Text(
            'Impossible de charger l’accueil',
            textAlign: TextAlign.center,
            style: AppTextStyles.titleMedium(koraColors.textPrimary),
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            message ?? 'Une erreur inattendue est survenue.',
            textAlign: TextAlign.center,
            style: AppTextStyles.body(koraColors.textMuted),
          ),
          const SizedBox(height: AppSpacing.lg),
          KoraButton(label: 'Réessayer', onPressed: onRetry),
        ],
      ),
    );
  }
}

/// Retour visuel pour les services encore absents du MVP Bus (Ride,
/// notifications) : la maquette les propose depuis l'accueil, mais leurs
/// écrans ne font pas partie du périmètre (voir AGENTS.md).
void _notAvailableYet(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}
