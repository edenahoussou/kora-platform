import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('KoraBadge — golden', () {
    testWidgets('point simple', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraBadge(child: Icon(Icons.notifications_outlined, size: 28)),
        surfaceSize: const Size(80, 80),
      );
      await expectLater(find.byType(KoraBadge), matchesGoldenFile('kora_badge_dot.png'));
    });

    testWidgets('avec compteur', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraBadge(count: 3, child: Icon(Icons.mail_outline, size: 28)),
        surfaceSize: const Size(80, 80),
      );
      await expectLater(find.byType(KoraBadge), matchesGoldenFile('kora_badge_count.png'));
    });

    testWidgets('compteur tronqué (999+)', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraBadge(count: 1280, child: Icon(Icons.mail_outline, size: 28)),
        surfaceSize: const Size(80, 80),
      );
      await expectLater(find.byType(KoraBadge), matchesGoldenFile('kora_badge_overflow.png'));
    });
  });

  group('KoraTag — golden', () {
    testWidgets('les 4 variantes', (tester) async {
      await pumpKoraWidget(
        tester,
        const Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            KoraTag(label: 'En attente'),
            SizedBox(height: 8),
            KoraTag(label: 'VIP', variant: KoraTagVariant.gold),
            SizedBox(height: 8),
            KoraTag(label: 'En cours', variant: KoraTagVariant.blue),
            SizedBox(height: 8),
            KoraTag(label: 'Livré', variant: KoraTagVariant.outline),
          ],
        ),
        surfaceSize: const Size(160, 160),
      );
      await expectLater(find.byType(Column).first, matchesGoldenFile('kora_tag_variants.png'));
    });
  });
}
