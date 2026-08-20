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
          departureTime: '07:30',
          arrivalTime: '17:15',
          origin: 'Cotonou',
          destination: 'Malanville',
          duration: '≈ 9 h 45',
          price: '12 500 FCFA',
          seatsAvailable: 12,
        ),
        surfaceSize: const Size(380, 190),
      );
      await expectLater(
        find.byType(DepartureCard),
        matchesGoldenFile('departure_card_default.png'),
      );
    });

    testWidgets('sélectionnée', (tester) async {
      await pumpKoraWidget(
        tester,
        const DepartureCard(
          departureTime: '07:30',
          arrivalTime: '17:15',
          origin: 'Cotonou',
          destination: 'Malanville',
          duration: '≈ 9 h 45',
          price: '12 500 FCFA',
          seatsAvailable: 12,
          isSelected: true,
        ),
        surfaceSize: const Size(380, 190),
      );
      await expectLater(
        find.byType(DepartureCard),
        matchesGoldenFile('departure_card_selected.png'),
      );
    });

    testWidgets('complet', (tester) async {
      await pumpKoraWidget(
        tester,
        const DepartureCard(
          departureTime: '22:30',
          arrivalTime: '08:15',
          origin: 'Cotonou',
          destination: 'Malanville',
          duration: '≈ 9 h 45',
          price: '12 500 FCFA',
          isSoldOut: true,
        ),
        surfaceSize: const Size(380, 190),
      );
      await expectLater(
        find.byType(DepartureCard),
        matchesGoldenFile('departure_card_sold_out.png'),
      );
    });

    // La carte suit le thème depuis qu'une liste entière de cartes
    // blanches sur fond navy s'est révélée éblouissante : elle est bleu
    // marine en sombre, et ses textes forts passent au blanc.
    testWidgets('complet — mode sombre (carte navy)', (tester) async {
      await pumpKoraWidget(
        tester,
        const DepartureCard(
          departureTime: '07:30',
          arrivalTime: '17:15',
          origin: 'Cotonou',
          destination: 'Malanville',
          duration: '≈ 9 h 45',
          price: '12 500 FCFA',
          seatsAvailable: 12,
        ),
        surfaceSize: const Size(380, 190),
        dark: true,
      );
      await expectLater(
        find.byType(DepartureCard),
        matchesGoldenFile('departure_card_dark.png'),
      );
    });
  });
}
