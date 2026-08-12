import 'package:flutter/material.dart';

import '../../colors/app_colors.dart';
import '../../colors/kora_colors.dart';
import '../../dimens/app_dimens.dart';
import '../kora_tag.dart';

/// Ligne "passager" — équivalent `.boarding-passengers article` du
/// prototype OPS web : avatar (initiale), nom, sous-titre (siège /
/// téléphone), badge de statut optionnel en fin de ligne.
///
/// Exemple d'utilisation :
/// ```dart
/// PassengerTile(
///   name: 'Awa Kouassi',
///   subtitle: 'Siège 12A · +225 07 00 00 00',
///   statusLabel: 'Embarqué',
///   statusVariant: KoraTagVariant.gold,
/// )
/// ```
class PassengerTile extends StatelessWidget {
  const PassengerTile({
    super.key,
    required this.name,
    this.subtitle,
    this.statusLabel,
    this.statusVariant = KoraTagVariant.neutral,
    this.onTap,
  });

  final String name;
  final String? subtitle;

  /// Ex: "Embarqué", "En attente", "No-show". `null` = pas de badge.
  final String? statusLabel;
  final KoraTagVariant statusVariant;

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final initial = name.trim().isEmpty ? '?' : name.trim()[0].toUpperCase();

    return Material(
      color: koraColors.surface,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            border: Border.all(color: koraColors.line),
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                alignment: Alignment.center,
                decoration: const BoxDecoration(
                  color: AppColors.mist,
                  shape: BoxShape.circle,
                ),
                child: Text(
                  initial,
                  style: const TextStyle(
                    color: AppColors.kora,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      name,
                      style: TextStyle(
                        color: koraColors.textPrimary,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (subtitle != null) ...[
                      const SizedBox(height: 3),
                      Text(
                        subtitle!,
                        style: TextStyle(
                          color: koraColors.textMuted,
                          fontSize: 10,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],
                ),
              ),
              if (statusLabel != null) ...[
                const SizedBox(width: AppSpacing.sm),
                KoraTag(label: statusLabel!, variant: statusVariant),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
