import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:widgetbook/widgetbook.dart';

import 'stories/base_component_stories.dart';
import 'stories/business_component_stories.dart';
import 'stories/token_stories.dart';

void main() {
  runApp(const KoraWidgetbookApp());
}

/// Widgetbook du design system KORA EXPRESS — équivalent Storybook.
///
/// Organisation du catalogue (`directories`) :
/// - `Tokens` : couleurs, typographie, espacements, rayons/ombres.
/// - `Composants de base` : KoraButton, KoraInput, KoraCard, KoraBadge,
///   KoraLoadingIndicator.
/// - `Composants métier` : DepartureCard, SeatMap, PassengerTile,
///   TicketView.
///
/// Le rendu de chaque story passe par [MaterialThemeAddon], initialisé
/// avec [AppTheme.light] et [AppTheme.dark] — **exactement** le
/// `ThemeData` utilisé par l'app passager (`AppTheme` vient du même
/// package `flutter_design_system`, aucune config dupliquée). Le sélecteur
/// de thème clair/sombre apparaît dans le panneau d'add-ons en haut à
/// droite de l'interface Widgetbook.
class KoraWidgetbookApp extends StatelessWidget {
  const KoraWidgetbookApp({super.key});

  @override
  Widget build(BuildContext context) {
    return Widgetbook.material(
      directories: [
        tokenStories,
        baseComponentStories,
        businessComponentStories,
      ],
      addons: [
        MaterialThemeAddon(
          themes: [
            WidgetbookTheme(name: 'Clair', data: AppTheme.light),
            WidgetbookTheme(name: 'Sombre', data: AppTheme.dark),
          ],
        ),
        ViewportAddon([
          ...IosViewports.phones,
          ...AndroidViewports.phones,
        ]),
        AlignmentAddon(),
      ],
    );
  }
}
