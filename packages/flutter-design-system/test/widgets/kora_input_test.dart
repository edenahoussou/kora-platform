import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('KoraInput', () {
    testWidgets('affiche le label et le hint', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraInput(label: 'Adresse e-mail', hint: 'toi@exemple.com'),
      );

      expect(find.text('Adresse e-mail'), findsOneWidget);
      expect(find.text('toi@exemple.com'), findsOneWidget);
    });

    testWidgets('déclenche onChanged à la saisie', (tester) async {
      String? value;
      await pumpKoraWidget(
        tester,
        KoraInput(label: 'Nom', onChanged: (v) => value = v),
      );

      await tester.enterText(find.byType(TextField), 'Awa Kouassi');

      expect(value, 'Awa Kouassi');
    });

    testWidgets('masque la saisie par défaut si obscureText, et révèle au tap', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraInput(label: 'Mot de passe', obscureText: true),
      );

      var field = tester.widget<TextField>(find.byType(TextField));
      expect(field.obscureText, isTrue);

      await tester.tap(find.byIcon(Icons.visibility_outlined));
      await tester.pump();

      field = tester.widget<TextField>(find.byType(TextField));
      expect(field.obscureText, isFalse);
      expect(find.byIcon(Icons.visibility_off_outlined), findsOneWidget);
    });

    testWidgets('affiche le texte d\'erreur', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraInput(label: 'Mot de passe', errorText: 'Minimum 8 caractères'),
      );

      expect(find.text('Minimum 8 caractères'), findsOneWidget);
    });

    testWidgets('désactivé quand enabled=false', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraInput(label: 'Code promo', enabled: false),
      );

      final field = tester.widget<TextField>(find.byType(TextField));
      expect(field.enabled, isFalse);
    });
  });
}
