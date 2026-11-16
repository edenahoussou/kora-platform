import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../domain/entities/amenity.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/group_booking_limits.dart';
import '../../domain/entities/itinerary.dart';
import '../../domain/entities/journey.dart';
import '../../domain/entities/line_stop.dart';
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
    required this.itinerary,
    this.passengers = 1,
    this.returnItinerary,
  });

  /// Le voyage choisi, direct ou à correspondance.
  final Itinerary itinerary;
  final int passengers;

  /// Le retour choisi, sur un aller-retour. `null` sur un aller simple.
  ///
  /// Porté jusqu'au devis : les deux sens tiennent dans un seul devis,
  /// donc un seul blocage et un seul paiement.
  final Itinerary? returnItinerary;
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
    required this.itinerary,
    this.passengers = 1,
    this.returnItinerary,
  });

  /// `null` si l'écran a été ouvert sans données (lien profond,
  /// redémarrage à chaud) : il le dit au lieu de planter.
  final Itinerary? itinerary;

  final int passengers;

  /// Le retour déjà choisi, sur un aller-retour. Transmis au plan des
  /// sièges, qui en pourvoira un second.
  final Itinerary? returnItinerary;

  @override
  Widget build(BuildContext context) {
    final itinerary = this.itinerary;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: itinerary == null
              ? const _MissingDeparture()
              : BlocProvider<DepartureDetailCubit>(
                  create: (_) => DepartureDetailCubit(
                    itinerary: itinerary,
                    repository: getIt(),
                  )..loadStops(),
                  child:
                      BlocBuilder<DepartureDetailCubit, DepartureDetailState>(
                        builder: (context, state) => _DetailContent(
                          itinerary: state.itinerary,
                          stopsByLine: state.stopsByLine,
                          passengers: passengers,
                          returnItinerary: returnItinerary,
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
    required this.itinerary,
    required this.passengers,
    this.stopsByLine = const {},
    this.returnItinerary,
    this.isStale = false,
  });

  final Itinerary itinerary;

  /// Les arrêts de chaque ligne empruntée, tels que
  /// `GET bus/lines/{uuid}` les rend.
  final Map<String, List<LineStop>> stopsByLine;

  final int passengers;

  /// Voir [DepartureDetailPage.returnItinerary].
  final Itinerary? returnItinerary;

  /// Le rafraîchissement a échoué : ce qui est affiché vient de la
  /// recherche.
  final bool isStale;

  /// Ce qu'on montre d'un tronçon : les services de son bus, les étapes
  /// de sa ligne et ses tarifs.
  ///
  /// Titre et contenu vont toujours ensemble : un bus pas encore affecté
  /// n'annonce aucun service, et un intertitre « Votre confort à bord »
  /// suivi de rien laisserait croire à un bus dépouillé plutôt qu'à une
  /// donnée pas encore connue. Même règle pour les étapes — deux arrêts,
  /// ce sont les deux terminus, et la frise répéterait le bandeau du
  /// haut sans rien apprendre.
  /// Les étapes du **tronçon retenu**, et non celles de la ligne
  /// entière.
  ///
  /// Une ligne va souvent plus loin que le trajet qu'on prend : sur la
  /// correspondance Cotonou → Bohicon → Natitingou, la frise du premier
  /// bus annonçait « Malanville · arrivée prévue 07:31 » — le terminus
  /// de la ligne, à l'heure où l'on descend à Bohicon.
  List<LineStop> _stopsOfLeg(Departure leg) {
    final stops = stopsByLine[leg.line.id] ?? const <LineStop>[];

    final from = stops.indexWhere((stop) => stop.label == leg.line.origin.name);
    final to = stops.lastIndexWhere(
      (stop) => stop.label == leg.line.destination.name,
    );

    // Arrêts non reconnus : mieux vaut la ligne entière que rien.
    if (from < 0 || to <= from) return stops;

    return stops.sublist(from, to + 1);
  }

  List<Widget> _legSections(BuildContext context, Departure leg) {
    final l10n = context.l10n;
    final stops = _stopsOfLeg(leg);
    final segments = leg.line.segments;

    return [
      if (leg.amenities.isNotEmpty) ...[
        SectionTitle(title: l10n.departureDetailComfort),
        _ServiceGrid(amenities: leg.amenities),
      ],
      if (stops.length > 2) ...[
        SectionTitle(title: l10n.departureDetailStops),
        KoraCard(
          child: _StopsTimeline(stops: stops, departure: leg),
        ),
        if (segments.length > 1) ...[
          SectionTitle(title: l10n.departureDetailSegmentPrice),
          _SegmentPrices(segments: segments),
        ],
        const SizedBox(height: 11),
        KoraCard(
          variant: KoraCardVariant.mist,
          child: Text(
            l10n.departureDetailEstimates,
            style: AppTextStyles.body(context.koraColors.textMuted),
          ),
        ),
      ],
    ];
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ScreenHeader(
          eyebrow: itinerary.first.vehicleLabel,
          title: l10n.departureDetailEyebrow,
        ),
        Expanded(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              _TripCard(itinerary: itinerary),
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
              // Un tronçon après l'autre. Sur un trajet direct, il n'y
              // en a qu'un et l'écran est celui d'avant ; sur une
              // correspondance, chaque bus a sa section, et le temps
              // d'attente les sépare — c'est ce qu'il faut savoir avant
              // de réserver, pas en descendant du premier bus.
              for (var index = 0; index < itinerary.legs.length; index++) ...[
                if (!itinerary.isDirect) ...[
                  if (index > 0)
                    _ConnectionWait(
                      city: itinerary.legs[index].line.origin.name,
                      wait: itinerary.connectionWaits[index - 1],
                    ),
                  SectionTitle(
                    title: l10n.departuresLegOrder(
                      index + 1,
                      itinerary.legs.length,
                    ),
                  ),
                  _LegCard(leg: itinerary.legs[index]),
                ],
                ..._legSections(context, itinerary.legs[index]),
              ],
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.lg),
          child: KoraButton(
            label: l10n.departureDetailChooseSeat,
            onPressed: itinerary.isSoldOut
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
                            itinerary: itinerary,
                            passengers: passengers,
                          )
                        : SeatsArgs(
                            journey: Journey(
                              outbound: itinerary,
                              inbound: returnItinerary,
                            ),
                            passengers: passengers,
                          ),
                  ),
          ),
        ),
      ],
    );
  }
}

