import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../../home/presentation/bloc/home_bloc.dart';
import '../../../home/presentation/pages/city_picker_page.dart';
import '../../../home/presentation/pages/date_picker_page.dart';
import '../../domain/entities/city.dart';
import '../widgets/booking_progress.dart';

/// Écran `bus-search` de la maquette : le formulaire de réservation en
/// grand, atteint depuis « Réserver un voyage ».
///
/// Reprend le [HomeBloc] plutôt qu'un bloc à lui : c'est le même trajet
/// qu'on compose ici et sur l'accueil, avec les mêmes écrans de sélection.
/// Deux blocs séparés donneraient deux recherches divergentes selon
/// l'endroit d'où on est parti.
class BusSearchPage extends StatelessWidget {
  const BusSearchPage({super.key, this.tripType = TripType.oneway});

  /// L'onglet ouvert à l'arrivée.
  ///
  /// L'accueil mène ici par deux portes : la recherche rapide, qui ne
  /// compose qu'un aller, et la carte « Réserver un aller-retour ». La
  /// seconde serait un détour absurde si elle déposait le voyageur sur
  /// « Aller simple », à recliquer sur l'onglet d'à côté.
  final TripType tripType;

  @override
  Widget build(BuildContext context) {
    return BlocProvider<HomeBloc>(
      create: (_) {
        final bloc = getIt<HomeBloc>()..add(const HomeStarted());
        if (tripType != TripType.oneway) {
          bloc.add(HomeTripTypeChanged(tripType));
        }

        return bloc;
      },
      child: const _BusSearchView(),
    );
  }
}

class _BusSearchView extends StatelessWidget {
  const _BusSearchView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.searchEyebrow,
                title: l10n.searchTitle,
                action: KoraIconButton(
                  icon: Icons.help_outline_rounded,
                  // Le bouton annonçait « Aide » dans un toast, et c'est
                  // tout : le voyageur bloqué sur ce formulaire restait
                  // dessus. Il ouvre maintenant le centre d'aide.
                  onTap: () => context.push(AppRouter.helpPath),
                ),
              ),
              Expanded(
                child: BlocBuilder<HomeBloc, HomeState>(
                  builder: (context, state) => _Form(state: state),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Form extends StatelessWidget {
  const _Form({required this.state});

  final HomeState state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final recent = _recentRoutesOf(state);

    return ListView(
      padding: const EdgeInsets.only(bottom: 100),
      children: [
        _TripTypeSwitch(tripType: state.tripType),
        const SizedBox(height: 12),
        // 5 étapes : trajet, départ, siège, passagers, récapitulatif.
        BookingProgress(step: 1, label: l10n.searchProgressStep),
        const SizedBox(height: 15),
        _RouteCard(state: state),
        const SizedBox(height: 10),
        _MetaGrid(state: state),
        if (state.tripType == TripType.roundtrip) ...[
          const SizedBox(height: 8),
          _ReturnDateCard(state: state),
        ],
        const SizedBox(height: 10),
        const _ComfortNote(),
        const SizedBox(height: 13),
        KoraButton(
          label: state.tripType == TripType.roundtrip
              ? l10n.searchCtaRoundtrip
              : l10n.searchCta,
          icon: Icons.arrow_forward_rounded,
          iconPosition: KoraButtonIconPosition.edges,
          onPressed: () => _search(context),
        ),
        const SizedBox(height: 18),
        // Titre et liste vont ensemble : un compte neuf n'a aucun trajet
        // derrière lui, et l'intertitre « Trajets récents » coiffait alors
        // le vide — un historique annoncé puis introuvable.
        if (recent.isNotEmpty) ...[
          SectionTitle(title: l10n.searchRecent),
          const SizedBox(height: AppSpacing.sm),
          _RecentRoutes(routes: recent),
        ],
      ],
    );
  }

  void _search(BuildContext context) {
    final l10n = context.l10n;

    final criteria = state.criteria;

    // Le trajet n'est pas complet : on le dit plutôt que de laisser le
    // bouton sans effet apparent. Seules les villes retiennent désormais —
    // la date de retour est facultative, et son absence ramène simplement
    // la recherche à l'aller.
    if (criteria == null) {
      KoraToaster.show(context, l10n.searchMissingCities);
      return;
    }

    context.push(AppRouter.departuresUrlFor(criteria));
  }
}

/// `.trip-type-switch` : aller simple / aller-retour.
class _TripTypeSwitch extends StatelessWidget {
  const _TripTypeSwitch({required this.tripType});

  final TripType tripType;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;

    void select(TripType type) =>
        context.read<HomeBloc>().add(HomeTripTypeChanged(type));

    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: koraColors.textPrimary.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        children: [
          Expanded(
            child: _TripTypeButton(
              label: l10n.searchTripOneway,
              isActive: tripType == TripType.oneway,
              onTap: () => select(TripType.oneway),
            ),
          ),
          const SizedBox(width: 4),
          Expanded(
            child: _TripTypeButton(
              label: l10n.searchTripRoundtrip,
              isActive: tripType == TripType.roundtrip,
              onTap: () => select(TripType.roundtrip),
            ),
          ),
        ],
      ),
    );
  }
}

