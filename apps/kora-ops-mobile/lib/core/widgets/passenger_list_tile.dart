import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';
import '../../app/theme/app_spacing.dart';
import '../../app/theme/kora_colors.dart';
import '../../features/ops/domain/entities/passenger.dart';
import 'statut_badge.dart';

/// Ligne de la liste Manifeste : avatar (initiales), nom, siège + segment,
/// pastille de statut. Réutilisée par `ManifestePage` et
/// `DescenteAnticipeePage`.
class PassengerListTile extends StatelessWidget {
  const PassengerListTile({required this.passenger, this.onTap, super.key});

  final Passenger passenger;
  final VoidCallback? onTap;

  String get _initials {
    final List<String> parts = passenger.nomComplet.trim().split(' ');
    if (parts.length < 2) return parts.first.substring(0, 1).toUpperCase();
    return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Padding(
          padding: const EdgeInsets.symmetric(
              vertical: AppSpacing.sm, horizontal: AppSpacing.xs),
          child: Row(
            children: <Widget>[
              CircleAvatar(
                radius: 22,
                backgroundColor: AppColors.kora.withValues(alpha: 0.1),
                child: Text(
                  _initials,
                  style: const TextStyle(
                      color: AppColors.kora, fontWeight: FontWeight.w700),
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      passenger.nomComplet,
                      style: Theme.of(context).textTheme.titleMedium,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Siège ${passenger.siege} · ${passenger.segmentOrigine} → ${passenger.segmentDestination}',
                      style:
                          TextStyle(color: koraColors.textMuted, fontSize: 12),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              StatutBadge(statut: passenger.statut),
              const SizedBox(width: 4),
              Icon(Icons.chevron_right, color: koraColors.textMuted, size: 18),
            ],
          ),
        ),
      ),
    );
  }
}
