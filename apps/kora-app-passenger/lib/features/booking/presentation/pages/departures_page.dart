import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/search_criteria.dart';
import '../bloc/departures_bloc.dart';
import '../widgets/departure_skeleton.dart';
import '../widgets/departure_sort_chips.dart';
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
          child: BlocBuilder<DeparturesBloc, DeparturesState>(
            builder: (context, state) => switch (state.status) {
              DeparturesStatus.loading => _SearchingView(state: state),
              DeparturesStatus.failure => _FailureView(state: state),
              DeparturesStatus.success when state.isEmpty => _EmptyView(
                state: state,
              ),
              DeparturesStatus.success => _ResultsView(state: state),
            },
          ),
        ),
      ),
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
          eyebrow: _routeLabel(state.criteria) ?? 'KORA BUS',
          title: 'Recherche en cours',
        ),
        const StateView(
          icon: Icons.trending_up_rounded,
          title: 'Nous trouvons votre meilleur départ',
          message:
              'Disponibilités et tarifs sont vérifiés sur chaque segment '
              'du trajet.',
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

/// Écran `results` : rappel des critères, garantie de prix, tris, puis les
/// cartes de départ.
class _ResultsView extends StatelessWidget {
  const _ResultsView({required this.state});

  final DeparturesState state;

  @override
  Widget build(BuildContext context) {
    final departures = state.sortedDepartures;
    final count = state.availableCount;

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow: _criteriaLabel(state.criteria),
          title: count == 1
              ? '1 départ disponible'
              : '$count départs disponibles',
          action: KoraIconButton(
            icon: Icons.tune_rounded,
            onTap: () => Navigator.of(context).pop(),
          ),
        ),
        const PriceAssuranceBanner(),
        const SizedBox(height: AppSpacing.md),
        DepartureSortChips(
          selected: state.sort,
          onChanged: (sort) =>
              context.read<DeparturesBloc>().add(DeparturesSortChanged(sort)),
        ),
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

/// Une carte de résultat. Toutes les mises en forme (heures, durée, prix)
/// se font ici : `DepartureCard` du design system ne reçoit que du texte
/// déjà prêt, ce qui la garde utilisable par n'importe quelle app.
class _DepartureTile extends StatelessWidget {
  const _DepartureTile({required this.departure, required this.passengers});

  final Departure departure;
  final int passengers;

  @override
  Widget build(BuildContext context) {
    return DepartureCard(
      departureTime: KoraDateFormats.time.format(departure.departsAt),
      arrivalTime: KoraDateFormats.time.format(departure.arrivesAt),
      origin: departure.line.origin.name,
      destination: departure.line.destination.name,
      duration: KoraDateFormats.approximateDuration(departure.line.duration),
      price: departure.price.formatted,
      vehicleLabel: departure.comfort.label,
      servicesLabel: departure.amenities.join(' · '),
      seatsAvailable: departure.seatsAvailable,
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
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow: _routeLabel(state.criteria) ?? 'KORA BUS',
          title: 'Aucun départ',
        ),
        StateView(
          icon: Icons.search_rounded,
          title: 'Aucun voyage ce jour-là',
          message:
              'Cette liaison n’est pas desservie à cette date. Essayez '
              'une autre date, ou un autre trajet.',
          primaryLabel: 'Modifier la recherche',
          onPrimary: () => Navigator.of(context).pop(),
        ),
        const SizedBox(height: AppSpacing.xl),
      ],
    );
  }
}

/// Erreur technique : on propose de relancer la même recherche.
class _FailureView extends StatelessWidget {
  const _FailureView({required this.state});

  final DeparturesState state;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow: _routeLabel(state.criteria) ?? 'KORA BUS',
          title: 'Recherche impossible',
        ),
        StateView(
          icon: Icons.cloud_off_rounded,
          title: 'La recherche a échoué',
          message: state.errorMessage ?? 'Une erreur inattendue est survenue.',
          primaryLabel: 'Modifier la recherche',
          onPrimary: () => Navigator.of(context).pop(),
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
String _criteriaLabel(SearchCriteria? criteria) {
  if (criteria == null) return 'KORA BUS';

  final day = KoraDateFormats.capitalize(
    KoraDateFormats.weekdayDayMonth.format(criteria.date),
  );
  final passengers = criteria.passengers == 1
      ? '1 passager'
      : '${criteria.passengers} passagers';

  return '$day · $passengers';
}
