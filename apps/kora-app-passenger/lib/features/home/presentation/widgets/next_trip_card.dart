import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../booking/domain/entities/upcoming_trip.dart';

/// Carte « Prochain voyage » de l'accueil (`.card.navy` dans la maquette) :
/// délai avant départ, siège, trajet, date et heure.
class NextTripCard extends StatelessWidget {
  const NextTripCard({
    super.key,
    required this.trip,
    required this.now,
    required this.onTap,
  });

  final UpcomingTrip trip;

  /// Date de référence pour le calcul « Dans 7 jours » — injectée pour
  /// rester testable (le widget n'appelle jamais `DateTime.now()`).
  final DateTime now;

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final departure = trip.departure;
    final departsAt = departure.departsAt;

    return KoraCard(
      variant: KoraCardVariant.navy,
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              KoraTag(
                label: KoraDateFormats.relativeDays(
                  trip.daysUntilDeparture(now),
                ),
                // Doré dans les deux thèmes : c'est l'information la plus
                // utile de la carte, elle doit accrocher l'œil.
                variant: KoraTagVariant.gold,
              ),
              Flexible(
                child: Text(
                  'Siège ${trip.seat.label}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: AppColors.white.withValues(alpha: 0.8),
                    fontSize: 11,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          Row(
            children: [
              Flexible(
                child: Text(
                  departure.line.origin.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: _routeStyle,
                ),
              ),
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: AppSpacing.sm),
                child: Icon(
                  Icons.arrow_right_alt_rounded,
                  color: AppColors.gold,
                  size: 22,
                ),
              ),
              Flexible(
                child: Text(
                  departure.line.destination.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: _routeStyle,
                ),
              ),
            ],
          ),
          const SizedBox(height: 11),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(
                  '${KoraDateFormats.dayMonth.format(departsAt)} · '
                  '${KoraDateFormats.time.format(departsAt)}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: AppColors.white.withValues(alpha: 0.65),
                    fontSize: 11,
                  ),
                ),
              ),
              const Icon(
                Icons.arrow_forward_rounded,
                color: AppColors.white,
                size: 16,
              ),
            ],
          ),
        ],
      ),
    );
  }

  static const TextStyle _routeStyle = TextStyle(
    color: AppColors.white,
    fontSize: 19,
    fontWeight: FontWeight.w900,
  );
}

/// Version affichée quand l'utilisateur n'a aucun voyage à venir — l'état
/// « empty » de cette section.
class NextTripEmptyCard extends StatelessWidget {
  const NextTripEmptyCard({super.key, this.onSearchTap});

  /// `null` désactive le lien — le formulaire de recherche est incomplet.
  final VoidCallback? onSearchTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Icon(
                Icons.directions_bus_outlined,
                size: 19,
                color: koraColors.textMuted,
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  context.l10n.homeNextTripEmptyTitle,
                  style: AppTextStyles.titleMedium(koraColors.textPrimary),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            'Réservez votre prochain trajet Bus : il apparaîtra ici avec '
            'votre siège et votre billet.',
            style: AppTextStyles.body(koraColors.textMuted),
          ),
          const SizedBox(height: AppSpacing.md),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton(
              onPressed: onSearchTap,
              style: TextButton.styleFrom(
                padding: EdgeInsets.zero,
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: Text(context.l10n.homeSearchSubmit),
            ),
          ),
        ],
      ),
    );
  }
}
