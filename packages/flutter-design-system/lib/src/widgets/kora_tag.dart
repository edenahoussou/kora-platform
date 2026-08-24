import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';

/// Les variantes de badge présentes dans le prototype :
/// `.tag`, `.tag.gold`, `.tag.blue`, `.tag.outline`, et `.tag.danger`
/// (visible via `.sold-out .danger` — ex: badge "Complet" sur une
/// [DepartureCard] pleine — `docs/maquettes/kora-app-passenger/css/
/// style.css`, L1676).
///
/// [success] n'a pas d'équivalent dans le prototype : il complète
/// [danger] pour les badges qui gradent une valeur plutôt que d'annoncer
/// un incident (places restantes d'un départ, voir [KoraSeatsBadge]). Un
/// feu tricolore sans vert n'en est pas un.
enum KoraTagVariant { neutral, gold, blue, outline, danger, success }

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
///
/// // Avec icône (ex: badge véhicule sur DepartureCard) :
/// KoraTag(
///   label: 'KORA Confort',
///   variant: KoraTagVariant.gold,
///   leading: KoraVehicleIcon(type: KoraVehicleType.bus, size: 19),
/// )
/// ```
class KoraTag extends StatelessWidget {
  const KoraTag({
    super.key,
    required this.label,
    this.variant = KoraTagVariant.neutral,
    this.leading,
  });

  final String label;
  final KoraTagVariant variant;

  /// Icône optionnelle avant le label — équivalent `.trip-mode-icon`
  /// (`margin-right: 4px` dans le prototype, repris ci-dessous).
  final Widget? leading;

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
      case KoraTagVariant.danger:
        background = AppColors.danger;
        foreground = AppColors.white;
        break;
      case KoraTagVariant.success:
        background = AppColors.success;
        foreground = AppColors.white;
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(AppRadius.pill),
        border: border,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (leading != null) ...[leading!, const SizedBox(width: 4)],
          Text(
            label.toUpperCase(),
            style: TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.w900,
              letterSpacing: 0.54,
              color: foreground,
            ),
          ),
        ],
      ),
    );
  }
}
