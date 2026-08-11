import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('KoraBadge', () {
    testWidgets('affiche le child et un Badge Material 3', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraBadge(child: Icon(Icons.notifications_outlined)),
      );

      expect(find.byIcon(Icons.notifications_outlined), findsOneWidget);
      expect(find.byType(Badge), findsOneWidget);
    });

    testWidgets('sans count : simple point, aucun texte affiché', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraBadge(child: Icon(Icons.notifications_outlined)),
      );

      expect(find.byType(Text), findsNothing);
    });

    testWidgets('avec count : affiche le chiffre', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraBadge(count: 3, child: Icon(Icons.mail_outline)),
      );

      expect(find.text('3'), findsOneWidget);
    });

    testWidgets('tronque à 999+ au-delà de 999 (comportement Badge.count)', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraBadge(count: 1280, child: Icon(Icons.mail_outline)),
      );

      expect(find.text('999+'), findsOneWidget);
    });

    testWidgets('isVisible=false masque le badge', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraBadge(count: 3, isVisible: false, child: Icon(Icons.mail_outline)),
      );

      final badge = tester.widget<Badge>(find.byType(Badge));
      expect(badge.isLabelVisible, isFalse);
    });
  });
}
