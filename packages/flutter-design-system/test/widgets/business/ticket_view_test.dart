import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../test_utils.dart';

void main() {
  group('TicketView', () {
    testWidgets('affiche toutes les informations du ticket', (tester) async {
      await pumpKoraWidget(
        tester,
        const TicketView(
          passengerName: 'Awa Kouassi',
          origin: 'Cotonou',
          destination: 'Porto-Novo',
          departureTime: '14:30',
          date: '12 Août 2026',
          seatLabel: '12A',
          ticketCode: 'KRA-284910',
        ),
      );

      expect(find.text('Awa Kouassi'), findsOneWidget);
      expect(find.text('Cotonou'), findsOneWidget);
      expect(find.text('Porto-Novo'), findsOneWidget);
      expect(find.text('14:30'), findsOneWidget);
      expect(find.text('12 Août 2026'), findsOneWidget);
      expect(find.text('12A'), findsOneWidget);
      expect(find.text('KRA-284910'), findsOneWidget);
    });
  });
}
