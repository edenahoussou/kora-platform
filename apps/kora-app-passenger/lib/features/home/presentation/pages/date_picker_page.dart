import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';

/// Données passées à [DatePickerPage] par `go_router` (`extra`).
///
/// [today] vient de l'horloge du Bloc plutôt que de `DateTime.now()` :
/// même raison que partout ailleurs dans cette feature, garder les dates
/// testables (voir `HomeState.today`).
class DatePickerArgs {
  const DatePickerArgs({required this.selected, required this.today});

  final DateTime selected;
  final DateTime today;
}

/// Écran de choix de la date de départ — équivalent `calendarScreen` de la
/// maquette (`js/app.js`, écran `date`) : en-tête, calendrier mensuel dans
/// une card, note explicative, puis le bouton de confirmation.
///
/// Écran **plein**, sans bottom nav, comme `CityPickerPage` : c'est une
/// étape du formulaire de recherche. Il renvoie la date choisie à
/// l'appelant via son `pop`.
///
/// **Le calendrier de la maquette est un simple compteur de 1 à 31**, sans
/// rapport entre les numéros et les colonnes de jours de semaine. On rend
/// ici un vrai mois : premier jour aligné sur sa colonne, longueur réelle,
/// navigation d'un mois à l'autre, et jours passés désactivés. Afficher un
/// faux calendrier dans une app de réservation induirait l'utilisateur en
/// erreur.
class DatePickerPage extends StatefulWidget {
  const DatePickerPage({
    super.key,
    required this.selected,
    required this.today,
    this.monthsAhead = 6,
  });

  /// Date actuellement renseignée dans le champ.
  final DateTime selected;

  /// Jour de référence : rien avant lui n'est réservable.
  final DateTime today;

  /// Profondeur du calendrier — au-delà, les départs ne sont pas encore
  /// ouverts à la vente.
  final int monthsAhead;

  @override
  State<DatePickerPage> createState() => _DatePickerPageState();
}

class _DatePickerPageState extends State<DatePickerPage> {
  late DateTime _selected = _dayOf(widget.selected);
  late DateTime _visibleMonth = DateTime(_selected.year, _selected.month);

  static DateTime _dayOf(DateTime date) =>
      DateTime(date.year, date.month, date.day);

  DateTime get _firstMonth => DateTime(widget.today.year, widget.today.month);

  DateTime get _lastMonth =>
      DateTime(widget.today.year, widget.today.month + widget.monthsAhead);

  bool get _canGoBack => _visibleMonth.isAfter(_firstMonth);
  bool get _canGoForward => _visibleMonth.isBefore(_lastMonth);

  void _shiftMonth(int months) {
    setState(() {
      _visibleMonth = DateTime(
        _visibleMonth.year,
        _visibleMonth.month + months,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: KoraDateFormats.capitalize(
                  KoraDateFormats.monthYear.format(_visibleMonth),
                ),
                title: 'Quand partez-vous ?',
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    KoraCard(
                      child: Column(
                        children: [
                          _MonthSwitcher(
                            month: _visibleMonth,
                            onPrevious: _canGoBack
                                ? () => _shiftMonth(-1)
                                : null,
                            onNext: _canGoForward ? () => _shiftMonth(1) : null,
                          ),
                          const SizedBox(height: 15),
                          _MonthGrid(
                            month: _visibleMonth,
                            selected: _selected,
                            firstSelectable: _dayOf(widget.today),
                            onSelect: (day) => setState(() => _selected = day),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 13),
                    KoraCard(
                      variant: KoraCardVariant.mist,
                      // La maquette annonce ici que « les jours en or ont
                      // plusieurs départs » ; chez nous le doré marque le
                      // jour choisi, et aucune donnée de disponibilité par
                      // jour n'existe encore. On dit donc ce qui est vrai.
                      child: Text(
                        'Les départs sont ouverts à la réservation jusqu’à '
                        '${widget.monthsAhead} mois à l’avance.',
                        style: AppTextStyles.body(koraColors.textMuted),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: KoraButton(
                  label:
                      'Choisir le ${KoraDateFormats.dayMonth.format(_selected)}',
                  onPressed: () => Navigator.of(context).pop(_selected),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// La ligne `‹ Août 2026 ›` en tête de la card.
class _MonthSwitcher extends StatelessWidget {
  const _MonthSwitcher({
    required this.month,
    required this.onPrevious,
    required this.onNext,
  });

  final DateTime month;

  /// `null` désactive la flèche : on ne remonte pas avant le mois courant,
  /// ni au-delà de l'horizon de vente.
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        _ArrowButton(icon: Icons.chevron_left_rounded, onTap: onPrevious),
        Text(
          KoraDateFormats.capitalize(KoraDateFormats.monthYear.format(month)),
          style: AppTextStyles.titleMedium(context.koraTitleColor),
        ),
        _ArrowButton(icon: Icons.chevron_right_rounded, onTap: onNext),
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
    required this.onSelect,
  });

  final DateTime month;
  final DateTime selected;
  final DateTime firstSelectable;
  final ValueChanged<DateTime> onSelect;

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
    final koraColors = context.koraColors;

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
            child: Text(
              initial,
              style: AppTextStyles.caption(koraColors.textMuted),
            ),
          ),
        for (var i = 0; i < leadingBlanks; i++) const SizedBox.shrink(),
        for (var day = 1; day <= daysInMonth; day++)
          _DayCell(
            date: DateTime(month.year, month.month, day),
            selected: selected,
            firstSelectable: firstSelectable,
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
    required this.onSelect,
  });

  final DateTime date;
  final DateTime selected;
  final DateTime firstSelectable;
  final ValueChanged<DateTime> onSelect;

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    final isSelected = date == selected;
    final isPast = date.isBefore(firstSelectable);

    return Material(
      color: isSelected ? AppColors.gold : Colors.transparent,
      borderRadius: BorderRadius.circular(AppRadius.sm),
      child: InkWell(
        onTap: isPast ? null : () => onSelect(date),
        borderRadius: BorderRadius.circular(AppRadius.sm),
        child: Center(
          child: Text(
            '${date.day}',
            style: TextStyle(
              fontSize: 13,
              fontWeight: isSelected ? FontWeight.w900 : FontWeight.w500,
              color: isSelected
                  ? AppColors.kora
                  : isPast
                  ? koraColors.textMuted.withValues(alpha: 0.4)
                  : koraColors.textPrimary,
            ),
          ),
        ),
      ),
    );
  }
}
