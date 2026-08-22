import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../utils/date_formats.dart';
import '../utils/title_color.dart';

/// Le calendrier KORA : l'entête `‹ Août 2026 ›` et sa grille de sept
/// colonnes, tels que la maquette les pose dans le choix d'une date de
/// trajet.
///
/// Il sert aussi bien à choisir un départ, à quelques mois de là, qu'une
/// date de naissance vieille de trente ans : quand la plage dépasse deux
/// ans, une seconde paire de flèches saute d'année en année — sans quoi
/// il faudrait trois cents appuis pour remonter jusqu'à 1994.
class KoraCalendar extends StatefulWidget {
  const KoraCalendar({
    super.key,
    required this.selected,
    required this.firstSelectable,
    required this.lastSelectable,
    required this.onSelect,
    this.onMonthChanged,
  });

  final DateTime selected;

  /// Bornes de ce qui est cliquable, incluses.
  final DateTime firstSelectable;
  final DateTime lastSelectable;

  final ValueChanged<DateTime> onSelect;

  /// Prévient l'écran du mois affiché : son entête l'annonce, alors que
  /// la navigation appartient au calendrier.
  final ValueChanged<DateTime>? onMonthChanged;

  /// Ramène une date à son jour, sans heure : deux dates du même jour
  /// doivent se comparer égales.
  static DateTime dayOf(DateTime date) =>
      DateTime(date.year, date.month, date.day);

  @override
  State<KoraCalendar> createState() => _KoraCalendarState();
}

class _KoraCalendarState extends State<KoraCalendar> {
  late DateTime _visibleMonth = _monthOf(widget.selected);

  static DateTime _monthOf(DateTime date) => DateTime(date.year, date.month);

  DateTime get _firstMonth => _monthOf(widget.firstSelectable);
  DateTime get _lastMonth => _monthOf(widget.lastSelectable);

  /// Au-delà de deux ans, naviguer mois par mois n'a plus de sens.
  bool get _showYears => _lastMonth.difference(_firstMonth).inDays > 730;

  @override
  void didUpdateWidget(KoraCalendar oldWidget) {
    super.didUpdateWidget(oldWidget);

    // Une date choisie ailleurs (champ, raccourci) ramène le calendrier
    // sur son mois.
    if (widget.selected != oldWidget.selected) {
      _visibleMonth = _monthOf(widget.selected);
    }
  }

  void _shift(int months) {
    final next = DateTime(_visibleMonth.year, _visibleMonth.month + months);

    final clamped = next.isBefore(_firstMonth)
        ? _firstMonth
        : next.isAfter(_lastMonth)
        ? _lastMonth
        : next;

    // Un saut d'année peut dépasser la borne : on s'y arrête plutôt que
    // d'afficher un mois entièrement grisé.
    setState(() => _visibleMonth = clamped);
    widget.onMonthChanged?.call(clamped);
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            if (_showYears)
              _ArrowButton(
                icon: Icons.keyboard_double_arrow_left_rounded,
                onTap: _visibleMonth.isAfter(_firstMonth)
                    ? () => _shift(-12)
                    : null,
              ),
            _ArrowButton(
              icon: Icons.chevron_left_rounded,
              onTap: _visibleMonth.isAfter(_firstMonth)
                  ? () => _shift(-1)
                  : null,
            ),
            Expanded(
              child: Text(
                KoraDateFormats.capitalize(
                  KoraDateFormats.monthYear.format(_visibleMonth),
                ),
                textAlign: TextAlign.center,
                style: AppTextStyles.titleMedium(context.koraTitleColor),
              ),
            ),
            _ArrowButton(
              icon: Icons.chevron_right_rounded,
              onTap: _visibleMonth.isBefore(_lastMonth)
                  ? () => _shift(1)
                  : null,
            ),
            if (_showYears)
              _ArrowButton(
                icon: Icons.keyboard_double_arrow_right_rounded,
                onTap: _visibleMonth.isBefore(_lastMonth)
                    ? () => _shift(12)
                    : null,
              ),
          ],
        ),
        const SizedBox(height: 15),
        _MonthGrid(
          month: _visibleMonth,
          selected: KoraCalendar.dayOf(widget.selected),
          firstSelectable: KoraCalendar.dayOf(widget.firstSelectable),
          lastSelectable: KoraCalendar.dayOf(widget.lastSelectable),
          onSelect: widget.onSelect,
          mutedColor: koraColors.textMuted,
        ),
      ],
    );
  }
}

class _ArrowButton extends StatelessWidget {
  const _ArrowButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    // `KoraIconButton` n'a pas d'état désactivé : on atténue nous-mêmes la
    // flèche quand elle ne mène nulle part.
    return Opacity(
      opacity: onTap == null ? 0.35 : 1,
      child: KoraIconButton(icon: icon, onTap: onTap, size: 38),
    );
  }
}

/// La grille de 7 colonnes : initiales des jours, puis les dates du mois.
class _MonthGrid extends StatelessWidget {
  const _MonthGrid({
    required this.month,
    required this.selected,
    required this.firstSelectable,
    required this.lastSelectable,
    required this.onSelect,
    required this.mutedColor,
  });

  final DateTime month;
  final DateTime selected;
  final DateTime firstSelectable;
  final DateTime lastSelectable;
  final ValueChanged<DateTime> onSelect;
  final Color mutedColor;

  /// Semaine commençant le lundi, comme la maquette (`L M M J V S D`).
  static const List<String> _weekdayInitials = [
    'L',
    'M',
    'M',
    'J',
    'V',
    'S',
    'D',
  ];

  @override
  Widget build(BuildContext context) {
    final daysInMonth = DateTime(month.year, month.month + 1, 0).day;
    // `weekday` vaut 1 (lundi) à 7 (dimanche) : le décalage à appliquer
    // avant le 1er du mois est donc `weekday - 1`.
    final leadingBlanks = DateTime(month.year, month.month).weekday - 1;

    return GridView.count(
      crossAxisCount: 7,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 7,
      crossAxisSpacing: 7,
      children: [
        for (final initial in _weekdayInitials)
          Center(
            child: Text(initial, style: AppTextStyles.caption(mutedColor)),
          ),
        for (var i = 0; i < leadingBlanks; i++) const SizedBox.shrink(),
        for (var day = 1; day <= daysInMonth; day++)
          _DayCell(
            date: DateTime(month.year, month.month, day),
            selected: selected,
            firstSelectable: firstSelectable,
            lastSelectable: lastSelectable,
            onSelect: onSelect,
          ),
      ],
    );
  }
}

class _DayCell extends StatelessWidget {
  const _DayCell({
    required this.date,
    required this.selected,
    required this.firstSelectable,
    required this.lastSelectable,
    required this.onSelect,
  });

  final DateTime date;
  final DateTime selected;
  final DateTime firstSelectable;
  final DateTime lastSelectable;
  final ValueChanged<DateTime> onSelect;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    final isSelected = date == selected;
    final isOutside =
        date.isBefore(firstSelectable) || date.isAfter(lastSelectable);

    return Material(
      color: isSelected ? AppColors.gold : Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      child: InkWell(
        onTap: isOutside ? null : () => onSelect(date),
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Center(
          child: Text(
            '${date.day}',
            style: TextStyle(
              fontSize: 13,
              fontWeight: isSelected ? FontWeight.w900 : FontWeight.w500,
              color: isSelected
                  ? AppColors.kora
                  : isOutside
                  ? koraColors.textMuted.withValues(alpha: 0.4)
                  : koraColors.textPrimary,
            ),
          ),
        ),
      ),
    );
  }
}
