import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/title_color.dart';

/// `.home-quick-grid` : les 4 accès rapides sous la carte de recherche.
///
/// La tuile mise en avant (`.home-quick-item.active` dans la maquette,
/// [QuickAccessItem.highlighted] ici) est bordée et remplie de doré.
class HomeQuickGrid extends StatelessWidget {
  const HomeQuickGrid({super.key, required this.items});

  final List<QuickAccessItem> items;

  @override
  Widget build(BuildContext context) {
    // `IntrinsicHeight` aligne la hauteur des 4 tuiles sur la plus haute.
    // Il est indispensable ici : `CrossAxisAlignment.stretch` sur une Row
    // exige une hauteur bornée, or la grille vit dans une colonne
    // scrollable, donc de hauteur illimitée.
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final item in items) ...[
            Expanded(child: _QuickItem(item: item)),
            if (item != items.last) const SizedBox(width: AppSpacing.sm),
          ],
        ],
      ),
    );
  }
}

/// Description d'une tuile d'accès rapide.
class QuickAccessItem {
  const QuickAccessItem({
    required this.label,
    required this.icon,
    required this.onTap,
    this.highlighted = false,
  });

  final String label;

  /// Icône déjà dimensionnée par l'appelant (une [KoraNavIcon] ou une
  /// [KoraVehicleIcon] selon le service).
  final Widget icon;

  final VoidCallback onTap;
  final bool highlighted;
}

class _QuickItem extends StatelessWidget {
  const _QuickItem({required this.item});

  final QuickAccessItem item;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final tile = Material(
      color: item.highlighted
          ? AppColors.gold.withValues(alpha: 0.12)
          : koraColors.surface,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: item.onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          constraints: const BoxConstraints(minHeight: 76),
          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: item.highlighted ? AppColors.gold : koraColors.line,
            ),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 31,
                height: 31,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: item.highlighted
                      ? AppColors.gold
                      : koraColors.background,
                  borderRadius: BorderRadius.circular(AppRadius.sm),
                ),
                child: item.icon,
              ),
              const SizedBox(height: 7),
              Text(
                item.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                // `.home-quick-item { color: var(--kora) }`
                style: TextStyle(
                  color: context.koraTitleColor,
                  fontSize: 9,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ),
    );

    // Le contour seul (`--line`, soit du bleu KORA à 10%) ne suffit pas à
    // détacher une tuile blanche d'un fond `mist` presque aussi clair : la
    // maquette lui adjoint une ombre douce
    // (`box-shadow: 0 8px 24px rgba(0,29,78,.045)`), qui manquait ici.
    // Aucune ombre en sombre, comme partout ailleurs dans le design
    // system (`KoraColors.dark.cardShadow` est vide).
    if (isDark) return tile;

    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: AppColors.kora.withValues(alpha: 0.045),
            offset: const Offset(0, 8),
            blurRadius: 24,
          ),
        ],
      ),
      child: tile,
    );
  }
}
