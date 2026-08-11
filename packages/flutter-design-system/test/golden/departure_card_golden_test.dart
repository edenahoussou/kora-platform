import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('DepartureCard — golden', () {
    testWidgets('par défaut', (tester) async {
      await pumpKoraWidget(
        tester,
        const DepartureCard(
          departureTime: '14:30',
          origin: 'Cotonou',
          destination: 'Porto-Novo',
          price: '2 500 FCFA',
          seatsAvailable: 12,
        ),
        surfaceSize: const Size(380, 130),
      );
      await expectLater(find.byType(DepartureCard), matchesGoldenFile('departure_card_default.png'));
    });

    testWidgets('sélectionnée', (tester) async {
      await pumpKoraWidget(
        tester,
        const DepartureCard(
          departureTime: '14:30',
          origin: 'Cotonou',
          destination: 'Porto-Novo',
          price: '2 500 FCFA',
          seatsAvailable: 12,
          isSelected: true,
        ),
        surfaceSize: const Size(380, 130),
      );
      await expectLater(find.byType(DepartureCard), matchesGoldenFile('departure_card_selected.png'));
    });
  });
}
