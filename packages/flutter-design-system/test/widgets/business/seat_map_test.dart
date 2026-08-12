import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../test_utils.dart';

void main() {
  group('SeatMap', () {
    const seats = [
      KoraSeat(label: '1A'),
      KoraSeat(label: '1B', status: KoraSeatStatus.occupied),
      KoraSeat(label: '1C', status: KoraSeatStatus.selected),
      KoraSeat(label: '1D', status: KoraSeatStatus.disabled),
    ];

    testWidgets('affiche le label de chaque siège', (tester) async {
      await pumpKoraWidget(tester, const SeatMap(seats: seats));

      for (final seat in seats) {
        expect(find.text(seat.label), findsOneWidget);
      }
    });

    testWidgets('tap sur un siège disponible déclenche onSeatTap', (
      tester,
    ) async {
      KoraSeat? tapped;
      await pumpKoraWidget(
        tester,
        SeatMap(seats: seats, onSeatTap: (seat) => tapped = seat),
      );

      await tester.tap(find.text('1A'));
      await tester.pump();

      expect(tapped?.label, '1A');
    });

    testWidgets('tap sur un siège occupé ne déclenche rien', (tester) async {
      KoraSeat? tapped;
      await pumpKoraWidget(
        tester,
        SeatMap(seats: seats, onSeatTap: (seat) => tapped = seat),
      );

      await tester.tap(find.text('1B'));
      await tester.pump();

      expect(tapped, isNull);
    });

    testWidgets('tap sur un siège bloqué ne déclenche rien', (tester) async {
      KoraSeat? tapped;
      await pumpKoraWidget(
        tester,
        SeatMap(seats: seats, onSeatTap: (seat) => tapped = seat),
      );

      await tester.tap(find.text('1D'));
      await tester.pump();

      expect(tapped, isNull);
    });

    testWidgets(
      'tap sur un siège déjà sélectionné redéclenche onSeatTap (toggle géré par l\'appelant)',
      (tester) async {
        KoraSeat? tapped;
        await pumpKoraWidget(
          tester,
          SeatMap(seats: seats, onSeatTap: (seat) => tapped = seat),
        );

        await tester.tap(find.text('1C'));
        await tester.pump();

        expect(tapped?.label, '1C');
      },
    );
  });
}
