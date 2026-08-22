import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/utils/date_formats.dart';
import '../../../../core/widgets/kora_calendar.dart';
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

  /// Mois montré par le calendrier — l'entête de l'écran l'annonce.
  late DateTime _visibleMonth = DateTime(_selected.year, _selected.month);
  static DateTime _dayOf(DateTime date) =>
      DateTime(date.year, date.month, date.day);

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
                      child: KoraCalendar(
                        selected: _selected,
                        firstSelectable: widget.today,
                        lastSelectable: DateTime(
                          widget.today.year,
                          widget.today.month + widget.monthsAhead,
                          DateTime(
                            widget.today.year,
                            widget.today.month + widget.monthsAhead + 1,
                            0,
                          ).day,
                        ),
                        onSelect: (day) => setState(() => _selected = day),
                        onMonthChanged: (month) =>
                            setState(() => _visibleMonth = month),
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
