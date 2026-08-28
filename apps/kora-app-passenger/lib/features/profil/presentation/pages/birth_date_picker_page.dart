import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/widgets/kora_calendar.dart';
import '../../../../core/widgets/screen_header.dart';

/// Choix de la date de naissance, avec le calendrier du choix de trajet.
///
/// Même card, mêmes flèches — une paire de plus pour sauter d'année en
/// année, la plage couvrant ici plus d'un siècle.
class BirthDatePickerPage extends StatefulWidget {
  const BirthDatePickerPage({super.key, this.selected, this.eyebrow});

  final DateTime? selected;

  /// Le surtitre de l'écran. `null` prend celui du profil.
  ///
  /// Cet écran sert aussi le formulaire voyageurs, où il annonçait
  /// « PROFIL » alors qu'on renseigne quelqu'un d'autre.
  final String? eyebrow;

  @override
  State<BirthDatePickerPage> createState() => _BirthDatePickerPageState();
}

class _BirthDatePickerPageState extends State<BirthDatePickerPage> {
  static final DateTime _today = KoraCalendar.dayOf(DateTime.now());

  /// Par défaut on ouvre sur un adulte plausible plutôt que sur
  /// aujourd'hui : personne n'est né ce matin.
  late DateTime _selected =
      widget.selected ?? DateTime(_today.year - 25, _today.month, _today.day);

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: widget.eyebrow ?? l10n.profileEditEyebrow,
                title: l10n.profileEditBirthDate,
              ),
              Expanded(
                child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    KoraCard(
                      child: KoraCalendar(
                        selected: _selected,
                        firstSelectable: DateTime(_today.year - 110),
                        // `before:today` côté serveur : une date du jour
                        // ou postérieure serait refusée.
                        lastSelectable: _today.subtract(
                          const Duration(days: 1),
                        ),
                        onSelect: (day) => setState(() => _selected = day),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.only(bottom: AppSpacing.lg),
                child: KoraButton(
                  label: KoraDateFormats.dayMonthYear.format(_selected),
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

/// Ce que reçoit [BirthDatePickerPage] par `go_router` (`extra`).
///
/// L'écran sert deux parcours : le profil et le formulaire voyageurs. Une
/// simple `DateTime` en `extra` ne laissait pas dire lequel, d'où ces
/// arguments — une `DateTime` nue reste acceptée pour le profil.
class BirthDateArgs {
  const BirthDateArgs({this.selected, this.eyebrow});

  final DateTime? selected;
  final String? eyebrow;
}
