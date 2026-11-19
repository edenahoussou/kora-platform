import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../auth/domain/entities/auth_user.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../booking/domain/entities/city.dart';
import '../../../booking/presentation/pages/bus_search_page.dart';
import '../../../profil/presentation/widgets/birth_date_prompt.dart';
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
/// Les villes viennent de l'API (`bus/cities`) ; ce qui n'a pas encore
/// d'endpoint documenté — prochain voyage, rappels — continue de lire le
/// jeu de démonstration.
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

  /// Le prénom du compte connecté : le premier mot de son nom, comme sur
  /// l'écran « Mes informations ». Vide tant que la session n'est pas
  /// relue — l'accueil salue alors sans nommer personne, plutôt que
  /// d'afficher un prénom qui n'est pas celui de l'utilisateur.
  static String _firstNameOf(AuthUser? user) {
    final name = user?.name.trim() ?? '';
    if (name.isEmpty) return '';

    return name.split(RegExp(r'\s+')).first;
  }

  String _greeting(BuildContext context) {
    final firstName = _firstNameOf(
      context.select<AuthBloc, AuthUser?>((bloc) => bloc.state.session?.user),
    );

    final l10n = context.l10n;

    return firstName.isEmpty
        ? l10n.homeGreetingAnonymous
        : l10n.homeGreeting(firstName);
  }

  @override
  Widget build(BuildContext context) {
    // Le profil sans date de naissance se voit proposer de la
    // renseigner, une fois. L'accueil est le seul écran que tout le
    // monde traverse après la connexion.
    final user = context.select<AuthBloc, AuthUser?>(
      (bloc) => bloc.state.session?.user,
    );

    return BirthDatePromptGate(
      isSignedIn: user != null,
      hasBirthDate: user?.birthDate != null,
      child: Scaffold(
        body: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              HomeHero(
                greeting: _greeting(context),
                // Coupures de ligne explicites, portées par la
                // traduction : la maquette compose ce titre sur 3 lignes,
                // et un retour automatique dépendrait de la largeur.
                title: context.l10n.homeTitle,
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
        SectionTitle(title: context.l10n.homeQuickTitle),
        HomeQuickGrid(
          items: [
            QuickAccessItem(
              label: context.l10n.homeQuickRide,
              highlighted: true,
              icon: const KoraVehicleIcon(
                type: KoraVehicleType.standard,
                size: 24,
                colorFilter: ColorFilter.mode(AppColors.kora, BlendMode.srcIn),
              ),
              // KORA Ride n'a pas d'écran : la tuile l'annonce, sans
              // promettre de l'ouvrir.
              onTap: null,
            ),
            // Colis, comme Ride : la tuile dit ce que KORA fera, sans
            // promettre de l'ouvrir. Son écran attend sa feature, et y
            // mener n'ouvrirait qu'une page vide.
            QuickAccessItem(
              label: context.l10n.homeQuickParcels,
              icon: _quickIcon(context, KoraNavIconType.parcel),
              onTap: null,
            ),
            QuickAccessItem(
              label: context.l10n.homeQuickWallet,
              icon: _quickIcon(context, KoraNavIconType.wallet),
              onTap: () => context.go('/wallet'),
            ),
            QuickAccessItem(
              label: context.l10n.homeQuickTickets,
              icon: _quickIcon(context, KoraNavIconType.tickets),
              onTap: () => context.go('/voyages'),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        const HomeRideBanner(),
        SectionTitle(
          title: context.l10n.homeNextTrip,
          actionLabel: context.l10n.actionSeeAll,
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
          TravelReminder(
            title: context.l10n.homeReminderTitle,
            message: context.l10n.homeReminderText,
          ),
        ],
        // L'accueil ne compose qu'un aller : son formulaire n'a ni date de
        // retour ni bascule, et c'est voulu — la recherche rapide d'un
        // trajet simple est ce qu'on y vient faire. L'aller-retour a sa
        // porte à lui, qui mène au formulaire complet avec le bon onglet
        // déjà choisi.
        SectionTitle(title: context.l10n.homeRoundTripSection),
        TravelReminder(
          icon: Icons.swap_horiz_rounded,
          title: context.l10n.homeRoundTripTitle,
          message: context.l10n.homeRoundTripText,
          onTap: () => context.push(
            AppRouter.busSearchPath,
            extra: const BusSearchArgs(tripType: TripType.roundtrip),
          ),
        ),
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
        SectionTitle(title: context.l10n.homeQuickTitle),
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
            context.l10n.homeErrorTitle,
            textAlign: TextAlign.center,
            style: AppTextStyles.titleMedium(koraColors.textPrimary),
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            message ?? context.l10n.homeErrorText,
            textAlign: TextAlign.center,
            style: AppTextStyles.body(koraColors.textMuted),
          ),
          const SizedBox(height: AppSpacing.lg),
          KoraButton(label: context.l10n.actionRetry, onPressed: onRetry),
        ],
      ),
    );
  }
}
