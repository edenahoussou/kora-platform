import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/trip_log/data/models/trip_log_dtos.dart';
import 'package:kora_ops_mobile/features/trip_log/domain/entities/trip_log.dart';

void main() {
  test('lit le carnet de bord renvoyé par le serveur', () {
    final TripLog log =
        TripLogDtos.tripLog(TripLogDtos.unwrap(<String, dynamic>{
      'data': <String, dynamic>{
        'departure_uuid': 'dep-1',
        'bus': <String, dynamic>{'registration_number': 'AB 1234 RB'},
        'last_known_odometer_km': 182300,
        'odometer': <String, dynamic>{
          'start': <String, dynamic>{
            'value_km': 182450,
            'recorded_at': '2026-10-27T07:55:00.000Z',
            'recorded_by': 'Koffi',
          },
          'end': <String, dynamic>{
            'value_km': 182612,
            'recorded_at': '2026-10-27T11:40:00.000Z',
          },
          'distance_km': 162,
        },
        'expenses': <Map<String, dynamic>>[
          <String, dynamic>{
            'uuid': 'exp-1',
            'client_uuid': 'cli-1',
            'kind': 'fuel',
            'amount': 44540,
            'liters': 65.5,
            'unit_price': 680,
            'station_label': 'Total Godomey',
            'payment_channel': 'cash',
            'occurred_at': '2026-10-27T08:30:00.000Z',
            'status': 'pending',
            'has_photo': true,
          },
          <String, dynamic>{
            'client_uuid': 'cli-2',
            'kind': 'border_tax',
            'amount': 5000,
            'checkpoint_label': 'Hillacondji',
            'country_code': 'TG',
            'occurred_at': '2026-10-27T10:00:00.000Z',
            'status': 'approved',
          },
        ],
      },
    }));

    expect(log.busRegistration, 'AB 1234 RB');
    expect(log.start?.valueKm, 182450);
    expect(log.distanceKm, 162);
    expect(log.latestKm, 182612);
    expect(log.expenses, hasLength(2));
    expect(log.expenses.first.kind, TripExpenseKind.fuel);
    expect(log.expenses.first.liters, 65.5);
    expect(log.expenses.first.statusLabel, 'En attente de validation');
    expect(log.expenses.last.kind, TripExpenseKind.borderTax);
    expect(log.expenses.last.statusLabel, 'Validée');
    expect(log.totalFuelLiters, 65.5);
  });

  test('un brouillon survit à la file hors ligne', () {
    final TripExpenseDraft draft = TripExpenseDraft(
      clientUuid: 'cli-3',
      departureUuid: 'dep-1',
      kind: TripExpenseKind.fuel,
      occurredAt: DateTime(2026, 10, 27, 9),
      liters: 40.25,
      unitPrice: 680,
      stationLabel: 'Oryx Sèmè',
      paymentChannel: TripPaymentChannel.mtnMomo,
    );

    final TripExpenseDraft restored = TripLogDtos.draftFromJson(
      TripLogDtos.draftToJson(draft),
      photoPath: '/tmp/ticket.jpg',
    );

    expect(restored.liters, 40.25);
    expect(restored.paymentChannel, TripPaymentChannel.mtnMomo);
    expect(restored.effectiveAmount, 27370);
    expect(restored.toPendingExpense().pendingSync, isTrue);
    expect(restored.toPendingExpense().hasPhoto, isTrue);
  });
}
