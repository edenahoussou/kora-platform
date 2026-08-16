import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/widgets/passenger_list_tile.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';

void main() {
  const Passenger passenger = Passenger(
    id: 'p1',
    nomComplet: 'Aïcha Hounkpé',
    siege: '12A',
    statut: BoardingStatus.enAttente,
    segmentOrigine: 'Cotonou',
    segmentDestination: 'Porto-Novo',
    ticketId: 'ticket-1',
  );

  testWidgets('affiche le nom, le siège/segment et déclenche onTap',
      (WidgetTester tester) async {
    bool tapped = false;

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: PassengerListTile(
              passenger: passenger, onTap: () => tapped = true),
        ),
      ),
    );

    expect(find.text('Aïcha Hounkpé'), findsOneWidget);
    expect(find.textContaining('Siège 12A'), findsOneWidget);
    expect(find.text('En attente'), findsOneWidget);

    await tester.tap(find.byType(PassengerListTile));
    expect(tapped, isTrue);
  });
}
