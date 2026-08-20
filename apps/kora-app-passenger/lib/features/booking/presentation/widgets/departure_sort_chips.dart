import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../bloc/departures_bloc.dart';

/// La rangée de puces de tri des résultats (`.tag.outline` dans la
/// maquette : `Heure ⌄`, `Prix ⌄`).
///
/// La maquette se contente d'afficher un message au clic ; ici chaque puce
/// trie réellement la liste, et la puce active se distingue.
///
/// L'apparence vient de [KoraFilterChip] (design system) ; ce widget ne
/// fait que la relier au tri des départs.
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
          KoraFilterChip(
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
