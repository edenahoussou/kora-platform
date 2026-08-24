import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../bloc/departures_bloc.dart';

/// Les deux rangées de puces au-dessus des résultats : **trier par**
/// (heure, prix, durée) puis **partir** (matin, après-midi, soir).
///
/// Deux rangées et pas une seule : trier et filtrer ne se défont pas de la
/// même façon. Un tri est toujours actif — on en change —, tandis qu'un
/// filtre s'ajoute et se retire. Mélangées, les puces auraient donné à
/// « Prix » et « Matin » la même apparence pour deux comportements
/// opposés.
///
/// L'apparence vient de [KoraFilterChip] (design system) ; ce widget ne
/// fait que la relier à l'état des départs.
class DepartureFilterBar extends StatelessWidget {
  const DepartureFilterBar({
    super.key,
    required this.sortField,
    required this.sortDescending,
    required this.timeSlots,
    required this.onSortTapped,
    required this.onTimeSlotTapped,
  });

  final DepartureSortField sortField;
  final bool sortDescending;
  final Set<DepartureTimeSlot> timeSlots;

  /// Appelé à chaque tir sur une puce de tri, y compris celle déjà
  /// active : c'est le Bloc qui décide s'il faut changer de critère ou
  /// inverser le sens.
  final ValueChanged<DepartureSortField> onSortTapped;

  final ValueChanged<DepartureTimeSlot> onTimeSlotTapped;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Caption(context.l10n.departuresSortLabel),
        const SizedBox(height: 8),
        _ChipRow(
          children: [
            for (final field in DepartureSortField.values)
              KoraFilterChip(
                // La flèche ne s'affiche que sur la puce active : sur les
                // autres elle annoncerait un sens qui n'est pas appliqué.
                label: field == sortField
                    ? '${_sortLabel(context, field)} ${sortDescending ? '↓' : '↑'}'
                    : _sortLabel(context, field),
                isSelected: field == sortField,
                showIcon: false,
                onTap: () => onSortTapped(field),
              ),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        _Caption(context.l10n.departuresFilterLabel),
        const SizedBox(height: 8),
        _ChipRow(
          children: [
            for (final slot in DepartureTimeSlot.values)
              KoraFilterChip(
                label: _slotLabel(context, slot),
                isSelected: timeSlots.contains(slot),
                showIcon: false,
                onTap: () => onTimeSlotTapped(slot),
              ),
          ],
        ),
      ],
    );
  }
}

/// Le petit intitulé au-dessus d'une rangée. Sans lui, six puces
/// alignées ne diraient pas laquelle trie et laquelle filtre.
class _Caption extends StatelessWidget {
  const _Caption(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Text(
      label.toUpperCase(),
      style: TextStyle(
        color: context.koraColors.textMuted,
        fontSize: 9,
        fontWeight: FontWeight.w900,
        letterSpacing: 0.54,
      ),
    );
  }
}

/// Une rangée de puces qui **passe à la ligne** plutôt que de déborder :
/// « Après-midi » en anglais ou dans une police agrandie ne rentre pas
/// toujours à côté de ses voisines sur un petit écran.
class _ChipRow extends StatelessWidget {
  const _ChipRow({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: AppSpacing.sm,
      runSpacing: AppSpacing.sm,
      children: children,
    );
  }
}

String _sortLabel(BuildContext context, DepartureSortField field) {
  final l10n = context.l10n;
  return switch (field) {
    DepartureSortField.hour => l10n.departuresSortHour,
    DepartureSortField.price => l10n.departuresSortPrice,
    DepartureSortField.duration => l10n.departuresSortDuration,
  };
}

String _slotLabel(BuildContext context, DepartureTimeSlot slot) {
  final l10n = context.l10n;
  return switch (slot) {
    DepartureTimeSlot.morning => l10n.departuresSlotMorning,
    DepartureTimeSlot.afternoon => l10n.departuresSlotAfternoon,
    DepartureTimeSlot.evening => l10n.departuresSlotEvening,
  };
}
