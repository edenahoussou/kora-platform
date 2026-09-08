import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/search_criteria.dart';
import '../bloc/departures_bloc.dart';
import '../widgets/amenity_view.dart';
import '../widgets/departure_filter_bar.dart';
import '../widgets/departure_skeleton.dart';
import '../widgets/price_assurance_banner.dart';
import '../widgets/state_view.dart';
import 'departure_detail_page.dart';

/// Liste des départs — écrans `searching`, `results` et `no-results` de la
/// maquette.
///
/// **Un seul écran pour ces trois vues**, là où la maquette en déclare
/// trois : ce sont les états successifs d'une même recherche. Les séparer
/// en trois routes obligerait à naviguer artificiellement à la fin du
/// chargement, et le retour depuis les résultats ramènerait sur un écran de
/// recherche terminée. Le parcours vu par l'utilisateur est identique :
/// squelettes pendant la recherche, puis les résultats.
class DeparturesPage extends StatelessWidget {
  const DeparturesPage({
    super.key,
    this.originId,
    this.destinationId,
    this.date,
    this.passengers,
  });

  /// Paramètres bruts de l'URL — voir `AppRouter.departuresUrlFor`.
  final String? originId;
  final String? destinationId;
  final String? date;
  final String? passengers;

  @override
  Widget build(BuildContext context) {
    return BlocProvider<DeparturesBloc>(
      create: (_) => getIt<DeparturesBloc>()
        ..add(
          DeparturesRequested(
            originId: originId,
            destinationId: destinationId,
            date: date,
            passengers: passengers,
          ),
        ),
      child: const _DeparturesView(),
    );
  }
}

class _DeparturesView extends StatelessWidget {
  const _DeparturesView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          // Une actualisation ratée ne remplace pas la liste : elle se dit
          // au passage, en laissant les résultats précédents à l'écran.
          child: BlocListener<DeparturesBloc, DeparturesState>(
            listenWhen: (previous, current) =>
                current.refreshError != null &&
                previous.refreshError != current.refreshError,
            listener: (context, state) {
              ScaffoldMessenger.of(context)
                ..hideCurrentSnackBar()
                ..showSnackBar(
                  SnackBar(content: Text(context.l10n.departuresRefreshFailed)),
                );
            },
            child: BlocBuilder<DeparturesBloc, DeparturesState>(
              builder: (context, state) => switch (state.status) {
                DeparturesStatus.loading => _SearchingView(state: state),
                DeparturesStatus.failure => _FailureView(state: state),
                DeparturesStatus.success => _Refreshable(
                  child: switch (state) {
                    _ when state.isEmpty => _EmptyView(state: state),
                    _ when state.isFilteredEmpty => _FilteredEmptyView(
                      state: state,
                    ),
                    _ => _ResultsView(state: state),
                  },
                ),
              },
            ),
          ),
        ),
      ),
    );
  }
}

/// Le tiré-pour-actualiser des écrans de résultats.
///
/// Il enveloppe aussi les listes **vides** : les places et les départs
/// bougent d'une minute à l'autre, et un écran « aucun départ » est
/// justement celui qu'on a envie de retirer vers le bas. Les listes qu'il
/// contient passent donc en `AlwaysScrollableScrollPhysics`, faute de quoi
/// un contenu trop court n'accepterait aucun geste.
///
/// La `Future` rendue à [RefreshIndicator] attend la fin réelle de
/// l'appel : sans elle l'indicateur disparaîtrait aussitôt lâché, laissant
/// croire à une actualisation instantanée.
class _Refreshable extends StatelessWidget {
  const _Refreshable({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final bloc = context.read<DeparturesBloc>();

    return RefreshIndicator(
      color: AppColors.kora,
      backgroundColor: context.koraColors.surface,
      onRefresh: () async {
        bloc.add(const DeparturesRefreshed());
        await bloc.stream.firstWhere((state) => !state.isRefreshing);
      },
      child: child,
    );
  }
}

/// Écran `searching` : le trajet cherché, une explication, la barre de
/// progression et trois cartes fantômes.
class _SearchingView extends StatelessWidget {
  const _SearchingView({required this.state});

  final DeparturesState state;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          // Le trajet n'est connu qu'une fois les villes résolues : avant,
          // on n'affiche pas un surtitre vide.
          eyebrow:
              _routeLabel(state.criteria) ?? context.l10n.departuresEyebrow,
          title: context.l10n.departuresSearchingTitle,
        ),
        StateView(
          icon: Icons.trending_up_rounded,
          title: context.l10n.departuresSearchingLead,
          message: context.l10n.departuresSearchingText,
          showLoader: true,
        ),
        const SizedBox(height: AppSpacing.sm),
        for (var i = 0; i < 3; i++) ...[
          const DepartureSkeleton(),
          const SizedBox(height: 10),
        ],
        const SizedBox(height: AppSpacing.xl),
      ],
    );
  }
}

