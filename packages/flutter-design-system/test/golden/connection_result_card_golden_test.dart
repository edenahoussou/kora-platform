import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('ConnectionResultCard — golden', () {
    testWidgets('par défaut', (tester) async {
      await pumpKoraWidget(
        tester,
        const ConnectionResultCard(
          departureTime: '06:45',
          arrivalTime: '17:40',
          routeLabel: 'Cotonou → Bohicon → Natitingou',
          noteLabel: 'Changement de bus à Bohicon · attente 35 min',
          segmentsLabel: '2 segments inclus',
          price: '14 500 FCFA',
        ),
        surfaceSize: const Size(380, 190),
      );
      await expectLater(
        find.byType(ConnectionResultCard),
        matchesGoldenFile('connection_result_card_default.png'),
      );
    });

    testWidgets('mode sombre (carte toujours blanche)', (tester) async {
      await pumpKoraWidget(
        tester,
        const ConnectionResultCard(
          departureTime: '06:45',
          arrivalTime: '17:40',
          routeLabel: 'Cotonou → Bohicon → Natitingou',
          noteLabel: 'Changement de bus à Bohicon · attente 35 min',
          segmentsLabel: '2 segments inclus',
          price: '14 500 FCFA',
        ),
        surfaceSize: const Size(380, 190),
        dark: true,
      );
      await expectLater(
        find.byType(ConnectionResultCard),
        matchesGoldenFile('connection_result_card_dark.png'),
      );
    });
  });
}
