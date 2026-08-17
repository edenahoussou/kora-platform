import 'package:flutter/material.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/app_radius.dart';

/// Quatre variantes (voir échange du 18/08/2026 — correction du 17/08) :
/// `primary` (fond jaune orangé KORA, action principale), `secondary`
/// (fond bleu nuit KORA), `ghost` (contour, actions discrètes type
/// "Annuler"), `danger` (fond rouge, actions destructives/refus).
enum KoraButtonVariant { primary, secondary, ghost, danger }

/// Bouton unique de l'application — les écrans n'appellent jamais
/// `ElevatedButton`/`OutlinedButton` directement.
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

  Color _foregroundColor(BuildContext context, KoraButtonVariant variant) =>
      switch (variant) {
        KoraButtonVariant.primary => AppColors.onGold,
        KoraButtonVariant.ghost =>
          Theme.of(context).brightness == Brightness.dark
              ? AppColors.white
              : AppColors.kora,
        _ => AppColors.white,
      };

  Color? _backgroundColor(KoraButtonVariant variant) => switch (variant) {
        KoraButtonVariant.primary => AppColors.gold,
        KoraButtonVariant.secondary => AppColors.kora,
        KoraButtonVariant.danger => AppColors.danger,
        KoraButtonVariant.ghost => null,
      };

  @override
  Widget build(BuildContext context) {
    final VoidCallback? handler = isLoading ? null : onPressed;
    final Color foreground = _foregroundColor(context, variant);

    final Widget child = isLoading
        ? SizedBox(
            height: 18,
            width: 18,
            child: CircularProgressIndicator(strokeWidth: 2, color: foreground),
          )
        : Row(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              if (icon != null) ...<Widget>[
                Icon(icon, size: 18),
                const SizedBox(width: 8),
              ],
              // ✅ CORRIGÉ : Utilisation de Flexible pour permettre au texte de se redimensionner
              Flexible(
                child: Text(
                  label,
                  textAlign: TextAlign.center,
                  overflow: TextOverflow.ellipsis,
                  maxLines: 1,
                ),
              ),
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
                borderRadius: BorderRadius.circular(AppRadius.lg)),
          ),
          onPressed: handler,
          child: child,
        ),
    };

    return fullWidth ? SizedBox(width: double.infinity, child: button) : button;
  }
}
