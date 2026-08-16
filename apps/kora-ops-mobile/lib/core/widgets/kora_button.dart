import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';

/// Variantes alignées sur `kora-passager` (`primary`/`secondary`/`ghost`/
/// `danger`), complétées par `success`/`warning` pour les besoins
/// opérationnels de KORA OPS (feedback de scan, alertes).
enum KoraButtonVariant { primary, secondary, ghost, success, warning, danger }

/// Bouton unique de l'application — les écrans n'appellent jamais
/// `ElevatedButton`/`OutlinedButton` directement. `primary` utilise l'or de
/// marque (`AppColors.gold`), conformément aux maquettes.
class KoraButton extends StatelessWidget {
  const KoraButton({
    required this.label,
    required this.onPressed,
    this.variant = KoraButtonVariant.primary,
    this.isLoading = false,
    this.icon,
    this.fullWidth = true,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final KoraButtonVariant variant;
  final bool isLoading;
  final IconData? icon;

  /// Les boutons KORA sont pleine largeur par défaut (voir maquette).
  final bool fullWidth;

  Color _foregroundColor(KoraButtonVariant variant) => switch (variant) {
        KoraButtonVariant.primary ||
        KoraButtonVariant.warning =>
          AppColors.onGold,
        KoraButtonVariant.ghost => AppColors.kora,
        _ => AppColors.white,
      };

  Color? _backgroundColor(KoraButtonVariant variant) => switch (variant) {
        KoraButtonVariant.primary => AppColors.gold,
        KoraButtonVariant.secondary => AppColors.digital,
        KoraButtonVariant.success => AppColors.success,
        KoraButtonVariant.warning => AppColors.warning,
        KoraButtonVariant.danger => AppColors.danger,
        KoraButtonVariant.ghost => null,
      };

  @override
  Widget build(BuildContext context) {
    final VoidCallback? handler = isLoading ? null : onPressed;
    final Color foreground = _foregroundColor(variant);

    final Widget child = isLoading
        ? SizedBox(
            height: 18,
            width: 18,
            child: CircularProgressIndicator(strokeWidth: 2, color: foreground),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              if (icon != null) ...<Widget>[
                Icon(icon, size: 18),
                const SizedBox(width: 8)
              ],
              Text(label),
            ],
          );

    final Widget button = switch (variant) {
      KoraButtonVariant.ghost =>
        OutlinedButton(onPressed: handler, child: child),
      _ => ElevatedButton(
          style: ElevatedButton.styleFrom(
            backgroundColor: _backgroundColor(variant),
            foregroundColor: foreground,
            disabledBackgroundColor:
                _backgroundColor(variant)?.withValues(alpha: 0.5),
            shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(AppRadius.md)),
          ),
          onPressed: handler,
          child: child,
        ),
    };

    return fullWidth ? SizedBox(width: double.infinity, child: button) : button;
  }
}
