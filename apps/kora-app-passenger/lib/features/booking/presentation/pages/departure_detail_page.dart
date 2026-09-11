import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../domain/entities/amenity.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/group_booking_limits.dart';
import '../../domain/entities/trip_segment.dart';
import '../bloc/departure_detail_cubit.dart';
import '../widgets/amenity_view.dart';
import '../widgets/state_view.dart';
import 'group_booking_page.dart';
import 'seats_page.dart';

/// Données passées à [DepartureDetailPage] par `go_router` (`extra`).
///
/// Le départ est transmis tel quel **et** rechargé : transmis pour que
/// l'écran s'affiche tout de suite, rechargé parce que les places
/// restantes ont pu bouger depuis la recherche. Voir
/// [DepartureDetailCubit].
///
/// Cette route n'est pas prévue pour un lien profond : elle est une étape
/// du tunnel de réservation.
class DepartureDetailArgs {
  const DepartureDetailArgs({
    required this.departure,
    this.passengers = 1,
    this.returnDeparture,
  });

  final Departure departure;
  final int passengers;

  /// Le retour choisi, sur un aller-retour. `null` sur un aller simple.
  ///
  /// Porté jusqu'au devis : les deux trajets tiennent dans un seul devis,
  /// donc un seul blocage et un seul paiement.
  final Departure? returnDeparture;
}

/// Détail d'un départ — écran `departure` de la maquette : bandeau navy du
/// trajet, services à bord, étapes de la ligne, prix par tronçon, puis
/// l'accès au plan des sièges.
///
/// L'écran affiche d'emblée le départ reçu, puis se rafraîchit sur
/// `GET bus/departures/{uuid}` : pas de squelette, jamais — remplacer par
/// un chargement ce qu'on vient de montrer ferait clignoter l'écran pour
/// rien.
class DepartureDetailPage extends StatelessWidget {
  const DepartureDetailPage({
    super.key,
    required this.departure,
    this.passengers = 1,
    this.returnDeparture,
  });

  /// `null` si l'écran a été ouvert sans données (lien profond,
  /// redémarrage à chaud) : il le dit au lieu de planter.
  final Departure? departure;

  final int passengers;

  /// Le retour déjà choisi, sur un aller-retour. Transmis au plan des
  /// sièges, qui en pourvoira un second.
  final Departure? returnDeparture;

  @override
  Widget build(BuildContext context) {
    final departure = this.departure;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: departure == null
              ? const _MissingDeparture()
              : BlocProvider<DepartureDetailCubit>(
                  create: (_) =>
                      DepartureDetailCubit(departure: departure)..refresh(),
                  child:
                      BlocBuilder<DepartureDetailCubit, DepartureDetailState>(
                        builder: (context, state) => _DetailContent(
                          departure: state.departure,
                          passengers: passengers,
                          returnDeparture: returnDeparture,
                          isStale: state.isStale,
                        ),
                      ),
                ),
        ),
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({
    required this.departure,
    required this.passengers,
    this.returnDeparture,
    this.isStale = false,
  });

  final Departure departure;
  final int passengers;

  /// Voir [DepartureDetailPage.returnDeparture].
  final Departure? returnDeparture;

  /// Le rafraîchissement a échoué : ce qui est affiché vient de la
  /// recherche.
  final bool isStale;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final segments = departure.line.segments;

