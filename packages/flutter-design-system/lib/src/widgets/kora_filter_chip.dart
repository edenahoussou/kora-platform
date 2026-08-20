import 'package:flutter/material.dart';

import '../colors/app_colors.dart';
import '../colors/kora_colors.dart';
import '../dimens/app_dimens.dart';
import 'kora_tag.dart';

/// Une puce de filtre : `.tag.outline` au repos, `.tag.gold` quand elle est
/// active — la rangée `Heure ⌄ / Prix ⌄` des résultats de recherche, les
/// onglets `À venir · 1 / Passés · 3` de « Mes voyages ».
///
/// C'est un [KoraTag] rendu cliquable, pas un composant parallèle : une
/// puce de filtre **est** une étiquette, elle réagit simplement au doigt.
/// Sans état actif visible, rien ne dirait sur quel critère la liste est
/// filtrée.
///
/// Exemple d'utilisation :
/// ```dart
/// KoraFilterChip(
///   label: 'Heure',
///   isSelected: sort == DepartureSort.hour,
///   onTap: () => onChanged(DepartureSort.hour),
/// )
///
/// // Sans icône, pour un onglet qui porte déjà un compteur.
/// KoraFilterChip(
///   label: 'À venir · 1',
///   isSelected: true,
///   showIcon: false,
///   onTap: () {},
/// )
/// ```
class KoraFilterChip extends StatelessWidget {
  const KoraFilterChip({
    super.key,
    required this.label,
    required this.isSelected,
    required this.onTap,
    this.showIcon = true,
  });

  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  /// Affiche le chevron (au repos) ou la coche (une fois sélectionnée).
  ///
  /// À couper quand la puce sert d'onglet : la maquette n'y met pas de
  /// chevron, il n'y a rien à dérouler.
  final bool showIcon;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadius.pill),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.pill),
        child: KoraTag(
          label: label,
          variant: isSelected ? KoraTagVariant.gold : KoraTagVariant.outline,
          leading: showIcon
              ? Icon(
                  isSelected
                      ? Icons.check_rounded
                      : Icons.keyboard_arrow_down_rounded,
                  size: 13,
                  color: isSelected
                      ? AppColors.kora
                      : context.koraColors.textMuted,
                )
              : null,
        ),
      ),
    );
  }
}
