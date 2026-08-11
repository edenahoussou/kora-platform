import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('KoraCard — golden', () {
    testWidgets('surface', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraCard(child: Text('Contenu de la card')),
        surfaceSize: const Size(300, 120),
      );
      await expectLater(find.byType(KoraCard), matchesGoldenFile('kora_card_surface.png'));
    });

    testWidgets('navy', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraCard(variant: KoraCardVariant.navy, child: Text('Card mise en avant')),
        surfaceSize: const Size(300, 120),
      );
      await expectLater(find.byType(KoraCard), matchesGoldenFile('kora_card_navy.png'));
    });

    testWidgets('gold', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraCard(variant: KoraCardVariant.gold, child: Text('Card promotionnelle')),
        surfaceSize: const Size(300, 120),
      );
      await expectLater(find.byType(KoraCard), matchesGoldenFile('kora_card_gold.png'));
    });

    testWidgets('dark theme', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraCard(child: Text('Contenu de la card')),
        surfaceSize: const Size(300, 120),
        dark: true,
      );
      await expectLater(find.byType(KoraCard), matchesGoldenFile('kora_card_dark.png'));
    });
  });
}
