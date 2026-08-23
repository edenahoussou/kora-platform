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
          eyebrow: _criteriaLabel(context, state.criteria),
          title: context.l10n.departuresCount(count),
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
      vehicleLabel: departure.vehicleLabel,
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
