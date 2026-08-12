import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('TicketView — golden', () {
    testWidgets('par défaut', (tester) async {
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
        surfaceSize: const Size(420, 420),
      );
      await expectLater(find.byType(TicketView), matchesGoldenFile('ticket_view_default.png'));
    });
  });
}