class _TripTypeButton extends StatelessWidget {
  const _TripTypeButton({
    required this.label,
    required this.isActive,
    this.onTap,
  });

  final String label;
  final bool isActive;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Material(
      color: isActive ? koraColors.surface : Colors.transparent,
      borderRadius: BorderRadius.circular(10),
      // `.trip-type-switch button.active { box-shadow: 0 5px 14px }`
      elevation: isActive && !isDark ? 3 : 0,
      shadowColor: AppColors.kora.withValues(alpha: 0.08),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          height: 34,
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              color: isActive
                  ? (isDark ? koraColors.textPrimary : AppColors.kora)
                  : koraColors.textMuted,
              fontSize: 11,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ),
    );
  }
}

/// `.booking-route-card` : la carte navy du trajet, départ au-dessus,
/// arrivée en dessous, reliés par un rail pointillé.
class _RouteCard extends StatelessWidget {
  const _RouteCard({required this.state});

  final HomeState state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    // `.booking-route-card` a son propre fond et son propre biseau, aux
    // valeurs près de ceux de `.card.navy` : on le dessine ici plutôt que
    // de passer par KoraCard, dont le biseau tombe ailleurs.
    return Container(
      clipBehavior: Clip.antiAlias,
      // Le contour passe en `foregroundDecoration` : dans une
      // `BoxDecoration`, il se peint DERRIÈRE l'enfant, et le calque du
      // halo le recouvrait entièrement — la carte se retrouvait sans
      // bords sur le fond navy du thème sombre.
      foregroundDecoration: isDark
          ? BoxDecoration(
              borderRadius: BorderRadius.circular(23),
              border: Border.all(color: koraColors.line),
            )
          : null,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(23),
        // Le fond navy et le halo bleu sont DEUX couches, comme en CSS
        // (`radial-gradient(...), var(--kora)`). Les donner tous deux à
        // la même `BoxDecoration` ne les superpose pas : le gradient
        // remplace la couleur, et la carte perd son navy.
        color: AppColors.kora,
        boxShadow: [
          BoxShadow(
            color: AppColors.kora.withValues(alpha: 0.18),
            offset: const Offset(0, 18),
            blurRadius: 45,
          ),
        ],
      ),
      child: Stack(
        children: [
          // `radial-gradient(circle at 102% 0%, rgba(0,87,184,.28),
          // transparent 34%)` : le halo bleu posé sur le navy.
          const Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: Alignment(1.04, -1),
                  radius: 0.68,
                  colors: [Color(0x470057B8), Colors.transparent],
                ),
              ),
            ),
          ),
          // `.booking-route-card::after` : le biseau doré traverse la
          // carte à hauteur du chevron de l'arrivée.
          Positioned(
            right: -62,
            bottom: 40,
            child: Transform.rotate(
              angle: -25 * 3.1415926535 / 180,
              child: Container(
                width: 190,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.gold.withValues(alpha: 0.72),
                  borderRadius: BorderRadius.circular(AppRadius.pill),
                ),
              ),
            ),
          ),
          // Le retrait est appliqué au contenu, pas à la carte : le halo
          // et le biseau doivent atteindre les bords, sinon ils s'arrêtent
          // net autour du texte.
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      l10n.searchRoute.toUpperCase(),
                      style: AppTextStyles.eyebrow(
                        AppColors.white.withValues(alpha: 0.55),
                      ),
                    ),
                    _SwapButton(
                      onTap: () {
                        context.read<HomeBloc>().add(const HomeRouteSwapped());
                        KoraToaster.show(context, l10n.searchSwapped);
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 7),
                _Location(
                  label: l10n.searchDeparture,
                  city: state.origin,
                  hint: l10n.searchDepartureHint,
                  dotColor: AppColors.white,
                  onTap: () => _pickCity(context, isOrigin: true),
                ),
                const _RouteRail(),
                _Location(
                  label: l10n.searchArrival,
                  city: state.destination,
                  hint: l10n.searchArrivalHint,
                  dotColor: AppColors.gold,
                  onTap: () => _pickCity(context, isOrigin: false),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

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
}

class _SwapButton extends StatelessWidget {
  const _SwapButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.white.withValues(alpha: 0.06),
      borderRadius: BorderRadius.circular(9),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(9),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(9),
            border: Border.all(color: AppColors.white.withValues(alpha: 0.13)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                context.l10n.searchSwap,
                style: const TextStyle(
                  color: AppColors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(width: 3),
              const Icon(
                Icons.swap_vert_rounded,
                size: 13,
                color: AppColors.white,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.booking-location` : pastille, libellé, ville et précision de gare.
class _Location extends StatelessWidget {
  const _Location({
    required this.label,
    required this.city,
    required this.hint,
    required this.dotColor,
    required this.onTap,
  });

  final String label;
  final City? city;
  final String hint;
  final Color dotColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Container(
          constraints: const BoxConstraints(minHeight: 72),
          padding: const EdgeInsets.symmetric(vertical: 9, horizontal: 3),
          child: Row(
            children: [
              // `.station-point` : cercle creux, doré pour l'arrivée.
              Container(
                width: 13,
                height: 13,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: dotColor, width: 3),
                ),
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      label.toUpperCase(),
                      style: AppTextStyles.eyebrow(
                        AppColors.white.withValues(alpha: 0.48),
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      city?.label ?? context.l10n.searchPickCity,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppColors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      hint,
                      style: TextStyle(
                        color: AppColors.white.withValues(alpha: 0.5),
                        fontSize: 10,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(
                Icons.chevron_right_rounded,
                size: 20,
                color: AppColors.gold,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.booking-route-rail` : le trait pointillé qui relie les deux
/// pastilles.
///
/// Aligné sur leur axe (à gauche, pas au centre de la carte) et débordant
/// dans les deux rangées voisines, pour toucher réellement le rond blanc
/// au-dessus et le rond doré en dessous — la maquette le pose en absolu
/// entre les deux, on obtient la même chose en laissant le trait sortir de
/// sa boîte.
class _RouteRail extends StatelessWidget {
  const _RouteRail();

  /// Centre des pastilles : 3px de retrait de rangée + la moitié des 13px
  /// du rond.
  static const double _dotCenter = 9.5;

  @override
  Widget build(BuildContext context) {
    const railHeight = 26.0;
    const overflow = 30.0;

    return SizedBox(
      height: railHeight,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned(
            left: _dotCenter - 0.5,
            top: -overflow,
            bottom: -overflow,
            child: Container(
              width: 1,
              color: AppColors.white.withValues(alpha: 0.25),
            ),
          ),
          Positioned(
            left: _dotCenter - 2.5,
            top: 0,
            bottom: 0,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                for (var i = 0; i < 3; i++)
                  Container(
                    width: 5,
                    height: 5,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.white.withValues(alpha: 0.45),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// `.booking-meta-grid` : date et passagers, côte à côte.
class _MetaGrid extends StatelessWidget {
  const _MetaGrid({required this.state});

  final HomeState state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final date = state.date ?? state.today;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: _MetaTile(
              icon: Icons.calendar_today_rounded,
              label: l10n.searchDate,
              value: date == null
                  ? '—'
                  : KoraDateFormats.weekdayDayMonthIn(date, l10n.languageCode),
              onTap: () => _pickDate(context),
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: _MetaTile(
              icon: Icons.people_alt_outlined,
              label: l10n.searchPassengers,
              value: l10n.searchPassengersCount(state.passengers),
              onTap: () => _pickPassengers(context),
            ),
          ),
        ],
      ),
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
}

class _MetaTile extends StatelessWidget {
  const _MetaTile({
    required this.icon,
    required this.label,
    required this.value,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(17),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(17),
        child: Container(
          constraints: const BoxConstraints(minHeight: 67),
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(17),
            border: Border.all(color: koraColors.line),
          ),
          child: Row(
            children: [
              Container(
                width: 31,
                height: 31,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: koraColors.background,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  icon,
                  size: 16,
                  color: isDark ? koraColors.textPrimary : AppColors.kora,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      label,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 10,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      value,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: isDark ? koraColors.textPrimary : AppColors.kora,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              const Icon(
                Icons.chevron_right_rounded,
                size: 18,
                color: AppColors.digital,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.comfort-note` : le rappel doré de ce qui est compris dans le billet.
class _ComfortNote extends StatelessWidget {
  const _ComfortNote();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: AppColors.gold.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: AppColors.gold.withValues(alpha: 0.24)),
      ),
      child: Row(
        children: [
          Container(
            width: 31,
            height: 31,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AppColors.gold,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(
              Icons.verified_user_outlined,
              size: 17,
              color: AppColors.kora,
            ),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  l10n.searchComfortTitle,
                  style: TextStyle(
                    color: isDark ? koraColors.textPrimary : AppColors.kora,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  l10n.searchComfortText,
                  style: TextStyle(color: koraColors.textMuted, fontSize: 10),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// `.recent-routes` : les dernières liaisons cherchées, en un tap.
/// Les liaisons déjà cherchées, reconstituées depuis les villes récentes.
///
/// Elles arrivent par paires — un départ, une arrivée — et une paire
/// incomplète ne fait pas une liaison. C'est cette liste-là qui décide
/// d'afficher la section, pour que l'intertitre ne puisse pas coiffer un
/// vide que la liste aurait laissé.
List<(City, City)> _recentRoutesOf(HomeState state) {
  final cities = state.recentCities;

  return [
    for (var i = 0; i + 1 < cities.length; i += 2) (cities[i], cities[i + 1]),
  ];
}

class _RecentRoutes extends StatelessWidget {
  const _RecentRoutes({required this.routes});

  final List<(City, City)> routes;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (final route in routes)
          _RecentRoute(origin: route.$1, destination: route.$2),
      ],
    );
  }
}

class _RecentRoute extends StatelessWidget {
  const _RecentRoute({required this.origin, required this.destination});

  final City origin;
  final City destination;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final color = isDark ? koraColors.textPrimary : AppColors.kora;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () {
          final bloc = context.read<HomeBloc>();
          bloc.add(HomeOriginSelected(origin));
          bloc.add(HomeDestinationSelected(destination));
        },
        child: Container(
          constraints: const BoxConstraints(minHeight: 46),
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
          decoration: BoxDecoration(
            border: Border(bottom: BorderSide(color: koraColors.line)),
          ),
          child: Row(
            children: [
              Text(
                origin.name,
                style: TextStyle(
                  color: color,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(width: 9),
              Expanded(child: Container(height: 1, color: koraColors.line)),
              const SizedBox(width: 9),
              Text(
                destination.name,
                style: TextStyle(
                  color: color,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(width: 6),
              Icon(
                Icons.chevron_right_rounded,
                size: 18,
                color: koraColors.textMuted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.return-date-card` : la date du retour, affichée seulement en
/// aller-retour.
class _ReturnDateCard extends StatelessWidget {
  const _ReturnDateCard({required this.state});

  final HomeState state;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final returnDate = state.returnDate;

    return _MetaTile(
      icon: Icons.event_repeat_rounded,
      label: l10n.searchReturnDate,
      value: returnDate == null
          ? l10n.searchReturnPick
          : KoraDateFormats.weekdayDayMonthIn(returnDate, l10n.languageCode),
      onTap: () => _pickReturnDate(context),
    );
  }

  Future<void> _pickReturnDate(BuildContext context) async {
    final bloc = context.read<HomeBloc>();

    // Le retour ne peut pas précéder l'aller : le calendrier s'ouvre sur
    // la date de départ et n'autorise rien avant elle.
    final outbound = state.date ?? state.today ?? DateTime.now();

    final picked = await context.push<DateTime>(
      AppRouter.returnDatePath,
      extra: DatePickerArgs(
        selected: state.returnDate ?? outbound,
        today: outbound,
      ),
    );
    if (picked == null) return;

    bloc.add(HomeReturnDateSelected(picked));
  }
}
