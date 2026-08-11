import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';

/// Les variantes de card présentes dans le prototype :
/// `.card`, `.card.navy`, `.card.gold`, `.card.mist`.
enum KoraCardVariant { surface, navy, gold, mist }

/// Card générique KORA : coins arrondis (20), fond selon la variante,
/// ombre douce en mode clair (pas d'ombre en mode sombre). Wrapper direct
/// du widget `Card` Material 3 : l'élévation Material reste à 0 (le rendu
/// de `Card` avec élévation utilise un flou différent du `box-shadow` du
/// prototype CSS) — l'ombre douce KORA est appliquée par-dessus via un
/// `Container` externe, avec les valeurs exactes de [AppShadows].
///
/// Exemple d'utilisation :
/// ```dart
/// KoraCard(
///   child: Text('Contenu de la card'),
/// )
///
/// KoraCard(
///   variant: KoraCardVariant.navy,
///   onTap: () => print('tap'),
///   child: Text('Card cliquable en bleu marine'),
/// )
/// ```
class KoraCard extends StatelessWidget {
  const KoraCard({
    super.key,
    required this.child,
    this.variant = KoraCardVariant.surface,
    this.onTap,
    this.padding = const EdgeInsets.all(AppSpacing.lg),
  });

  final Widget child;
  final KoraCardVariant variant;

  /// Si fourni, la card devient cliquable (équivalent `.card.clickable`).
  final VoidCallback? onTap;

  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    final Color background;
    final List<BoxShadow> shadow;
    switch (variant) {
      case KoraCardVariant.surface:
        background = koraColors.surface;
        shadow = koraColors.cardShadow;
        break;
      case KoraCardVariant.navy:
        background = AppColors.kora;
        shadow = const [];
        break;
      case KoraCardVariant.gold:
        background = AppColors.gold;
        shadow = const [];
        break;
      case KoraCardVariant.mist:
        background = koraColors.background.withValues(alpha: 0.5);
        shadow = const [];
        break;
    }

    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.lg),
      side: variant == KoraCardVariant.surface
          ? BorderSide(color: koraColors.line)
          : BorderSide.none,
    );

    final card = Card(
      margin: EdgeInsets.zero,
      color: background,
      // Élévation à 0 volontairement : l'ombre douce KORA vient du
      // `Container` ci-dessous, pas de l'algorithme d'élévation Material.
      elevation: 0,
      shape: shape,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: padding,
          child: DefaultTextStyle.merge(
            style: TextStyle(color: _textColorFor(variant, koraColors)),
            child: child,
          ),
        ),
      ),
    );

    if (shadow.isEmpty) return card;

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.lg),
        boxShadow: shadow,
      ),
      child: card,
    );
  }

  Color _textColorFor(KoraCardVariant variant, KoraColors koraColors) {
    switch (variant) {
      case KoraCardVariant.navy:
        return AppColors.white;
      case KoraCardVariant.gold:
        return AppColors.kora;
      case KoraCardVariant.surface:
      case KoraCardVariant.mist:
        return koraColors.textPrimary;
    }
  }
}