/// Écran `results` : rappel des critères, garantie de prix, tris et
/// filtres, puis les cartes de départ.
class _ResultsView extends StatelessWidget {
  const _ResultsView({required this.state});

  final DeparturesState state;

  @override
  Widget build(BuildContext context) {
    final departures = state.visibleDepartures;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow: _criteriaLabel(context, state.criteria),
          // Le compte reste le titre, et compte **tous** les départs de la
          // liaison : le récapitulatif juste en dessous dit aussitôt
          // combien les filtres en retiennent, donc le titre n'a pas à
          // suivre les filtres pour rester honnête.
          title: context.l10n.departuresCount(state.totalAvailableCount),
          action: _FiltersButton(state: state),
        ),
        const PriceAssuranceBanner(),
        if (state.hasFilters) ...[
          const SizedBox(height: AppSpacing.md),
          _FiltersRecap(state: state),
        ],
        const SizedBox(height: AppSpacing.md),
        for (final departure in departures) ...[
          _DepartureTile(
            departure: departure,
            passengers: state.criteria?.passengers ?? 1,
          ),
          const SizedBox(height: 10),
        ],
        const SizedBox(height: AppSpacing.xl),
      ],
    );
  }
}

/// La barre de tri et de filtres, câblée sur le Bloc.
class _FilterBar extends StatelessWidget {
  const _FilterBar({required this.state});

  final DeparturesState state;

  @override
  Widget build(BuildContext context) {
    final bloc = context.read<DeparturesBloc>();

    return DepartureFilterBar(
      sortField: state.sortField,
      sortDescending: state.sortDescending,
      timeSlots: state.timeSlots,
      onSortTapped: (field) => bloc.add(DeparturesSortChanged(field)),
      onTimeSlotTapped: (slot) => bloc.add(DeparturesTimeSlotToggled(slot)),
    );
  }
}

/// L'icône qui ouvre les tris et filtres, avec sa pastille.
///
/// La pastille n'est pas décorative. Les puces rangées dans une feuille,
/// plus rien à l'écran ne dit qu'un créneau est actif : une liste amputée
/// de moitié passerait pour la liste complète, et le voyageur conclurait
/// qu'il n'y a pas d'autre départ.
class _FiltersButton extends StatelessWidget {
  const _FiltersButton({required this.state});

  final DeparturesState state;

  @override
  Widget build(BuildContext context) {
    return KoraIconButton(
      icon: Icons.tune_rounded,
      showBadge: state.hasFilters,
      onTap: () => _openFilters(context),
    );
  }
}

/// Ce que les filtres retiennent, juste au-dessus des cartes.
///
/// « 2 départs ce matin, de 12 000 à 18 000 FCFA ». Les puces rangées
/// dans une feuille, une liste amputée ressemble à la liste complète :
/// la pastille de l'icône signale qu'un filtre tourne, cette phrase dit
/// **lequel**, et ce qu'il laisse. Sans elle, il faudrait rouvrir la
/// feuille pour se rappeler ce qu'on avait demandé.
///
/// Affichée seulement quand un filtre est actif : sans filtre elle
/// répéterait le titre.
class _FiltersRecap extends StatelessWidget {
  const _FiltersRecap({required this.state});

  final DeparturesState state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            l10n.departuresRecap(
              state.availableCount,
              _slotsLabel(context, state.timeSlots),
              _pricesLabel(context, state.visiblePriceRange),
            ),
            style: AppTextStyles.body(koraColors.textPrimary),
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        // Les filtres repliés, le retour à la liste complète doit rester
        // à portée : sans ce raccourci, défaire ce que la pastille
        // signale demanderait de rouvrir la feuille pour y décocher.
        GestureDetector(
          onTap: () => context.read<DeparturesBloc>().add(
            const DeparturesFiltersCleared(),
          ),
          behavior: HitTestBehavior.opaque,
          child: Text(
            l10n.departuresFiltersClear,
            style: AppTextStyles.caption(AppColors.digital),
          ),
        ),
      ],
    );
  }
}

