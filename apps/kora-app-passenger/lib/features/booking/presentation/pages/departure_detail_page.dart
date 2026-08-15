import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/app_router.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/section_title.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/trip_segment.dart';
import '../widgets/state_view.dart';
import 'seats_page.dart';

/// Données passées à [DepartureDetailPage] par `go_router` (`extra`).
///
/// Le départ est transmis tel quel plutôt que rechargé par identifiant :
/// il vient d'être affiché par la liste, le recharger ferait clignoter un
/// écran de chargement pour des données déjà en mémoire. C'est aussi
/// pourquoi cette route n'est pas prévue pour un lien profond — elle est
/// une étape du tunnel de réservation.
class DepartureDetailArgs {
  const DepartureDetailArgs({required this.departure, this.passengers = 1});

  final Departure departure;
  final int passengers;
}

/// Détail d'un départ — écran `departure` de la maquette : bandeau navy du
/// trajet, services à bord, étapes de la ligne, prix par tronçon, puis
/// l'accès au plan des sièges.
///
/// Écran **purement présentationnel** : il reçoit un [Departure] déjà
/// chargé et n'a donc pas de Bloc. En Clean Architecture, un Bloc porte un
/// état à gérer ; ici il n'y en a aucun, en ajouter un n'apporterait que de
/// l'indirection.
class DepartureDetailPage extends StatelessWidget {
  const DepartureDetailPage({
    super.key,
    required this.departure,
    this.passengers = 1,
  });

  /// `null` si l'écran a été ouvert sans données (lien profond,
  /// redémarrage à chaud) : il le dit au lieu de planter.
  final Departure? departure;

  final int passengers;

  @override
  Widget build(BuildContext context) {
    final departure = this.departure;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: departure == null
              ? const _MissingDeparture()
              : _DetailContent(departure: departure, passengers: passengers),
        ),
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({required this.departure, required this.passengers});

  final Departure departure;
  final int passengers;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final segments = departure.line.segments;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ScreenHeader(eyebrow: departure.comfort.label, title: 'Votre départ'),
        Expanded(
          child: ListView(
            padding: EdgeInsets.zero,
            children: [
              _TripCard(departure: departure),
              const SectionTitle(title: 'Votre confort à bord'),
              _ServiceGrid(amenities: departure.amenities),
              const SectionTitle(title: 'Les étapes'),
              KoraCard(child: _StopsTimeline(departure: departure)),
              if (segments.length > 1) ...[
                const SectionTitle(title: 'Prix par tronçon'),
                _SegmentPrices(segments: segments),
              ],
              const SizedBox(height: 11),
              KoraCard(
                variant: KoraCardVariant.mist,
                child: Text(
                  'Les heures de passage sont des estimations actualisées '
                  'pendant le trajet.',
                  style: AppTextStyles.body(koraColors.textMuted),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.lg),
          child: KoraButton(
            label: 'Choisir mon siège',
            onPressed: departure.isSoldOut
                ? null
                : () => context.push(
                    AppRouter.seatsPath,
                    extra: SeatsArgs(
                      departure: departure,
                      passengers: passengers,
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
                ? 'Complet'
                : '${departure.seatsAvailable} places restantes',
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
class _ServiceGrid extends StatelessWidget {
  const _ServiceGrid({required this.amenities});

  final List<String> amenities;

  /// Le jeu de données décrit les services par leur nom ; on leur associe
  /// ici un pictogramme, avec une icône neutre pour tout service inconnu.
  static const Map<String, IconData> _icons = {
    'Wi-Fi': Icons.wifi_rounded,
    'Climatisation': Icons.ac_unit_rounded,
    'USB': Icons.usb_rounded,
  };

  @override
  Widget build(BuildContext context) {
    if (amenities.isEmpty) return const SizedBox.shrink();

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final amenity in amenities) ...[
            Expanded(
              child: _ServiceTile(
                label: amenity,
                icon: _icons[amenity] ?? Icons.check_circle_outline_rounded,
              ),
            ),
            if (amenity != amenities.last) const SizedBox(width: 10),
          ],
        ],
      ),
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

/// `miniTimeline` : les arrêts de la ligne avec leur heure de passage.
class _StopsTimeline extends StatelessWidget {
  const _StopsTimeline({required this.departure});

  final Departure departure;

  @override
  Widget build(BuildContext context) {
    final segments = departure.line.segments;
    if (segments.isEmpty) return const SizedBox.shrink();

    // Un arrêt par origine de tronçon, plus le terminus.
    final stops = <(String, Duration)>[
      for (final segment in segments)
        (segment.origin.name, segment.departureOffset),
      (segments.last.destination.name, segments.last.arrivalOffset),
    ];

    return Column(
      children: [
        for (var i = 0; i < stops.length; i++)
          _TimelineItem(
            label: stops[i].$1,
            time: KoraDateFormats.time.format(
              departure.departsAt.add(stops[i].$2),
            ),
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
        const ScreenHeader(eyebrow: 'KORA BUS', title: 'Départ introuvable'),
        StateView(
          icon: Icons.help_outline_rounded,
          title: 'Ce départ n’est plus disponible',
          message: 'Reprenez la recherche pour voir les départs du jour.',
          primaryLabel: 'Retour aux départs',
          onPrimary: () => Navigator.of(context).pop(),
        ),
      ],
    );
  }
}
