import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/widgets/seat_map.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/seat.dart';

void main() {
  testWidgets('SeatMap affiche un chip par siège et sa légende',
      (WidgetTester tester) async {
    const seats = <Seat>[
      Seat(numero: '1A', statut: SeatStatus.occupe, passengerName: 'Test'),
      Seat(numero: '1B', statut: SeatStatus.libere),
      Seat(numero: '1C', statut: SeatStatus.occupe, libereAPartirDe: 'Bohicon'),
    ];

    await tester.pumpWidget(
      const MaterialApp(home: Scaffold(body: SeatMap(seats: seats))),
    );

    expect(find.text('1A'), findsOneWidget);
    expect(find.text('1B'), findsOneWidget);
    expect(find.text('1C'), findsOneWidget);
    expect(find.text('Libéré'), findsOneWidget);
    expect(find.text('Se libère bientôt'), findsOneWidget);
    expect(find.text('Occupé'), findsOneWidget);
  });
}
