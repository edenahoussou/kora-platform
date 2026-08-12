import 'package:flutter/material.dart';

import '../../colors/app_colors.dart';
import '../../colors/kora_colors.dart';
import '../../dimens/app_dimens.dart';

/// Carte de départ (trajet Bus/Ride) — équivalent `.departure` /
/// `.premium-departure` du prototype web : heure à gauche, ligne de
/// trajet (origine → destination) au centre, prix + action à droite.
/// État sélectionné : bordure et ombre dorées (`.departure.selected`).
///
/// Exemple d'utilisation :
/// ```dart
/// DepartureCard(
///   departureTime: '14:30',
///   origin: 'Cotonou',
///   destination: 'Porto-Novo',
///   price: '2 500 FCFA',
///   seatsAvailable: 12,
///   onTap: () {},
/// )
/// ```
class DepartureCard extends StatelessWidget {
  const DepartureCard({
    super.key,
    required this.departureTime,
    required this.origin,
    required this.destination,
    required this.price,
    this.seatsAvailable,
    this.isSelected = false,
    this.onTap,
  });

  final String departureTime;
  final String origin;
  final String destination;
  final String price;

  /// `null` si non applicable (ex: Ride à la demande, pas de quota de places).
  final int? seatsAvailable;
  final bool isSelected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(
              color: isSelected ? AppColors.gold : koraColors.line,
              width: isSelected ? 1.5 : 1,
            ),
            boxShadow: isSelected
                ? [
                    BoxShadow(
                      color: AppColors.gold.withValues(alpha: 0.12),
                      blurRadius: 0,
                      spreadRadius: 2,
                    ),
                  ]
                : null,
          ),
          child: Row(
            children: [
              Text(
                departureTime,
                style: TextStyle(
                  color: AppColors.kora,
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(width: AppSpacing.lg),
              Expanded(child: _RouteLine(origin: origin, destination: destination)),
              const SizedBox(width: AppSpacing.lg),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  if (seatsAvailable != null) ...[
                    Text(
                      '$seatsAvailable places',
                      style: TextStyle(color: koraColors.textMuted, fontSize: 10),
                    ),
                    const SizedBox(height: 4),
                  ],
                  Text(
                    price,
                    style: TextStyle(
                      color: AppColors.kora,
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// La petite ligne pointillée avec deux cercles (départ/arrivée), reprise
/// de `.route-line` dans le prototype.
class _RouteLine extends StatelessWidget {
  const _RouteLine({required this.origin, required this.destination});

  final String origin;
  final String destination;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            _RouteDot(color: AppColors.digital),
            Expanded(child: _dashedLine(koraColors.line)),
            _RouteDot(color: AppColors.gold),
          ],
        ),
        const SizedBox(height: 6),
        Row(
          children: [
            Expanded(
              child: Text(
                origin,
                style: TextStyle(color: koraColors.textPrimary, fontSize: 11, fontWeight: FontWeight.w700),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            const SizedBox(width: AppSpacing.xs),
            Expanded(
              child: Text(
                destination,
                textAlign: TextAlign.right,
                style: TextStyle(color: koraColors.textPrimary, fontSize: 11, fontWeight: FontWeight.w700),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _dashedLine(Color color) {
    return SizedBox(
      height: 2,
      child: DecoratedBox(decoration: BoxDecoration(color: color)),
    );
  }
}

class _RouteDot extends StatelessWidget {
  const _RouteDot({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 9,
      height: 9,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white,
        border: Border.all(color: color, width: 2),
      ),
    );
  }
}
