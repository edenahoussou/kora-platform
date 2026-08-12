import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('KoraButton', () {
    testWidgets('affiche son label et déclenche onPressed au tap', (
      tester,
    ) async {
      var tapped = false;
      await pumpKoraWidget(
        tester,
        KoraButton(label: 'Continuer', onPressed: () => tapped = true),
      );

      expect(find.text('Continuer'), findsOneWidget);

      await tester.tap(find.byType(KoraButton));
      await tester.pump();

      expect(tapped, isTrue);
    });

    testWidgets('désactivé quand onPressed est null', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraButton(label: 'Continuer', onPressed: null),
      );

      final button = tester.widget<ElevatedButton>(find.byType(ElevatedButton));
      expect(button.onPressed, isNull);
    });

    testWidgets('état loading affiche un spinner et bloque le tap', (
      tester,
    ) async {
      var tapped = false;
      await pumpKoraWidget(
        tester,
        KoraButton(
          label: 'Continuer',
          isLoading: true,
          onPressed: () => tapped = true,
        ),
      );

      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(find.text('Continuer'), findsNothing);

      await tester.tap(find.byType(KoraButton));
      await tester.pump();

      expect(tapped, isFalse);
    });

    testWidgets('affiche l\'icône fournie', (tester) async {
      await pumpKoraWidget(
        tester,
        KoraButton(label: 'Payer', icon: Icons.lock_outline, onPressed: () {}),
      );

      expect(find.byIcon(Icons.lock_outline), findsOneWidget);
    });

    for (final variant in KoraButtonVariant.values) {
      testWidgets('variante $variant se construit sans erreur', (tester) async {
        await pumpKoraWidget(
          tester,
          KoraButton(label: 'Label', variant: variant, onPressed: () {}),
        );
        expect(find.byType(KoraButton), findsOneWidget);
      });
    }
  });
}
