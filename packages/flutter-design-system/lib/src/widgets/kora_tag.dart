import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';

/// Les variantes de badge présentes dans le prototype :
/// `.tag`, `.tag.gold`, `.tag.blue`, `.tag.outline`.
enum KoraTagVariant { neutral, gold, blue, outline }

/// Petit badge en forme de pilule, utilisé pour les statuts
/// (ex: "Livré", "En cours", "VIP"). Équivalent `.tag` / `.status`.
///
/// À ne pas confondre avec `KoraBadge` (wrapper du `Badge` Material 3) :
/// [KoraTag] est un badge de statut texte inline, `KoraBadge` est un
/// point/compteur superposé sur un autre widget (icône, avatar...).
///
/// Exemple d'utilisation :
/// ```dart
/// KoraTag(label: 'En cours', variant: KoraTagVariant.blue)
/// ```
class KoraTag extends StatelessWidget {
  const KoraTag({
    super.key,
    required this.label,
    this.variant = KoraTagVariant.neutral,
  });

  final String label;
  final KoraTagVariant variant;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    late final Color background;
    late final Color foreground;
    BoxBorder? border;

    switch (variant) {
      case KoraTagVariant.neutral:
        background = koraColors.background;
        foreground = koraColors.textPrimary;
        break;
      case KoraTagVariant.gold:
        background = AppColors.gold;
        foreground = AppColors.kora;
        break;
      case KoraTagVariant.blue:
        background = AppColors.digital;
        foreground = AppColors.white;
        break;
      case KoraTagVariant.outline:
        background = Colors.transparent;
        foreground = koraColors.textPrimary;
        border = Border.all(color: koraColors.line);
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(AppRadius.pill),
        border: border,
      ),
      child: Text(
        label.toUpperCase(),
        style: TextStyle(
          fontSize: 9,
          fontWeight: FontWeight.w900,
          letterSpacing: 0.54,
          color: foreground,
        ),
      ),
    );
  }
}
