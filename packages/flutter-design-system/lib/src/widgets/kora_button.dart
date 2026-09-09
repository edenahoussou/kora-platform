import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';

/// Les 4 styles de bouton présents dans le prototype :
/// `.primary` (gold), `.secondary` (navy), `.ghost` (contour), `.danger-button`.
enum KoraButtonVariant { primary, secondary, ghost, danger }

/// Où se place l'icône par rapport au libellé.
///
/// Le prototype utilise les trois dispositions :
/// - [leading] : l'icône précède le texte, l'ensemble reste centré. Elle
///   qualifie l'action (`🔒 Payer maintenant`).
/// - [trailing] : l'icône suit le texte, l'ensemble reste centré.
/// - [edges] : le libellé est calé à gauche, l'icône à droite, aux deux
///   bouts du bouton — `justify-content: space-between` sur
///   `.seat-checkout button` et `.welcome-primary`.
enum KoraButtonIconPosition { leading, trailing, edges }

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
    this.iconPosition = KoraButtonIconPosition.leading,
    this.isLoading = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final KoraButtonVariant variant;
  final IconData? icon;

  /// Position de [icon] par rapport au libellé.
  final KoraButtonIconPosition iconPosition;

  final bool isLoading;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final style = _resolveStyle(
      variant,
      koraColors,
      isDark: Theme.of(context).brightness == Brightness.dark,
    );

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
            // En disposition [KoraButtonIconPosition.edges], la rangée
            // occupe toute la largeur et écarte ses deux extrémités ;
            // sinon elle se resserre autour de son contenu, centré.
            mainAxisSize: iconPosition == KoraButtonIconPosition.edges
                ? MainAxisSize.max
                : MainAxisSize.min,
            mainAxisAlignment: iconPosition == KoraButtonIconPosition.edges
                ? MainAxisAlignment.spaceBetween
                : MainAxisAlignment.center,
            children: [
              if (icon != null &&
                  iconPosition == KoraButtonIconPosition.leading) ...[
                Icon(icon, size: 18, color: style.foreground),
                const SizedBox(width: AppSpacing.sm),
              ],
              // `Flexible` + ellipse : un libellé long (bouton en demi-
              // largeur, texte agrandi par les réglages d'accessibilité)
              // doit se tronquer proprement, pas déborder de son bouton.
              Flexible(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                    color: style.foreground,
                  ),
                ),
              ),
              if (icon != null &&
                  iconPosition != KoraButtonIconPosition.leading) ...[
                const SizedBox(width: AppSpacing.sm),
                Icon(icon, size: 18, color: style.foreground),
              ],
            ],
          );

    final button = SizedBox(
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

    // `.primary { box-shadow: 0 9px 22px rgba(246,176,0,.2) }` — la halo
    // dorée sous le bouton principal, seule variante à en avoir une. Le
    // token [AppShadows.primaryButton] existait déjà pour ça mais n'était
    // appliqué nulle part.
    //
    // Pas d'ombre quand le bouton ne répond pas : un bouton désactivé (ou
    // en cours de chargement) est à plat, son fond passant lui aussi à
    // 50 % d'opacité juste au-dessus.
    final isEnabled = onPressed != null && !isLoading;
    if (variant != KoraButtonVariant.primary || !isEnabled) return button;

    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadius.md),
        boxShadow: AppShadows.primaryButton,
      ),
      child: button,
    );
  }

  _KoraButtonStyle _resolveStyle(
    KoraButtonVariant variant,
    KoraColors koraColors, {
    required bool isDark,
  }) {
    switch (variant) {
      case KoraButtonVariant.primary:
        return const _KoraButtonStyle(
          background: AppColors.gold,
          foreground: AppColors.kora,
        );
      case KoraButtonVariant.secondary:
        // Le navy de la maquette n'existe qu'en thème clair, où il tranche
        // sur la page. En sombre, le fond d'écran EST ce navy : le bouton
        // s'y dissolvait, et il ne restait que son libellé flottant. Un
        // filet lui rend ses bords, comme à `KoraCard.navy`.
        return _KoraButtonStyle(
          background: AppColors.kora,
          foreground: AppColors.white,
          border: isDark ? BorderSide(color: koraColors.line) : null,
        );
      case KoraButtonVariant.ghost:
        // `.ghost { color: var(--kora) }` : le libellé est en bleu KORA,
        // pas dans la couleur du texte courant (graphite), qui le rendait
        // presque noir en thème clair. En thème sombre ce bleu serait
        // illisible : `textPrimary` (blanc) y reste le bon choix.
        return _KoraButtonStyle(
          background: Colors.transparent,
          foreground: isDark ? koraColors.textPrimary : AppColors.kora,
          border: BorderSide(color: koraColors.line),
        );
      case KoraButtonVariant.danger:
        // Rouge plein, libellé blanc — le traitement que la maquette
        // réserve déjà à ce qui bloque (`.sold-out .danger { color: white;
        // background: #9c2430 }`). Une action destructrice doit se lire
        // comme telle : le contour blanc de `.danger-button` la rendait
        // indistinguable d'un bouton secondaire.
        return const _KoraButtonStyle(
          background: AppColors.danger,
          foreground: AppColors.white,
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
