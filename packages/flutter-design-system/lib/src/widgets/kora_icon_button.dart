import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';
import 'kora_badge.dart';

/// Bouton rond avec icône, utilisé dans les headers d'écran (retour,
/// notifications, etc.). Équivalent de `.icon-button` / `.round-button`
/// dans le prototype CSS.
///
/// Exemple d'utilisation :
/// ```dart
/// KoraIconButton(
///   icon: Icons.arrow_back,
///   onTap: () => Navigator.pop(context),
/// )
/// ```
class KoraIconButton extends StatelessWidget {
  const KoraIconButton({
    super.key,
    required this.icon,
    this.onTap,
    this.size = 42,
    this.showBadge = false,
    this.onNavy = false,
  });

  final IconData icon;
  final VoidCallback? onTap;
  final double size;

  /// Petit point doré, ex: pour indiquer une notification non lue
  /// (équivalent `.notification-button::after` dans le CSS). Affiché via
  /// [KoraBadge] (wrapper du `Badge` Material 3).
  final bool showBadge;

  /// Le bouton est posé sur un fond navy, quel que soit le thème :
  /// `.navy-screen .icon-button` de la maquette — un voile blanc à 10 %,
  /// pictogramme blanc, sans ombre.
  final bool onNavy;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    // Certains écrans (connexion, OTP) sont navy dans les deux thèmes :
    // c'est le fond qui commande, pas la clarté du thème.
    final isDark = onNavy || Theme.of(context).brightness == Brightness.dark;

    // Le bouton est très souvent posé sur une card, de la même couleur de
    // surface que lui : sans traitement, la pastille disparaît. La
    // maquette résout ça de deux façons selon le fond, on reprend les
    // deux :
    // - sur écran clair, `.icon-button` est blanc avec une ombre douce ;
    // - sur écran navy, `.navy-screen .icon-button` devient un voile blanc
    //   à 10 %, sans ombre (une ombre ne se verrait pas sur du sombre).
    final button = DecoratedBox(
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        boxShadow: isDark ? const [] : AppShadows.iconButton,
      ),
      child: SizedBox(
        width: size,
        height: size,
        child: Material(
          color: isDark
              ? AppColors.white.withValues(alpha: 0.1)
              : koraColors.surface,
          shape: const CircleBorder(),
          elevation: 0,
          child: InkWell(
            onTap: onTap,
            customBorder: const CircleBorder(),
            child: Center(
              child: Icon(
                icon,
                size: 19,
                color: onNavy ? AppColors.white : koraColors.textPrimary,
              ),
            ),
          ),
        ),
      ),
    );

    if (!showBadge) return button;

    return KoraBadge(child: button);
  }
}
