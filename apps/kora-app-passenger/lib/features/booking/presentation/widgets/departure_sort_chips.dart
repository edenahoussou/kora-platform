import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../bloc/departures_bloc.dart';

/// La rangée de puces de tri des résultats (`.tag.outline` dans la
/// maquette : `Heure ⌄`, `Prix ⌄`).
///
/// La maquette se contente d'afficher un message au clic ; ici chaque puce
/// trie réellement la liste, et la puce active se distingue.
class DepartureSortChips extends StatelessWidget {
  const DepartureSortChips({
    super.key,
    required this.selected,
    required this.onChanged,
  });

  final DepartureSort selected;
  final ValueChanged<DepartureSort> onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (final sort in DepartureSort.values) ...[
          _SortChip(
            label: sort.label,
            isSelected: sort == selected,
            onTap: () => onChanged(sort),
          ),
          const SizedBox(width: AppSpacing.sm),
        ],
      ],
    );
  }
}

class _SortChip extends StatelessWidget {
  const _SortChip({
    required this.label,
    required this.isSelected,
    required this.onTap,
  });

  final String label;
  final bool isSelected;
  final VoidCallback onTap;

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
          // Le tri actif passe en doré, les autres restent en contour :
          // sans ça, rien ne dirait sur quel critère la liste est triée.
          variant: isSelected ? KoraTagVariant.gold : KoraTagVariant.outline,
          leading: Icon(
            isSelected
                ? Icons.check_rounded
                : Icons.keyboard_arrow_down_rounded,
            size: 13,
            color: isSelected ? AppColors.kora : context.koraColors.textMuted,
          ),
        ),
      ),
    );
  }
}
