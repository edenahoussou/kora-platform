import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('TicketView — golden', () {
    // Le logo (assets/brand/kora-logo.png, image raster) se décode de
    // façon asynchrone. Dans cet environnement de test, cette résolution
    // ne se termine jamais dans l'horloge simulée de `flutter test`
    // (`tester.pumpAndSettle()` reste bloqué indéfiniment dessus — testé
    // et confirmé y compris avec un PNG minimal généré à la volée, donc
    // indépendant du fichier lui-même). Un `tester.pump()` simple (sans
    // attendre le décodage) reste stable et rapide : le logo apparaît
    // simplement en blanc dans ce golden précis. Le rendu réel du logo a
    // été vérifié séparément dans Widgetbook (`flutter run -d chrome`,
    // navigateur réel — pas ce harnais de test), où il s'affiche
    // correctement.
    testWidgets('confirmé', (tester) async {
      await pumpKoraWidget(
        tester,
        const TicketView(
          passengerName: 'A. HOUNKPÈ',
          originCode: 'CTN',
          origin: 'Cotonou',
          destinationCode: 'MLV',
          destination: 'Malanville',
          date: '04 AOÛ',
          departureTime: '07:30',
          seatLabel: '4A',
          ticketCode: 'KE-20260804-78342',
        ),
        surfaceSize: const Size(420, 480),
      );
      await expectLater(find.byType(TicketView), matchesGoldenFile('ticket_view_default.png'));
    });

    testWidgets('annulé', (tester) async {
      await pumpKoraWidget(
        tester,
        const TicketView(
          passengerName: 'A. HOUNKPÈ',
          originCode: 'CTN',
          origin: 'Cotonou',
          destinationCode: 'MLV',
          destination: 'Malanville',
          date: '04 AOÛ',
          departureTime: '07:30',
          seatLabel: '4A',
          ticketCode: 'KE-20260804-78342',
          isCancelled: true,
        ),
        surfaceSize: const Size(420, 480),
      );
      await expectLater(find.byType(TicketView), matchesGoldenFile('ticket_view_cancelled.png'));
    });
  });
}
