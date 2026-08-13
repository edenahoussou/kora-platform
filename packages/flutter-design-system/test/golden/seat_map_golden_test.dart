import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('SeatMap — golden', () {
    // Reproduit la logique de génération de l'écran `seats` du prototype
    // (`js/app.js`, L1660) : rangée 1 bloquée, sièges payés/embarqués/
    // réservés dispersés — les 6 états sont tous couverts.
    const mixedSeats = [
      KoraSeat(label: '1A', status: KoraSeatStatus.blocked),
      KoraSeat(label: '1B', status: KoraSeatStatus.blocked),
      KoraSeat(label: '1C', status: KoraSeatStatus.blocked),
      KoraSeat(label: '1D', status: KoraSeatStatus.blocked),
      KoraSeat(label: '2A'),
      KoraSeat(label: '2B', status: KoraSeatStatus.selected),
      KoraSeat(label: '2C', status: KoraSeatStatus.held, timerLabel: '06:18'),
      KoraSeat(label: '2D'),
      KoraSeat(label: '3A', status: KoraSeatStatus.paid),
      KoraSeat(label: '3B'),
      KoraSeat(label: '3C', status: KoraSeatStatus.embarked),
      KoraSeat(label: '3D'),
    ];

    testWidgets('places mixtes', (tester) async {
      await pumpKoraWidget(
        tester,
        const SeatMap(seats: mixedSeats),
        surfaceSize: const Size(320, 320),
      );
      await expectLater(
        find.byType(SeatMap),
        matchesGoldenFile('seat_map_mixed.png'),
      );
    });

    testWidgets('places mixtes — mode sombre (panneau toujours blanc)', (
      tester,
    ) async {
      await pumpKoraWidget(
        tester,
        const SeatMap(seats: mixedSeats),
        surfaceSize: const Size(320, 320),
        dark: true,
      );
      await expectLater(
        find.byType(SeatMap),
        matchesGoldenFile('seat_map_dark.png'),
      );
    });
  });
}