/// Le bus d'un tronçon : ses heures, ses villes, sa ligne et ses places.
///
/// Sur une correspondance, la carte navy du haut parle du voyage entier ;
/// celle-ci dit ce qu'on prend réellement, bus après bus.
class _LegCard extends StatelessWidget {
  const _LegCard({required this.leg});

  final Departure leg;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final l10n = context.l10n;

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  '${KoraDateFormats.time.format(leg.departsAt)} '
                  '${leg.line.origin.name} → '
                  '${KoraDateFormats.time.format(leg.arrivesAt)} '
                  '${leg.line.destination.name}',
                  style: TextStyle(
                    color: context.koraTitleColor,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              KoraTag(
                label: leg.isSoldOut
                    ? l10n.departureDetailFull
                    : l10n.departureDetailSeatsLeft(leg.seatsAvailable),
                variant: leg.isSoldOut
                    ? KoraTagVariant.danger
                    : KoraTagVariant.gold,
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            [leg.vehicleLabel, ?leg.line.code, leg.price.formatted].join(' · '),
            style: AppTextStyles.caption(koraColors.textMuted),
          ),
        ],
      ),
    );
  }
}

/// Le temps passé à l'arrêt entre deux bus.
///
/// Affiché entre les deux tronçons, là où il se vit. C'est la donnée qui
/// décide d'une correspondance : vingt minutes et trois heures ne se
/// réservent pas avec la même tranquillité.
class _ConnectionWait extends StatelessWidget {
  const _ConnectionWait({required this.city, required this.wait});

