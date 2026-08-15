import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';

enum AppButtonVariant { primary, secondary, outline, success, warning, danger }

/// Bouton unique de l'application — les écrans ne doivent pas appeler
/// `ElevatedButton`/`OutlinedButton` directement pour rester cohérents avec
/// le Design System KORA. Couvre les 6 variantes du kit UI.
///
/// `primary` utilise l'or de marque (`AppColors.accent`), conformément aux
/// maquettes d'authentification fournies (CTA doré, marine réservé aux
/// en-têtes et aux écrans de sécurité) — pas le bleu marine.
class AppButton extends StatelessWidget {
  const AppButton({
    required this.label,
    required this.onPressed,
    this.variant = AppButtonVariant.primary,
    this.isLoading = false,
    this.icon,
    this.fullWidth = true,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final AppButtonVariant variant;
  final bool isLoading;
  final IconData? icon;

  /// Les boutons KORA sont pleine largeur par défaut (voir maquette : tous
  /// les boutons d'action occupent la largeur de l'écran). Passer `false`
  /// pour un bouton de taille intrinsèque.
  final bool fullWidth;

  Color _foregroundColor(AppButtonVariant variant) => switch (variant) {
        AppButtonVariant.primary ||
        AppButtonVariant.warning =>
          AppColors.onAccent,
        AppButtonVariant.outline => AppColors.primary,
        _ => AppColors.white,
      };

  Color? _backgroundColor(AppButtonVariant variant) => switch (variant) {
        AppButtonVariant.primary => AppColors.accent,
        AppButtonVariant.secondary => AppColors.secondary,
        AppButtonVariant.success => AppColors.success,
        AppButtonVariant.warning => AppColors.warning,
        AppButtonVariant.danger => AppColors.danger,
        AppButtonVariant.outline => null,
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
      AppButtonVariant.outline =>
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
