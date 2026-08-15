import 'dart:math' as math;

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
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final Color background;
    final List<BoxShadow> shadow;
    switch (variant) {
      case KoraCardVariant.surface:
        background = koraColors.surface;
        shadow = koraColors.cardShadow;
        break;
      case KoraCardVariant.navy:
        background = AppColors.kora;
        // `.card.navy` n'annule pas l'ombre de `.card` : elle flotte au
        // même titre que les autres. En sombre, aucune ombre ne se voit
        // sur un fond déjà sombre (voir `KoraColors.dark.cardShadow`).
        shadow = isDark ? const [] : AppShadows.card;
        break;
      case KoraCardVariant.gold:
        background = AppColors.gold;
        shadow = const [];
        break;
      case KoraCardVariant.mist:
        // `.card.mist { background: rgba(255,255,255,.5); box-shadow: none }`
        // — du BLANC translucide, et non la couleur de fond de l'écran.
        // Avec le fond (`mist` en clair), la card se confondait exactement
        // avec la page derrière elle : invisible, le texte semblait flotter.
        // En thème sombre, du blanc à 50 % serait aveuglant : on garde le
        // principe (un voile clair par-dessus le fond) à une opacité
        // adaptée, comme le fait déjà le fond des champs de saisie.
        background = AppColors.white.withValues(alpha: isDark ? 0.06 : 0.5);
        shadow = const [];
        break;
    }

    // `.card.mist` n'annule que l'ombre : elle garde le contour de `.card`,
    // qui reste son seul relief une fois l'ombre retirée.
    //
    // La variante navy, elle, n'a pas de contour dans la maquette — mais
    // celle-ci n'existe qu'en thème clair, où le navy tranche sur le fond.
    // En thème sombre, le fond d'écran EST ce navy : sans filet, la card
    // n'a plus de bords visibles.
    final hasBorder =
        variant == KoraCardVariant.surface ||
        variant == KoraCardVariant.mist ||
        (variant == KoraCardVariant.navy && isDark);

    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.lg),
      side: hasBorder ? BorderSide(color: koraColors.line) : BorderSide.none,
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
        child: _NavyDecoration(
          enabled: variant == KoraCardVariant.navy,
          child: Padding(
            padding: padding,
            child: DefaultTextStyle.merge(
              style: TextStyle(color: _textColorFor(variant, koraColors)),
              child: child,
            ),
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

/// Les deux ornements de `.card.navy` : un halo bleu en haut à droite et
/// le trait doré incliné en bas, signature visuelle qu'on retrouve sur
/// l'en-tête de l'accueil et la bannière Ride.
///
/// Ils sont portés par la card elle-même — c'est ce que dit la maquette
/// (`.card.navy` et son `::after`) — plutôt que recopiés par chaque écran
/// qui en affiche une.
class _NavyDecoration extends StatelessWidget {
  const _NavyDecoration({required this.enabled, required this.child});

  final bool enabled;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (!enabled) return child;

    return Stack(
      children: [
        // `radial-gradient(circle at 90% 15%, rgba(0,87,184,.33), transparent 35%)`
        const Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: RadialGradient(
                center: Alignment(0.8, -0.7),
                radius: 0.7,
                colors: [Color(0x540057B8), Colors.transparent],
              ),
            ),
          ),
        ),
        // `.card.navy::after` — le débord à droite est rogné par le
        // `clipBehavior` de la card.
        Positioned(
          right: -45,
          bottom: 24,
          child: Transform.rotate(
            angle: -22 * math.pi / 180,
            child: Container(
              width: 170,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.gold.withValues(alpha: 0.8),
                borderRadius: BorderRadius.circular(AppRadius.pill),
              ),
            ),
          ),
        ),
        child,
      ],
    );
  }
}
