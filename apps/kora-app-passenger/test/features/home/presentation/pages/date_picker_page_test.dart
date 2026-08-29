// Tests de l'écran de choix de la date : alignement du calendrier sur les
// vrais jours du mois, bornes de navigation, jours passés non
// sélectionnables et valeur renvoyée.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/home/presentation/pages/date_picker_page.dart';

void main() {
  setUpAll(() => AppLocalization.preloadAll());

  // 14 août 2026, un vendredi.
  final today = DateTime(2026, 8, 14);

  setUpAll(() async {
    await initializeDateFormatting(KoraDateFormats.locale);
  });

  /// Ouvre l'écran comme le fait l'accueil (empilé au-dessus d'une page),
  /// pour pouvoir observer la valeur qu'il renvoie en se refermant.
  Future<DateTime?> pumpPicker(
    WidgetTester tester, {
    DateTime? selected,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    DateTime? result;

    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        theme: AppTheme.light,
        home: Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: TextButton(
                onPressed: () async {
                  result = await Navigator.of(context).push<DateTime>(
                    MaterialPageRoute(
                      builder: (_) => DatePickerPage(
                        selected: selected ?? today,
                        today: today,
                      ),
                    ),
                  );
                },
                child: const Text('ouvrir'),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('ouvrir'));
    await tester.pumpAndSettle();

    return result;
  }

  testWidgets('ouvre sur le mois de la date déjà choisie', (tester) async {
    await pumpPicker(tester);

    // Le mois apparaît deux fois : en surtitre (en capitales) et dans
    // la card.
    expect(find.text('AOÛT 2026'), findsOneWidget);
    expect(find.text('Août 2026'), findsOneWidget);
    expect(find.text('Choisir le 14 août'), findsOneWidget);
  });

  testWidgets('aligne les jours sur les bonnes colonnes', (tester) async {
    await pumpPicker(tester);

    // Août 2026 commence un samedi et compte 31 jours : la grille doit
    // donc contenir 7 initiales + 5 cases vides + 31 jours.
    expect(DateTime(2026, 8).weekday, DateTime.saturday);
    expect(find.text('31'), findsOneWidget);
    expect(find.text('32'), findsNothing);

    final grid = tester.widget<GridView>(find.byType(GridView));
    expect(grid.semanticChildCount, 7 + 5 + 31);
  });

  testWidgets('n’autorise pas un départ dans le passé', (tester) async {
    await pumpPicker(tester);

    // Le 3 août est passé : le toucher ne change pas la date retenue.
    await tester.tap(find.text('3'));
    await tester.pumpAndSettle();

    expect(find.text('Choisir le 14 août'), findsOneWidget);
  });

  testWidgets('permet de choisir un autre jour du mois', (tester) async {
    await pumpPicker(tester);

    await tester.tap(find.text('21'));
    await tester.pumpAndSettle();

    expect(find.text('Choisir le 21 août'), findsOneWidget);
  });

  testWidgets('navigue de mois en mois, sans revenir avant aujourd’hui', (
    tester,
  ) async {
    await pumpPicker(tester);

    // Le mois courant est le premier disponible : la flèche « précédent »
    // ne fait rien.
    await tester.tap(find.byIcon(Icons.chevron_left_rounded).last);
    await tester.pumpAndSettle();
    expect(find.text('Août 2026'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.chevron_right_rounded));
    await tester.pumpAndSettle();
    expect(find.text('SEPTEMBRE 2026'), findsOneWidget);
    expect(find.text('Septembre 2026'), findsOneWidget);

    // Septembre compte 30 jours, et aucun n'est passé.
    expect(find.text('30'), findsOneWidget);
    expect(find.text('31'), findsNothing);
    await tester.tap(find.text('2'));
    await tester.pumpAndSettle();
    expect(find.text('Choisir le 02 septembre'), findsOneWidget);
  });

  testWidgets('renvoie la date confirmée à l’écran appelant', (tester) async {
    DateTime? result;

    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        theme: AppTheme.light,
        home: Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: TextButton(
                onPressed: () async {
                  result = await Navigator.of(context).push<DateTime>(
                    MaterialPageRoute(
                      builder: (_) =>
                          DatePickerPage(selected: today, today: today),
                    ),
                  );
                },
                child: const Text('ouvrir'),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('ouvrir'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('20'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Choisir le 20 août'));
    await tester.pumpAndSettle();

    expect(result, DateTime(2026, 8, 20));
  });
}
