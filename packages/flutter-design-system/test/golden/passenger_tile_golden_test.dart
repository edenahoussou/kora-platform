import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('PassengerTile — golden', () {
    testWidgets('avec badge de statut', (tester) async {
      await pumpKoraWidget(
        tester,
        const PassengerTile(
          name: 'Awa Kouassi',
          subtitle: 'Siège 12A · +225 07 00 00 00',
          statusLabel: 'Embarqué',
          statusVariant: KoraTagVariant.gold,
        ),
        surfaceSize: const Size(340, 90),
      );
      await expectLater(
        find.byType(PassengerTile),
        matchesGoldenFile('passenger_tile_default.png'),
      );
    });
  });
}