/// `ce matin`, ou `ce matin et cet après-midi` — jamais une liste vide,
/// le récapitulatif ne s'affichant que sous filtre.
String _slotsLabel(BuildContext context, Set<DepartureTimeSlot> slots) {
  final l10n = context.l10n;

  // L'ordre suit la journée, pas celui des appuis : « cet après-midi et
  // ce matin » se lit comme une erreur.
  final ordered = DepartureTimeSlot.values.where(slots.contains);

  return ordered
      .map(
        (slot) => switch (slot) {
          DepartureTimeSlot.morning => l10n.departuresRecapMorning,
          DepartureTimeSlot.afternoon => l10n.departuresRecapAfternoon,
          DepartureTimeSlot.evening => l10n.departuresRecapEvening,
        },
      )
      .join(l10n.departuresRecapSlotsJoin);
}

/// `de 12 000 à 18 000 FCFA`, ou `à 12 000 FCFA` quand tous les départs
/// retenus sont au même prix — annoncer une fourchette entre deux fois le
/// même montant ferait hésiter sur ce qui varie.
String _pricesLabel(BuildContext context, (Price, Price)? range) {
  final l10n = context.l10n;
  if (range == null) return '';

  final (cheapest, dearest) = range;

  return cheapest.amount == dearest.amount
      ? l10n.departuresRecapPricesFlat(cheapest.formatted)
      : l10n.departuresRecapPricesRange(cheapest.formatted, dearest.formatted);
}

/// Ouvre les tris et filtres en feuille.
///
/// `BlocProvider.value` et non une simple lecture : la feuille est une
/// **route**, poussée sur le Navigator, donc au-dessus du fournisseur
/// installé par [DeparturesPage]. Son contexte n'en hérite pas, et les
/// puces ne trouveraient aucun Bloc à interroger.
Future<void> _openFilters(BuildContext context) {
  final bloc = context.read<DeparturesBloc>();

  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: context.koraColors.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadius.xl)),
    ),
    builder: (_) => BlocProvider<DeparturesBloc>.value(
      value: bloc,
      child: const _FiltersSheet(),
    ),
  );
}

/// La feuille des tris et filtres.
///
/// Aucun bouton « appliquer » : chaque puce agit sur-le-champ, comme
/// avant que ces réglages ne descendent en feuille. Un bouton de
/// validation laisserait croire qu'on peut annuler, alors que rien n'est
/// mis de côté — et obligerait à tenir un état provisoire que le Bloc ne
/// connaît pas. « Voir les résultats » ne fait donc que refermer.
class _FiltersSheet extends StatelessWidget {
  const _FiltersSheet();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          18,
          AppSpacing.md,
          18,
          AppSpacing.md,
        ),
        child: BlocBuilder<DeparturesBloc, DeparturesState>(
          builder: (context, state) => Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 38,
                  height: 4,
                  decoration: BoxDecoration(
                    color: koraColors.line,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      l10n.departuresFiltersTitle,
                      style: AppTextStyles.heading2(koraColors.textPrimary),
                    ),
                  ),
                  if (state.hasFilters)
                    GestureDetector(
                      onTap: () => context.read<DeparturesBloc>().add(
                        const DeparturesFiltersCleared(),
                      ),
                      behavior: HitTestBehavior.opaque,
                      child: Text(
                        l10n.departuresFiltersClear,
                        style: AppTextStyles.caption(AppColors.digital),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              _FilterBar(state: state),
              const SizedBox(height: AppSpacing.xl),
              KoraButton(
                label: l10n.departuresFiltersDone,
                onPressed: () => Navigator.of(context).pop(),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Une carte de résultat. Toutes les mises en forme (heures, durée, prix)
/// se font ici : `DepartureCard` du design system ne reçoit que du texte
/// déjà prêt, ce qui la garde utilisable par n'importe quelle app.
class _DepartureTile extends StatelessWidget {
  const _DepartureTile({required this.departure, required this.passengers});

  final Departure departure;
  final int passengers;

  /// Le pied de carte n'a qu'une ligne, et le serveur peut annoncer
  /// vingt-huit services : on n'en montre que les premiers. L'ordre est
  /// celui du serveur — c'est lui qui range son catalogue, pas l'app.
  static const int _maxServices = 3;

  /// Les services à bord, en une ligne. Vide quand aucun bus n'est encore
  /// affecté au départ : la carte ne montre alors rien plutôt que de
  /// promettre un confort inconnu.
  String _servicesLabel(BuildContext context) => departure.amenities
      .take(_maxServices)
      .map((amenity) => amenity.displayLabel(context))
      .join(' · ');

  @override
  Widget build(BuildContext context) {
    return DepartureCard(
      departureTime: KoraDateFormats.time.format(departure.departsAt),
      arrivalTime: KoraDateFormats.time.format(departure.arrivesAt),
      origin: departure.line.origin.name,
      destination: departure.line.destination.name,
      duration: KoraDateFormats.approximateDuration(departure.line.duration),
      price: departure.price.formatted,
      vehicleLabel: departure.vehicleLabel,
      servicesLabel: _servicesLabel(context),
      seatsAvailable: departure.seatsAvailable,
      // Le total donne au badge sa couleur : 12 places sur 55, c'est
      // orange ; sur 20, c'est vert.
      seatsTotal: departure.seatsTotal,
      isSoldOut: departure.isSoldOut,
      onTap: () => context.push(
        AppRouter.departureDetailPath,
        extra: DepartureDetailArgs(
          departure: departure,
          passengers: passengers,
        ),
      ),
    );
  }
}

/// Écran `no-results` : la liaison n'est pas desservie ce jour-là.
class _EmptyView extends StatelessWidget {
  const _EmptyView({required this.state});

  final DeparturesState state;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow:
              _routeLabel(state.criteria) ?? context.l10n.departuresEyebrow,
          title: context.l10n.departuresEmptyEyebrow,
        ),
        StateView(
          icon: Icons.search_rounded,
          title: context.l10n.departuresEmptyTitle,
          message: context.l10n.departuresEmptyText,
          primaryLabel: context.l10n.departuresEditSearch,
          onPrimary: () => Navigator.of(context).pop(),
        ),
        const SizedBox(height: AppSpacing.xl),
      ],
    );
  }
}

