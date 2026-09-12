import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

/// `.travel-reminder` : le rappel pratique affiché sous la carte du
/// prochain voyage (« Présentez-vous 30 min avant le départ. »).
class TravelReminder extends StatelessWidget {
  const TravelReminder({
    super.key,
    required this.title,
    required this.message,
    this.icon = Icons.access_time_rounded,
    this.onTap,
  });

  final String title;
  final String message;

  /// Le pictogramme de la pastille dorée. L'horloge du rappel de départ
  /// par défaut ; les autres entrées de la même famille — l'aller-retour,
  /// par exemple — disent la leur.
  final IconData icon;

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(17),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(17),
        child: Container(
          constraints: const BoxConstraints(minHeight: 58),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: 9,
          ),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(17),
            border: Border.all(color: koraColors.line),
          ),
          child: Row(
            children: [
              // `.reminder-icon { width/height: 38px; border-radius: 12px;
              // background: var(--gold); color: var(--kora) }` — un aplat
              // doré plein (la couleur secondaire de la marque), pas une
              // teinte dorée translucide.
              Container(
                width: 38,
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: AppColors.gold,
                  borderRadius: BorderRadius.circular(AppRadius.base),
                ),
                child: Icon(icon, size: 20, color: AppColors.kora),
              ),
              const SizedBox(width: AppSpacing.sm + 2),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        color: koraColors.textPrimary,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      message,
                      style: TextStyle(
                        color: koraColors.textMuted,
                        fontSize: 9,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                Icons.chevron_right_rounded,
                size: 20,
                color: koraColors.textPrimary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