  final String city;
  final Duration wait;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.md),
      child: KoraCard(
        variant: KoraCardVariant.mist,
        child: Row(
          children: [
            const Icon(
              Icons.alt_route_rounded,
              size: 16,
              color: AppColors.gold,
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Text(
                context.l10n.departuresConnectionWait(
                  KoraDateFormats.approximateDuration(wait),
                  city,
                ),
                style: AppTextStyles.body(context.koraColors.textMuted),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// `.card.navy` de l'écran : places restantes, trajet en grand, date et
/// prix.
///
/// Elle décrit le **voyage entier** : l'heure du premier bus, celle
/// d'arrivée du dernier, la durée attentes comprises et le prix total.
/// Sur une correspondance, une ligne dorée dit où l'on change — le
/// détail de chaque bus suit en dessous.
class _TripCard extends StatelessWidget {
  const _TripCard({required this.itinerary});

  final Itinerary itinerary;

  @override
  Widget build(BuildContext context) {
    return KoraCard(
      variant: KoraCardVariant.navy,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              KoraTag(
                label: itinerary.isSoldOut
                    ? context.l10n.departureDetailFull
                    : context.l10n.departureDetailSeatsLeft(
                        itinerary.seatsAvailable,
                      ),
                variant: itinerary.isSoldOut
                    ? KoraTagVariant.danger
                    : KoraTagVariant.gold,
              ),
              if (!itinerary.isDirect) ...[
                const SizedBox(width: AppSpacing.sm),
                Flexible(
                  child: KoraTag(
                    // Le nombre seul : avec les villes, le badge
                    // débordait (« 1 CORRESPONDANCE · BOHI… »), et elles
                    // se lisent juste en dessous, tronçon par tronçon.
                    label: context.l10n.departuresConnectionsTag(
                      itinerary.connectionsCount,
                    ),
                  ),
                ),
              ],
            ],
          ),
          const SizedBox(height: 23),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _TimePoint(
                time: KoraDateFormats.time.format(itinerary.departsAt),
                city: itinerary.origin.name,
                alignEnd: false,
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  children: [
                    Text(
                      KoraDateFormats.approximateDuration(itinerary.duration),
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
                time: KoraDateFormats.time.format(itinerary.arrivesAt),
                city: itinerary.destination.name,
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
                    KoraDateFormats.fullDate.format(itinerary.departsAt),
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
                itinerary.total.formatted,
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

/// `miniTimeline` : les arrêts de la ligne, dans l'ordre du trajet.
///
/// **Le serveur ne publie aucune heure de passage intermédiaire** —
/// `GET bus/lines/{uuid}` rend le rang et la distance depuis l'origine,
/// rien de plus. On date donc les deux terminus, qu'on connaît par le
/// départ, et on situe les escales au kilomètre. Interpoler des horaires
/// sur la distance en inventerait, et c'est précisément ce qu'un voyageur
/// qui descend en route ne doit pas croire.
class _StopsTimeline extends StatelessWidget {
  const _StopsTimeline({required this.stops, required this.departure});

  final List<LineStop> stops;
  final Departure departure;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Column(
      children: [
        for (var i = 0; i < stops.length; i++)
          _TimelineItem(
            label: stops[i].label,
            detail: switch (i) {
              0 => l10n.departureDetailStopDeparture(
                KoraDateFormats.time.format(departure.departsAt),
              ),
              _ when i == stops.length - 1 => l10n.departureDetailStopArrival(
                KoraDateFormats.time.format(departure.arrivesAt),
              ),
              _ => _distanceOf(stops[i], l10n),
            },
            isFirst: i == 0,
            isLast: i == stops.length - 1,
          ),
      ],
    );
  }

  /// « à 128 km du départ », ou rien quand la distance manque.
  String? _distanceOf(LineStop stop, AppLocalization l10n) {
    final km = stop.kmFromOrigin;
    if (km == null || km <= 0) return null;

    return l10n.departureDetailStopDistance(km.round());
  }
}

class _TimelineItem extends StatelessWidget {
  const _TimelineItem({
    required this.label,
    required this.detail,
    required this.isFirst,
    required this.isLast,
  });

  final String label;

  /// L'heure pour un terminus, la distance pour une escale, `null` quand
  /// le serveur n'en dit rien.
  final String? detail;

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
                  if (detail != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      detail!,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 10,
                      ),
                    ),
                  ],
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
