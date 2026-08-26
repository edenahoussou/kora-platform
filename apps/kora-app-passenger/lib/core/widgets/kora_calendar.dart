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

/// Ce que le calendrier montre en ce moment.
///
/// Reprend la mecanique du selecteur natif : le titre est un bouton qui
/// descend d'un cran a chaque appui — jours, puis mois, puis annees — et
/// remonte a mesure qu'on choisit. Trois appuis suffisent alors pour
/// atteindre n'importe quelle date, la ou les fleches en demandaient une
/// centaine sur une plage de 110 ans.
enum _CalendarView { days, months, years }

class _KoraCalendarState extends State<KoraCalendar> {
  late DateTime _visibleMonth = _monthOf(widget.selected);

  _CalendarView _view = _CalendarView.days;

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

  int get _firstYear => _firstMonth.year;
  int get _lastYear => _lastMonth.year;

  /// Ramene un mois dans les bornes, sans jamais afficher une grille
  /// entierement grisee.
  DateTime _clamp(DateTime month) {
    if (month.isBefore(_firstMonth)) return _firstMonth;
    if (month.isAfter(_lastMonth)) return _lastMonth;
    return month;
  }

  void _pickYear(int year) {
    setState(() {
      _visibleMonth = _clamp(DateTime(year, _visibleMonth.month));
      _view = _CalendarView.months;
    });
    widget.onMonthChanged?.call(_visibleMonth);
  }

  void _pickMonth(int month) {
    setState(() {
      _visibleMonth = _clamp(DateTime(_visibleMonth.year, month));
      _view = _CalendarView.days;
    });
    widget.onMonthChanged?.call(_visibleMonth);
  }

  void _toggleView() {
    setState(() {
      _view = switch (_view) {
        _CalendarView.days => _CalendarView.years,
        // Depuis les mois, l'appui remonte aux annees : on vient d'y
        // descendre, l'utilisateur veut viser plus large.
        _CalendarView.months => _CalendarView.years,
        _CalendarView.years => _CalendarView.days,
      };
    });
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

    final onDays = _view == _CalendarView.days;

    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            // Les fleches ne servent qu'a la grille des jours : sur les
            // mois et les annees, tout est deja a l'ecran.
            if (_showYears && onDays)
              _ArrowButton(
                icon: Icons.keyboard_double_arrow_left_rounded,
                onTap: _visibleMonth.isAfter(_firstMonth)
                    ? () => _shift(-12)
                    : null,
              ),
            if (onDays)
              _ArrowButton(
                icon: Icons.chevron_left_rounded,
                onTap: _visibleMonth.isAfter(_firstMonth)
                    ? () => _shift(-1)
                    : null,
              ),
            Expanded(
              child: _ViewSwitch(
                label: KoraDateFormats.capitalize(
                  KoraDateFormats.monthYear.format(_visibleMonth),
                ),
                expanded: !onDays,
                onTap: _toggleView,
              ),
            ),
            if (onDays)
              _ArrowButton(
                icon: Icons.chevron_right_rounded,
                onTap: _visibleMonth.isBefore(_lastMonth)
                    ? () => _shift(1)
                    : null,
              ),
            if (_showYears && onDays)
              _ArrowButton(
                icon: Icons.keyboard_double_arrow_right_rounded,
                onTap: _visibleMonth.isBefore(_lastMonth)
                    ? () => _shift(12)
                    : null,
              ),
          ],
        ),
        const SizedBox(height: 15),
        switch (_view) {
          _CalendarView.days => _MonthGrid(
            month: _visibleMonth,
            selected: KoraCalendar.dayOf(widget.selected),
            firstSelectable: KoraCalendar.dayOf(widget.firstSelectable),
            lastSelectable: KoraCalendar.dayOf(widget.lastSelectable),
            onSelect: widget.onSelect,
            mutedColor: koraColors.textMuted,
          ),
          _CalendarView.months => _MonthPicker(
            year: _visibleMonth.year,
            selectedMonth: _visibleMonth.month,
            firstMonth: _firstMonth,
            lastMonth: _lastMonth,
            onSelect: _pickMonth,
          ),
          _CalendarView.years => _YearPicker(
            selectedYear: _visibleMonth.year,
            firstYear: _firstYear,
            lastYear: _lastYear,
            onSelect: _pickYear,
          ),
        },
      ],
    );
  }
}