    // Les arrêts tels qu'ils seront dessinés, et non les tronçons dont on
    // les déduit : c'est cette liste-là qui décide d'afficher la section,
    // pour que l'intertitre ne puisse pas coiffer une carte que la frise
    // aurait laissée vide.
    final stops = _stopsOf(departure);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ScreenHeader(
          eyebrow: departure.vehicleLabel,
          title: context.l10n.departureDetailEyebrow,
        ),
        Expanded(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              _TripCard(departure: departure),
              // Discret, et seulement en cas d'échec : ce qui est affiché
              // reste utilisable, il manque juste sa fraîcheur.
              if (isStale) ...[
                const SizedBox(height: AppSpacing.sm),
                KoraCard(
                  variant: KoraCardVariant.mist,
                  child: Row(
                    children: [
                      Icon(
                        Icons.cloud_off_rounded,
                        size: 15,
                        color: koraColors.textMuted,
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          context.l10n.departureDetailStale,
                          style: AppTextStyles.caption(koraColors.textMuted),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              // Titre et grille vont ensemble : un départ sans bus affecté
              // n'annonce aucun service, et un intertitre « Votre confort à
              // bord » suivi de rien laisserait croire à un bus dépouillé
              // plutôt qu'à une donnée pas encore connue.
              if (departure.amenities.isNotEmpty) ...[
                SectionTitle(title: context.l10n.departureDetailComfort),
                _ServiceGrid(amenities: departure.amenities),
              ],
              // Même règle que pour les services : sans arrêt connu, le
              // serveur n'a pas encore d'étapes à donner. L'intertitre
              // « Les étapes » coiffait alors une carte vide, et la note
              // sur les heures de passage parlait d'heures que rien
              // n'affichait — un bas de page qui annonçait une ligne sans
              // arrêts plutôt qu'une donnée manquante.
              if (stops.isNotEmpty) ...[
                SectionTitle(title: context.l10n.departureDetailStops),
                KoraCard(child: _StopsTimeline(stops: stops)),
                if (segments.length > 1) ...[
                  SectionTitle(title: context.l10n.departureDetailSegmentPrice),
                  _SegmentPrices(segments: segments),
                ],
                const SizedBox(height: 11),
                KoraCard(
                  variant: KoraCardVariant.mist,
                  child: Text(
                    context.l10n.departureDetailEstimates,
                    style: AppTextStyles.body(koraColors.textMuted),
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.lg),
          child: KoraButton(
            label: context.l10n.departureDetailChooseSeat,
            onPressed: departure.isSoldOut
                ? null
                // Un groupe passe d'abord par la réservation de groupe
                // (BUS-15) puis par la remise (BUS-16) : les montants
                // n'existent qu'une fois le départ connu, et c'est le
                // dernier moment où les annoncer sert encore à décider. En
                // dessous du seuil, on va droit aux sièges.
                : () => context.push(
                    passengers >= kGroupMinimumPassengers
                        ? AppRouter.groupBookingPath
                        : AppRouter.seatsPath,
                    extra: passengers >= kGroupMinimumPassengers
                        ? GroupBookingArgs(
                            departure: departure,
                            passengers: passengers,
                          )
                        : SeatsArgs(
                            departure: departure,
                            passengers: passengers,
                            returnDeparture: returnDeparture,
                          ),
                  ),
          ),
        ),
      ],
    );
  }
}

/// `.card.navy` de l'écran : places restantes, trajet en grand, date et
/// prix.
class _TripCard extends StatelessWidget {
  const _TripCard({required this.departure});

  final Departure departure;

  @override
  Widget build(BuildContext context) {
    final line = departure.line;

    return KoraCard(
      variant: KoraCardVariant.navy,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          KoraTag(
            label: departure.isSoldOut
                ? context.l10n.departureDetailFull
                : context.l10n.departureDetailSeatsLeft(
                    departure.seatsAvailable,
                  ),
            variant: departure.isSoldOut
                ? KoraTagVariant.danger
                : KoraTagVariant.gold,
          ),
          const SizedBox(height: 23),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _TimePoint(
                time: KoraDateFormats.time.format(departure.departsAt),
                city: line.origin.name,
                alignEnd: false,
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  children: [
                    Text(
                      KoraDateFormats.approximateDuration(line.duration),
                      style: TextStyle(
                        color: AppColors.white.withValues(alpha: 0.55),
                        fontSize: 10,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Container(
                      height: 2,
                      color: AppColors.white.withValues(alpha: 0.2),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              _TimePoint(
                time: KoraDateFormats.time.format(departure.arrivesAt),
                city: line.destination.name,
                alignEnd: true,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(
                  KoraDateFormats.capitalize(
                    KoraDateFormats.fullDate.format(departure.departsAt),
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: AppColors.white.withValues(alpha: 0.65),
                    fontSize: 11,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Text(
                departure.price.formatted,
                style: const TextStyle(
                  color: AppColors.white,
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TimePoint extends StatelessWidget {
  const _TimePoint({
    required this.time,
    required this.city,
    required this.alignEnd,
  });

  final String time;
  final String city;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          time,
          style: const TextStyle(
            color: AppColors.white,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          city,
          style: TextStyle(
            color: AppColors.white.withValues(alpha: 0.6),
            fontSize: 9,
          ),
        ),
      ],
    );
  }
}

/// `.quick-grid` : une tuile par service à bord.
///
/// En lignes de trois, et non en une seule rangée : le serveur annonce
/// jusqu'à vingt-huit services pour un même bus, là où la maquette n'en
/// montrait que trois. Une `Row` les aurait comprimés jusqu'à l'illisible.
class _ServiceGrid extends StatelessWidget {
  const _ServiceGrid({required this.amenities});

  final List<Amenity> amenities;

  /// Tuiles par ligne, et écart entre elles.
  static const int _columns = 3;
  static const double _gap = 10;

  @override
  Widget build(BuildContext context) {
    if (amenities.isEmpty) return const SizedBox.shrink();

    return LayoutBuilder(
      builder: (context, constraints) {
        // Largeur d'une tuile pour que trois tiennent exactement, gouttières
        // comprises. Sans ce calcul, `Wrap` n'en placerait que deux et
        // laisserait un vide à droite.
        final width = (constraints.maxWidth - _gap * (_columns - 1)) / _columns;

        return Wrap(
          spacing: _gap,
          runSpacing: _gap,
          children: [
            for (final amenity in amenities)
              SizedBox(
                width: width,
                child: _ServiceTile(
                  label: amenity.displayLabel(context),
                  icon: amenity.icon,
                ),
              ),
          ],
        );
      },
    );
  }
}

class _ServiceTile extends StatelessWidget {
  const _ServiceTile({required this.label, required this.icon});

  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      constraints: const BoxConstraints(minHeight: 88),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 13),
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: koraColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Icon(icon, size: 20, color: context.koraTitleColor),
          Text(
            label,
            maxLines: 2,
            style: TextStyle(
              color: context.koraTitleColor,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

/// Les arrêts d'un départ, avec l'heure de passage de chacun.
///
/// Ils se déduisent des tronçons de la ligne : un arrêt par origine de
/// tronçon, plus le terminus. Une ligne dont le serveur n'a pas publié les
/// tronçons n'a donc aucun arrêt à montrer — c'est le cas courant tant que
/// `bus/itineraries` ne les renvoie pas.
List<({String label, DateTime time})> _stopsOf(Departure departure) {
  final segments = departure.line.segments;
  if (segments.isEmpty) return const [];

  return [
    for (final segment in segments)
      (
        label: segment.origin.name,
        time: departure.departsAt.add(segment.departureOffset),
      ),
    (
      label: segments.last.destination.name,
      time: departure.departsAt.add(segments.last.arrivalOffset),
    ),
  ];
}

/// `miniTimeline` : les arrêts de la ligne avec leur heure de passage.
class _StopsTimeline extends StatelessWidget {
  const _StopsTimeline({required this.stops});

  final List<({String label, DateTime time})> stops;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (var i = 0; i < stops.length; i++)
          _TimelineItem(
            label: stops[i].label,
            time: KoraDateFormats.time.format(stops[i].time),
            // Seule l'heure de départ est ferme ; les suivantes sont
            // estimées, ce que dit aussi la note en bas d'écran.
            isFirst: i == 0,
            isLast: i == stops.length - 1,
          ),
      ],
    );
  }
}

class _TimelineItem extends StatelessWidget {
  const _TimelineItem({
    required this.label,
    required this.time,
    required this.isFirst,
    required this.isLast,
  });

  final String label;
  final String time;
  final bool isFirst;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Pastille de l'arrêt et trait qui rejoint le suivant.
          Column(
            children: [
              Container(
                width: 11,
                height: 11,
                margin: const EdgeInsets.only(top: 3),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: isFirst ? AppColors.gold : Colors.transparent,
                  border: Border.all(
                    color: isFirst ? AppColors.gold : koraColors.line,
                    width: 3,
                  ),
                ),
              ),
              if (!isLast)
                Expanded(child: Container(width: 2, color: koraColors.line)),
            ],
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : AppSpacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    label,
                    style: AppTextStyles.titleMedium(context.koraTitleColor),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    isFirst ? '$time · départ confirmé' : '≈ $time · estimée',
                    style: TextStyle(color: koraColors.textMuted, fontSize: 10),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Le tarif de chaque portion, pour qui descend avant le terminus.
class _SegmentPrices extends StatelessWidget {
  const _SegmentPrices({required this.segments});

  final List<TripSegment> segments;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      padding: EdgeInsets.zero,
      child: Column(
        children: [
          for (final segment in segments)
            Container(
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.lg,
                vertical: AppSpacing.md,
              ),
              decoration: BoxDecoration(
                border: segment == segments.last
                    ? null
                    : Border(bottom: BorderSide(color: koraColors.line)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${segment.origin.name} → ${segment.destination.name}',
                          style: TextStyle(
                            color: context.koraTitleColor,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${segment.distanceKm} km · '
                          '${KoraDateFormats.approximateDuration(segment.duration)}',
                          style: TextStyle(
                            color: koraColors.textMuted,
                            fontSize: 10,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    segment.price.formatted,
                    style: TextStyle(
                      color: context.koraTitleColor,
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
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

/// Écran ouvert sans départ à afficher.
class _MissingDeparture extends StatelessWidget {
  const _MissingDeparture();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        ScreenHeader(
          eyebrow: context.l10n.departuresEyebrow,
          title: context.l10n.departureDetailMissingHeader,
        ),
        StateView(
          icon: Icons.help_outline_rounded,
          title: context.l10n.departureDetailMissingTitle,
          message: context.l10n.departureDetailMissingText,
          primaryLabel: context.l10n.departureDetailBack,
          onPrimary: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }
}
