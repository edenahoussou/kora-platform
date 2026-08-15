// Tests de l'écran de création du PIN : saisie, effacement, validation
// incomplète, et transmission du code choisi.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/auth/presentation/pages/create_pin_page.dart';

void main() {
  late List<String> submitted;

  Future<void> pumpCreatePin(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    submitted = [];

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: CreatePinPage(onSubmit: submitted.add),
      ),
    );
    await tester.pumpAndSettle();
  }

  /// Compte les pastilles déjà remplies. Hors écran navy, elles se
  /// remplissent en bleu KORA.
  int filledDots(WidgetTester tester) => tester
      .widgetList<Container>(find.byType(Container))
      .where((container) {
        final decoration = container.decoration;
        return decoration is BoxDecoration &&
            decoration.shape == BoxShape.circle &&
            decoration.color == AppColors.kora;
      })
      .length;

  testWidgets('affiche la consigne et un pavé complet', (tester) async {
    await pumpCreatePin(tester);

    expect(find.text('Créez votre PIN'), findsOneWidget);
    expect(find.textContaining('4 chiffres faciles à retenir'), findsOneWidget);

    for (final key in ['1', '5', '9', '0', '⌫', '✓']) {
      expect(find.text(key), findsOneWidget, reason: 'touche $key absente');
    }
    expect(filledDots(tester), 0);
  });

  testWidgets('remplit une pastille par chiffre, sans dépasser quatre', (
    tester,
  ) async {
    await pumpCreatePin(tester);

    for (final key in ['1', '2', '3']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    expect(filledDots(tester), 3);

    for (final key in ['4', '5']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    expect(filledDots(tester), 4);
  });

  testWidgets('efface le dernier chiffre saisi', (tester) async {
    await pumpCreatePin(tester);

    await tester.tap(find.text('7'));
    await tester.tap(find.text('8'));
    await tester.pump();
    expect(filledDots(tester), 2);

    await tester.tap(find.text('⌫'));
    await tester.pump();
    expect(filledDots(tester), 1);
  });

  testWidgets('refuse un PIN incomplet', (tester) async {
    await pumpCreatePin(tester);

    await tester.tap(find.text('1'));
    await tester.tap(find.text('2'));
    await tester.pump();

    await tester.tap(find.text('Continuer'));
    await tester.pump();

    expect(find.text('Choisissez vos 4 chiffres'), findsOneWidget);
    expect(submitted, isEmpty);
  });

  testWidgets('transmet le code une fois les quatre chiffres saisis', (
    tester,
  ) async {
    await pumpCreatePin(tester);

    for (final key in ['1', '2', '3', '4']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    await tester.tap(find.text('Continuer'));
    await tester.pump();

    expect(submitted, ['1234']);
    expect(find.text('Choisissez vos 4 chiffres'), findsNothing);
  });

  testWidgets('la touche ✓ vaut le bouton Continuer', (tester) async {
    await pumpCreatePin(tester);

    for (final key in ['9', '8', '7', '6']) {
      await tester.tap(find.text(key));
      await tester.pump();
    }
    await tester.tap(find.text('✓'));
    await tester.pump();

    expect(submitted, ['9876']);
  });

  testWidgets('sert aussi à la confirmation du PIN', (tester) async {
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: const CreatePinPage(
          title: 'Confirmez votre PIN',
          subtitle: 'Saisissez à nouveau votre code personnel.',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Confirmez votre PIN'), findsOneWidget);
    expect(
      find.text('Saisissez à nouveau votre code personnel.'),
      findsOneWidget,
    );
  });
}
