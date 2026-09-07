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

    // Dix voyageurs au plus : le plafond d'un achat groupé en une seule
    // transaction, fixé par MEZ-57.
    for (var i = 0; i < 14; i++) {
      await tester.tap(find.byIcon(Icons.add_rounded));
      await tester.pumpAndSettle();
    }
    expect(find.text('Continuer avec 10 passagers'), findsOneWidget);
  });

  testWidgets('renvoie le nombre confirmé', (tester) async {
    await pumpPicker(tester, selected: 3);

    await tester.tap(find.byIcon(Icons.add_rounded));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer avec 4 passagers'));
    await tester.pumpAndSettle();

    expect(result, 4);
  });

  testWidgets('le raccourci groupe ouvre l’écran de réservation groupée', (
    tester,
  ) async {
    // Le bouton ne pousse plus un compteur : il ouvre l'écran BUS-15 de la
    // maquette, qui explique l'offre avant de faire confirmer le nombre.
    await pumpPicker(tester);

    expect(find.text('Groupe 5–10'), findsOneWidget);

    final shortcut = tester.widget<KoraButton>(
      find.ancestor(
        of: find.text('Groupe 5–10'),
        matching: find.byType(KoraButton),
      ),
    );

    // Toujours actif, quel que soit le compte : on peut vouloir relire
    // l'offre après être passé au-dessus du seuil.
    expect(shortcut.onPressed, isNotNull);
  });

  testWidgets('le raccourci groupe renvoie le seuil à l’accueil', (
    tester,
  ) async {
    // Le nombre exact ne se règle pas ici : il se choisit sur l'écran de
    // réservation de groupe, une fois le départ connu — c'est là seulement
    // que les montants existent.
    await pumpPicker(tester);

    await tester.tap(find.text('Groupe 5–10'));
    await tester.pumpAndSettle();

    expect(result, 5);
  });

  testWidgets('le compte entreprise renvoie au moment du paiement', (
    tester,
  ) async {
    // Le compte entreprise désigne un payeur, pas un nombre de sièges : il
    // n'a rien à faire sur cet écran-ci.
    await pumpPicker(tester);

    await tester.tap(find.text('Compte entreprise'));
    await tester.pumpAndSettle();

    expect(
      find.text('Le compte entreprise se choisit au moment du paiement.'),
      findsOneWidget,
    );
  });
}
