import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../dimens/app_dimens.dart';

/// Les trois alertes du prototype, qui partagent toutes la même forme —
/// pastille d'icône à gauche, titre court, message en dessous — et ne
/// diffèrent que par le ton :
///
/// - [info] : `.connection-alert`, fond doré translucide. L'information
///   utile qu'on ne veut pas rater (une correspondance, un changement de
///   bus) sans pour autant alarmer.
/// - [warning] : `.offline-ride-banner`, doré plein. Un état dégradé mais
///   sous contrôle (mode hors ligne) : plus visible, toujours pas une
///   erreur.
/// - [critical] : `.incident-alert`, fond bleu KORA. Ce qui engage
///   l'utilisateur ou KORA — un signalement ouvert, un incident pris en
///   charge.
///
/// Il n'y a volontairement pas de variante rouge : le prototype réserve
/// le rouge à ce qui bloque (siège embarqué, départ complet), jamais à un
/// message.
enum KoraAlertVariant { info, warning, critical }

/// Message encadré, à poser au-dessus du contenu qu'il concerne.
///
/// Exemple d'utilisation :
/// ```dart
/// KoraAlert(
///   title: 'Changement de bus à Bohicon',
///   message: 'Temps prévu : 35 minutes · assistance KORA sur place',
/// )
///
/// KoraAlert(
///   variant: KoraAlertVariant.warning,
///   icon: Icons.wifi_off_rounded,
///   title: 'Mode hors ligne',
///   message: 'Course enregistrée · synchronisation au retour du réseau',
/// )
/// ```
class KoraAlert extends StatelessWidget {
  const KoraAlert({
    super.key,
    required this.title,
    this.message,
    this.variant = KoraAlertVariant.info,
    this.icon = Icons.priority_high_rounded,
    this.trailing,
  });

  /// Ce qu'il faut retenir, en une ligne. Toujours affiché.
  final String title;

  /// Le détail : délai, conséquence, marche à suivre. Optionnel — une
  /// alerte qui se suffit à son titre n'a pas à s'allonger.
  final String? message;

  final KoraAlertVariant variant;

  /// Glyphe de la pastille. `!` dans le prototype, quelle que soit
  /// l'alerte ; une icône plus parlante (réseau coupé, horloge) reste
  /// préférable quand elle existe.
  final IconData icon;

  /// Action ou valeur alignée à droite (bouton « Voir », montant…).
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final style = _resolveStyle(variant, isDark: isDark);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: style.background,
        borderRadius: BorderRadius.circular(15),
        // Le fond bleu KORA se confond avec un écran en thème sombre :
        // un liseré le détache, comme sur KoraCard en variante navy.
        border: style.border == null ? null : Border.all(color: style.border!),
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: style.badgeBackground,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 18, color: style.badgeForeground),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: style.title,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (message != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    message!,
                    style: TextStyle(
                      color: style.message,
                      fontSize: 9,
                      height: 1.35,
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (trailing != null) ...[
            const SizedBox(width: AppSpacing.sm),
            trailing!,
          ],
        ],
      ),
    );
  }

  _KoraAlertStyle _resolveStyle(
    KoraAlertVariant variant, {
    required bool isDark,
  }) {
    switch (variant) {
      // `.connection-alert { background: rgba(246,176,0,.17) }` — le doré
      // translucide tient sur les deux thèmes, mais son texte bleu KORA
      // serait illisible sur un fond sombre.
      case KoraAlertVariant.info:
        return _KoraAlertStyle(
          background: AppColors.gold.withValues(alpha: 0.17),
          badgeBackground: AppColors.gold,
          badgeForeground: AppColors.kora,
          title: isDark ? AppColors.white : AppColors.kora,
          message: isDark
              ? AppColors.white.withValues(alpha: 0.7)
              : AppColors.mutedLight,
        );

      // `.offline-ride-banner` : doré plein, pastille blanche. Fond opaque
      // dans les deux thèmes, donc mêmes couleurs de texte partout.
      case KoraAlertVariant.warning:
        return const _KoraAlertStyle(
          background: AppColors.gold,
          badgeBackground: AppColors.white,
          badgeForeground: AppColors.kora,
          title: AppColors.kora,
          message: AppColors.kora,
        );

      // `.incident-alert { color: white; background: var(--kora) }` et
      // `p { color: rgba(255,255,255,.58) }`.
      case KoraAlertVariant.critical:
        return _KoraAlertStyle(
          background: AppColors.kora,
          badgeBackground: AppColors.gold,
          badgeForeground: AppColors.kora,
          title: AppColors.white,
          message: AppColors.white.withValues(alpha: 0.58),
          border: isDark ? AppColors.white.withValues(alpha: 0.12) : null,
        );
    }
  }
}

class _KoraAlertStyle {
  const _KoraAlertStyle({
    required this.background,
    required this.badgeBackground,
    required this.badgeForeground,
    required this.title,
    required this.message,
    this.border,
  });

  final Color background;
  final Color badgeBackground;
  final Color badgeForeground;
  final Color title;
  final Color message;
  final Color? border;
}
