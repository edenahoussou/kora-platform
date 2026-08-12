import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';

/// Les 4 styles de bouton présents dans le prototype :
/// `.primary` (gold), `.secondary` (navy), `.ghost` (contour), `.danger-button`.
enum KoraButtonVariant { primary, secondary, ghost, danger }

/// Bouton pleine largeur KORA, avec icône optionnelle.
///
/// Exemple d'utilisation :
/// ```dart
/// KoraButton(
///   label: 'Continuer',
///   onPressed: () {},
/// )
///
/// KoraButton(
///   label: 'Annuler',
///   variant: KoraButtonVariant.ghost,
///   onPressed: () {},
/// )
/// ```
class KoraButton extends StatelessWidget {
  const KoraButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = KoraButtonVariant.primary,
    this.icon,
    this.isLoading = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final KoraButtonVariant variant;
  final IconData? icon;
  final bool isLoading;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final style = _resolveStyle(variant, koraColors);

    final child = isLoading
        ? SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2.4,
              color: style.foreground,
            ),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (icon != null) ...[
                Icon(icon, size: 18, color: style.foreground),
                const SizedBox(width: AppSpacing.sm),
              ],
              Text(
                label,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                  color: style.foreground,
                ),
              ),
            ],
          );

    return SizedBox(
      width: double.infinity,
      height: 50,
      child: ElevatedButton(
        onPressed: isLoading ? null : onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: style.background,
          disabledBackgroundColor: style.background.withValues(alpha: 0.5),
          foregroundColor: style.foreground,
          elevation: 0,
          shadowColor: Colors.transparent,
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          side: style.border,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
        ),
        child: child,
      ),
    );
  }

  _KoraButtonStyle _resolveStyle(
    KoraButtonVariant variant,
    KoraColors koraColors,
  ) {
    switch (variant) {
      case KoraButtonVariant.primary:
        return const _KoraButtonStyle(
          background: AppColors.gold,
          foreground: AppColors.kora,
        );
      case KoraButtonVariant.secondary:
        return const _KoraButtonStyle(
          background: AppColors.kora,
          foreground: AppColors.white,
        );
      case KoraButtonVariant.ghost:
        return _KoraButtonStyle(
          background: Colors.transparent,
          foreground: koraColors.textPrimary,
          border: BorderSide(color: koraColors.line),
        );
      case KoraButtonVariant.danger:
        return _KoraButtonStyle(
          background: koraColors.surface,
          foreground: AppColors.danger,
          border: BorderSide(color: koraColors.line),
        );
    }
  }
}

class _KoraButtonStyle {
  const _KoraButtonStyle({
    required this.background,
    required this.foreground,
    this.border,
  });

  final Color background;
  final Color foreground;
  final BorderSide? border;
}