/// Des départs existent, mais aucun ne passe les filtres.
///
/// L'icône des filtres **reste dans l'en-tête**, et l'action principale
/// vide les créneaux d'un geste : la liaison est desservie, la correction
/// tient à un filtre. Renvoyer vers le formulaire de recherche, comme le
/// fait [_EmptyView], serait ici un détour absurde.
class _FilteredEmptyView extends StatelessWidget {
  const _FilteredEmptyView({required this.state});

  final DeparturesState state;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow: _criteriaLabel(context, state.criteria),
          title: context.l10n.departuresFilteredEmptyEyebrow,
          action: _FiltersButton(state: state),
        ),
        StateView(
          icon: Icons.filter_alt_off_rounded,
          title: context.l10n.departuresFilteredEmptyTitle,
          message: context.l10n.departuresFilteredEmptyText,
          primaryLabel: context.l10n.departuresFiltersReset,
          onPrimary: () => context.read<DeparturesBloc>().add(
            const DeparturesFiltersCleared(),
          ),
        ),
        const SizedBox(height: AppSpacing.xl),
      ],
    );
  }
}

/// Erreur technique : on propose de relancer la même recherche.
///
/// « Réessayer » est l'action **principale** parce que c'est presque
/// toujours la bonne : la cause la plus fréquente est une connexion
/// perdue, et la recherche saisie est encore valable. Revenir au
/// formulaire ne vient qu'en second, pour qui veut vraiment changer de
/// trajet.
class _FailureView extends StatelessWidget {
  const _FailureView({required this.state});

  final DeparturesState state;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow:
              _routeLabel(state.criteria) ?? context.l10n.departuresEyebrow,
          title: context.l10n.departuresErrorEyebrow,
        ),
        StateView(
          icon: Icons.cloud_off_rounded,
          title: context.l10n.departuresErrorTitle,
          message: state.errorMessage ?? context.l10n.departuresErrorText,
          primaryLabel: context.l10n.actionRetry,
          onPrimary: () =>
              context.read<DeparturesBloc>().add(const DeparturesRetried()),
          secondaryLabel: context.l10n.departuresEditSearch,
          onSecondary: () => Navigator.of(context).pop(),
        ),
        const SizedBox(height: AppSpacing.xl),
      ],
    );
  }
}

/// `Cotonou → Malanville`, ou `null` tant que les villes ne sont pas
/// résolues.
String? _routeLabel(SearchCriteria? criteria) {
  if (criteria == null) return null;
  return '${criteria.origin.name} → ${criteria.destination.name}';
}

/// `Ven. 14 août · 1 passager` — le rappel des critères en tête des
/// résultats.
String _criteriaLabel(BuildContext context, SearchCriteria? criteria) {
  final l10n = context.l10n;
  if (criteria == null) return l10n.departuresEyebrow;

  final day = KoraDateFormats.capitalize(
    KoraDateFormats.weekdayDayMonthIn(
      criteria.date,
      Localizations.localeOf(context).languageCode,
    ),
  );

  return '$day · ${l10n.departuresPassengers(criteria.passengers)}';
}
