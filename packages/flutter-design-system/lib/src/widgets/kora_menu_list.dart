import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';

/// `.menu-list` : une pile d'actions dans une seule carte, séparées par un
/// filet — les actions d'un billet, les rubriques du profil, les réglages.
///
/// Exemple d'utilisation :
/// ```dart
/// KoraMenuList(
///   items: [
///     KoraMenuItem(
///       icon: Icons.qr_code_2_rounded,
///       title: 'Afficher le QR Code',
///       subtitle: 'Disponible hors ligne',
///       onTap: () {},
///     ),
///   ],
/// )
/// ```
class KoraMenuList extends StatelessWidget {
  const KoraMenuList({super.key, required this.items});

  final List<KoraMenuItem> items;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Container(
      decoration: BoxDecoration(
        color: koraColors.surface,
        borderRadius: BorderRadius.circular(20),
        // `box-shadow: 0 8px 25px rgba(0,29,78,.05)`
        boxShadow: koraColors.cardShadow,
      ),
      // Les coins arrondis doivent rogner le premier et le dernier item,
      // qui touchent le bord de la carte.
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final item in items) ...[
              // `.menu-item:last-child { border: 0 }` — pas de filet sous
              // le dernier, il tomberait sur le bord de la carte.
              if (item != items.first)
                Divider(height: 1, thickness: 1, color: koraColors.line),
              item,
            ],
          ],
        ),
      ),
    );
  }
}

/// `.menu-item` : une action de [KoraMenuList] — pictogramme, libellé,
/// sous-titre et chevron.
class KoraMenuItem extends StatelessWidget {
  const KoraMenuItem({
    super.key,
    required this.icon,
    required this.title,
    this.subtitle,
    this.onTap,
    this.isDestructive = false,
    this.trailing,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback? onTap;

  /// Passe le pictogramme et le libellé en rouge — pour une action qui
  /// retire quelque chose (annuler un billet, supprimer un compte).
  final bool isDestructive;

  /// Ce qui remplace le chevron à droite. La maquette y pose un
  /// interrupteur pour les lignes de réglage (`settingRow`), là où les
  /// lignes d'action gardent leur chevron.
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    // `.menu-item strong { color: var(--kora) }` : le libellé est en bleu
    // KORA, pas dans la couleur du texte courant. En thème sombre ce bleu
    // serait illisible sur la surface foncée.
    final titleColor = isDestructive
        ? AppColors.danger
        : (isDark ? koraColors.textPrimary : AppColors.kora);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Container(
          constraints: const BoxConstraints(minHeight: 58),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            children: [
              // `.menu-icon` : carré doux, fond mist, pictogramme bleu.
              Container(
                width: 36,
                height: 36,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: isDestructive
                      ? AppColors.danger.withValues(alpha: 0.12)
                      : koraColors.background,
                  borderRadius: BorderRadius.circular(AppRadius.base),
                ),
                child: Icon(icon, size: 18, color: titleColor),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        color: titleColor,
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (subtitle != null) ...[
                      const SizedBox(height: 3),
                      Text(
                        subtitle!,
                        style: TextStyle(
                          color: koraColors.textMuted,
                          fontSize: 9,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              trailing ??
                  Icon(
                    Icons.chevron_right_rounded,
                    size: 20,
                    color: koraColors.textMuted,
                  ),
            ],
          ),
        ),
      ),
    );
  }
}
