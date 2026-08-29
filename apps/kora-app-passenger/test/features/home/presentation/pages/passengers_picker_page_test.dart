// Tests de l'écran « Combien de passagers ? » : compteur borné et valeur
// renvoyée à l'écran appelant.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/home/presentation/pages/passengers_picker_page.dart';

void main() {
  setUpAll(() => AppLocalization.preloadAll());

  late int? result;

  Future<void> pumpPicker(WidgetTester tester, {int selected = 1}) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    result = null;

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
                  result = await Navigator.of(context).push<int>(
                    MaterialPageRoute(
                      builder: (_) => PassengersPickerPage(selected: selected),
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
  }

  testWidgets('incrémente et décrémente le nombre de passagers', (
    tester,
  ) async {
    await pumpPicker(tester);

    expect(find.text('Continuer avec 1 passager'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.add_rounded));
    await tester.pumpAndSettle();
    expect(find.text('Continuer avec 2 passagers'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.remove_rounded));
    await tester.pumpAndSettle();
    expect(find.text('Continuer avec 1 passager'), findsOneWidget);
  });

  testWidgets('ne descend pas sous 1 ni au-dessus du maximum', (tester) async {
    await pumpPicker(tester);

    await tester.tap(find.byIcon(Icons.remove_rounded));
    await tester.pumpAndSettle();
    expect(find.text('Continuer avec 1 passager'), findsOneWidget);

    // 6 passagers maximum : au-delà, c'est une réservation de groupe.
    for (var i = 0; i < 8; i++) {
      await tester.tap(find.byIcon(Icons.add_rounded));
      await tester.pumpAndSettle();
    }
    expect(find.text('Continuer avec 6 passagers'), findsOneWidget);
  });

  testWidgets('renvoie le nombre confirmé', (tester) async {
    await pumpPicker(tester, selected: 3);

    await tester.tap(find.byIcon(Icons.add_rounded));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer avec 4 passagers'));
    await tester.pumpAndSettle();

    expect(result, 4);
  });

  testWidgets('annonce les parcours groupe et entreprise comme à venir', (
    tester,
  ) async {
    await pumpPicker(tester);

    await tester.tap(find.text('Groupe 5–10'));
    await tester.pumpAndSettle();

    expect(
      find.text('La réservation de groupe arrive après le MVP Bus.'),
      findsOneWidget,
    );
  });
}
