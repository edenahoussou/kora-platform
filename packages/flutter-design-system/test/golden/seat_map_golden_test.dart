import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('SeatMap — golden', () {
    testWidgets('places mixtes', (tester) async {
      await pumpKoraWidget(
        tester,
        const SeatMap(
          seats: [
            KoraSeat(label: '1A'),
            KoraSeat(label: '1B', status: KoraSeatStatus.occupied),
            KoraSeat(label: '1C', status: KoraSeatStatus.occupied),
            KoraSeat(label: '1D'),
            KoraSeat(label: '2A', status: KoraSeatStatus.selected),
            KoraSeat(label: '2B'),
            KoraSeat(label: '2C'),
            KoraSeat(label: '2D', status: KoraSeatStatus.disabled),
          ],
        ),
        surfaceSize: const Size(320, 260),
      );
      await expectLater(
        find.byType(SeatMap),
        matchesGoldenFile('seat_map_mixed.png'),
      );
    });
  });
}
