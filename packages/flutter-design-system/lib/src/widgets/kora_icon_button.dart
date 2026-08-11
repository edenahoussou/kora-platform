import 'package:flutter/material.dart';

import '../colors/kora_colors.dart';
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
  });

  final IconData icon;
  final VoidCallback? onTap;
  final double size;

  /// Petit point doré, ex: pour indiquer une notification non lue
  /// (équivalent `.notification-button::after` dans le CSS). Affiché via
  /// [KoraBadge] (wrapper du `Badge` Material 3).
  final bool showBadge;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    final button = SizedBox(
      width: size,
      height: size,
      child: Material(
        color: koraColors.surface,
        shape: const CircleBorder(),
        elevation: 0,
        child: InkWell(
          onTap: onTap,
          customBorder: const CircleBorder(),
          child: Center(
            child: Icon(icon, size: 19, color: koraColors.textPrimary),
          ),
        ),
      ),
    );

    if (!showBadge) return button;

    return KoraBadge(child: button);
  }
}
