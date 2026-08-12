import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('KoraCard', () {
    testWidgets('affiche son contenu et utilise un Card Material 3', (
      tester,
    ) async {
      await pumpKoraWidget(tester, const KoraCard(child: Text('Contenu')));

      expect(find.text('Contenu'), findsOneWidget);
      expect(find.byType(Card), findsOneWidget);
    });

    testWidgets('déclenche onTap au tap quand cliquable', (tester) async {
      var tapped = false;
      await pumpKoraWidget(
        tester,
        KoraCard(onTap: () => tapped = true, child: const Text('Tape-moi')),
      );

      await tester.tap(find.byType(KoraCard));
      await tester.pump();

      expect(tapped, isTrue);
    });

    testWidgets('reste inerte sans onTap (pas de crash au tap)', (
      tester,
    ) async {
      await pumpKoraWidget(tester, const KoraCard(child: Text('Statique')));

      await tester.tap(find.byType(KoraCard));
      await tester.pump();

      expect(find.text('Statique'), findsOneWidget);
    });

    for (final variant in KoraCardVariant.values) {
      testWidgets('variante $variant se construit sans erreur', (tester) async {
        await pumpKoraWidget(
          tester,
          KoraCard(variant: variant, child: const Text('X')),
        );
        expect(find.byType(KoraCard), findsOneWidget);
      });
    }
  });
}