/// Le titre du calendrier, devenu bouton.
///
/// Le chevron dit qu'il y a quelque chose dessous — sans lui, rien
/// n'indique que ce texte se touche, et on repart sur les fleches.
class _ViewSwitch extends StatelessWidget {
  const _ViewSwitch({
    required this.label,
    required this.expanded,
    required this.onTap,
  });

  final String label;
  final bool expanded;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.pill),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Flexible(
              child: Text(
                label,
                textAlign: TextAlign.center,
                overflow: TextOverflow.ellipsis,
                style: AppTextStyles.titleMedium(context.koraTitleColor),
              ),
            ),
            const SizedBox(width: 4),
            Icon(
              expanded
                  ? Icons.arrow_drop_up_rounded
                  : Icons.arrow_drop_down_rounded,
              color: context.koraTitleColor,
            ),
          ],
        ),
      ),
    );
  }
}

/// Les douze mois d'une annee, en grille.
class _MonthPicker extends StatelessWidget {
  const _MonthPicker({
    required this.year,
    required this.selectedMonth,
    required this.firstMonth,
    required this.lastMonth,
    required this.onSelect,
  });

  final int year;
  final int selectedMonth;
  final DateTime firstMonth;
  final DateTime lastMonth;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 3,
      childAspectRatio: 2.1,
      mainAxisSpacing: 6,
      crossAxisSpacing: 6,
      children: [
        for (var month = 1; month <= 12; month++)
          _PickerCell(
            label: KoraDateFormats.capitalize(
              KoraDateFormats.monthOnly.format(DateTime(year, month)),
            ),
            selected: month == selectedMonth,
            // Un mois hors bornes reste visible mais eteint : le masquer
            // ferait sauter la grille d'une annee a l'autre.
            enabled:
                !DateTime(year, month).isBefore(firstMonth) &&
                !DateTime(year, month).isAfter(lastMonth),
            onTap: () => onSelect(month),
          ),
      ],
    );
  }
}

/// Les annees de la plage, en grille defilante.
class _YearPicker extends StatelessWidget {
  const _YearPicker({
    required this.selectedYear,
    required this.firstYear,
    required this.lastYear,
    required this.onSelect,
  });

  final int selectedYear;
  final int firstYear;
  final int lastYear;
  final ValueChanged<int> onSelect;

  /// Hauteur d'une ligne, cellule et espacement compris — sert a ouvrir
  /// la grille sur l'annee choisie plutot qu'au debut de la plage.
  static const double _rowHeight = 48;

  @override
  Widget build(BuildContext context) {
    final years = [for (var y = lastYear; y >= firstYear; y--) y];
    final index = years.indexOf(selectedYear);
    final row = index < 0 ? 0 : index ~/ 3;

    return SizedBox(
      height: _rowHeight * 5,
      child: GridView.count(
        controller: ScrollController(
          // Centre approximativement l'annee courante : deux lignes
          // au-dessus d'elle.
          initialScrollOffset: ((row - 2) * _rowHeight).clamp(
            0,
            double.infinity,
          ),
        ),
        crossAxisCount: 3,
        childAspectRatio: 2.1,
        mainAxisSpacing: 6,
        crossAxisSpacing: 6,
        children: [
          for (final year in years)
            _PickerCell(
              label: '$year',
              selected: year == selectedYear,
              enabled: true,
              onTap: () => onSelect(year),
            ),
        ],
      ),
    );
  }
}

/// Une case des grilles mois et annees.
class _PickerCell extends StatelessWidget {
  const _PickerCell({
    required this.label,
    required this.selected,
    required this.enabled,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Material(
      color: selected ? AppColors.gold : Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadius.input),
      child: InkWell(
        onTap: enabled ? onTap : null,
        borderRadius: BorderRadius.circular(AppRadius.input),
        child: Container(
          alignment: Alignment.center,
          decoration: BoxDecoration(
            border: selected ? null : Border.all(color: koraColors.line),
            borderRadius: BorderRadius.circular(AppRadius.input),
          ),
          child: Text(
            label,
            style: AppTextStyles.body(
              selected
                  ? AppColors.kora
                  : enabled
                  ? koraColors.textPrimary
                  : koraColors.textMuted,
            ),
          ),
        ),
      ),
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
