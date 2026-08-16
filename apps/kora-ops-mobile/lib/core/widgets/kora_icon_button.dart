import 'package:flutter/material.dart';
import '../../app/theme/kora_colors.dart';

/// Bouton rond avec icône — utilisé pour le retour dans [ScreenHeader] et
/// toute action secondaire compacte.
class KoraIconButton extends StatelessWidget {
  const KoraIconButton({
    required this.icon,
    required this.onTap,
    this.color,
    this.background,
    this.size = 40,
    super.key,
  });

  final IconData icon;
  final VoidCallback? onTap;
  final Color? color;
  final Color? background;
  final double size;

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;

    return Material(
      color: background ?? koraColors.surface,
      shape: const CircleBorder(),
      elevation: 1,
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: SizedBox(
          width: size,
          height: size,
          child: Icon(icon,
              color: color ?? koraColors.textPrimary, size: size * 0.55),
        ),
      ),
    );
  }
}
