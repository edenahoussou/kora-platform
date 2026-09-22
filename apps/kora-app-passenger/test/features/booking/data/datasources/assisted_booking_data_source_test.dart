// La réservation assistée côté données (MEZ-80) : ce qui part au serveur,
// ce qu'on lit de sa réponse, et la trace gardée sur le téléphone.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/constants/api_constants.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/assisted_booking_local_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/assisted_booking_remote_data_source.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/assisted_booking.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:mocktail/mocktail.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../../support/assistance_harness.dart';

class _MockDio extends Mock implements Dio {}

/// Le dossier tel que le rend `AssistedBookingResource`.
Map<String, dynamic> _dossierJson({
  String status = 'pending',
  Map<String, dynamic>? agency,
}) => {
  'data': {
    'uuid': 'dossier-1',
    'status': status,
    'channel': 'whatsapp',
    'reservation': {
      'uuid': 'resa-1',
      'reference': 'KBR-7X2K9P4Q',
      'status': 'assistance_pending',
    },
    'handling_agency': agency,
    'contact': {'name': 'Afi', 'phone': '+2290197000000'},
    'channels': {
      'call_phone': '+229010000001',
      'whatsapp_phone': '+229010000002',
      'call_url': 'tel:+229010000001',
      'whatsapp_url': 'https://wa.me/229010000002?text=Bonjour',
    },
    'whatsapp_message': 'Bonjour, je souhaite terminer cette réservation KORA',
    'snapshot': {
      'reservation_reference': 'KBR-7X2K9P4Q',
      'legs': [
        {
          'leg_order': 1,
          'origin': 'Cotonou',
          'destination': 'Bohicon',
          'departure_at': '2026-09-20T06:00:00+00:00',
        },
      ],
      'requested_seats': [
        {'passenger_index': 0, 'leg_order': 1, 'code': '7C'},
        {'passenger_index': 1, 'leg_order': 1, 'code': '7D'},
      ],
      'passenger_count': 2,
    },
    'amount': {'original': 5200, 'current': 4680, 'currency': 'XOF'},
    'valid_until': '2026-09-20T05:30:00+00:00',
    'actions': {'resume_online': true},
  },
};

void main() {
  late _MockDio dio;
  late AssistedBookingRemoteDataSource dataSource;

  setUp(() {
    dio = _MockDio();
    dataSource = AssistedBookingRemoteDataSource(dio);
  });

  Response<dynamic> ok(String path, Object body) => Response<dynamic>(
    requestOptions: RequestOptions(path: path),
    statusCode: 200,
    data: body,
  );

  group('envoi en assistance', () {
    setUp(() {
      when(
        () => dio.post<dynamic>(any(), data: any(named: 'data')),
      ).thenAnswer((_) async => ok('assistance', _dossierJson()));
    });

    test(
      'part sur la réservation, avec canal, contact et clé',
      () async {
        await dataSource.request(
          reservationId: 'resa-1',
          channel: AssistanceChannel.whatsapp,
          contactName: 'Afi',
          contactPhone: '+2290197000000',
          idempotencyKey: 'kora-assist-resa-1',
        );

        final captured = verify(
          () =>
              dio.post<dynamic>(captureAny(), data: captureAny(named: 'data')),
        ).captured;
        expect(captured.first, ApiConstants.bookingAssistance('resa-1'));
        // Plus d'agence : le dossier part au support central, c'est le
        // réseau qui affectera l'agence.
        expect(captured.last, {
          'channel': 'whatsapp',
          'contact_name': 'Afi',
          'contact_phone': '+2290197000000',
          'idempotency_key': 'kora-assist-resa-1',
        });
      },
    );

    test('lit la référence, les canaux, la validité et le snapshot', () async {
      final booking = await dataSource.request(
        reservationId: 'resa-1',
        channel: AssistanceChannel.whatsapp,
        contactName: 'Afi',
        contactPhone: '+2290197000000',
        idempotencyKey: 'kora-assist-resa-1',
      );

      // La même réservation, et son code métier : jamais d'identifiant
      // numérique.
      expect(booking.reservationId, 'resa-1');
      expect(booking.reference, 'KBR-7X2K9P4Q');
      expect(booking.status, AssistanceStatus.pending);
      // Les numéros sont ceux du support central, pas ceux d'une agence.
      expect(
        booking.support.whatsappUrl,
        startsWith('https://wa.me/229010000002'),
      );
      expect(booking.support.callUrl, 'tel:+229010000001');
      // Personne n'a encore pris le dossier.
      expect(booking.agency, isNull);
      expect(booking.validUntil, DateTime.utc(2026, 9, 20, 5, 30).toLocal());
      // Le montant recalculé l'emporte sur celui de l'envoi.
      expect(booking.amount, const Price(4680));
      expect(booking.legs.single.destination, 'Bohicon');
      expect(booking.seatLabels, ['7C', '7D']);
      expect(booking.passengerCount, 2);
      expect(booking.canResumeOnline, isTrue);
    });
  });

  test('chaque statut du serveur a le sien', () async {
    for (final (wire, status) in [
      ('pending', AssistanceStatus.pending),
      ('in_progress', AssistanceStatus.inProgress),
      ('finalized', AssistanceStatus.finalized),
      ('refused', AssistanceStatus.refused),
      ('unavailable', AssistanceStatus.unavailable),
      ('expired', AssistanceStatus.expired),
      ('cancelled', AssistanceStatus.cancelled),
      ('nouveau_statut', AssistanceStatus.unknown),
    ]) {
      when(
        () => dio.get<dynamic>(any()),
      ).thenAnswer((_) async => ok('assistance', _dossierJson(status: wire)));

      final booking = await dataSource.fetch('resa-1');
      expect(booking.status, status, reason: wire);
    }
  });

  test(
    'un dossier clos par une reprise en ligne n’est pas une annulation',
    () async {
      final json = _dossierJson(status: 'cancelled');
      ((json['data'] as Map)['reservation'] as Map)['status'] = 'held';
      when(
        () => dio.get<dynamic>(any()),
      ).thenAnswer((_) async => ok('assistance', json));

      final booking = await dataSource.fetch('resa-1');

      expect(booking.status, AssistanceStatus.resumedOnline);
      expect(booking.status.isDeadEnd, isFalse);
    },
  );

  test('l’agence affectée se lit dès que le réseau la nomme', () async {
    when(() => dio.get<dynamic>(any())).thenAnswer(
      (_) async => ok(
        'assistance',
        _dossierJson(
          status: 'in_progress',
          agency: {
            'uuid': 'agence-bohicon',
            'code': 'AG-000002',
            'name': 'Agence Bohicon',
            'city': 'Bohicon',
          },
        ),
      ),
    );

    final booking = await dataSource.fetch('resa-1');

    expect(booking.agency?.name, 'Agence Bohicon');
    expect(booking.agency?.code, 'AG-000002');
  });

  test(
    'la reprise renvoie chaque voyageur avec ses sièges par tronçon',
    () async {
      when(() => dio.post<dynamic>(any(), data: any(named: 'data'))).thenAnswer(
        (_) async => ok('resume', {
          'data': {
            'uuid': 'resa-1',
            'reference': 'KBR-7X2K9P4Q',
            'status': 'held',
            'amount': 5200,
            'currency': 'XOF',
            'expires_at': '2026-09-20T05:45:00+00:00',
            'seats': [
              {'seat_code': '7C'},
              {'seat_code': '7D'},
            ],
          },
        }),
      );

      final hold = await dataSource.resumeOnline(
        reservationId: 'resa-1',
        idempotencyKey: 'kora-resume-resa-1',
        seats: const [
          AssistanceSeat(
            passengerIndex: 0,
            legOrder: 1,
            seatId: 's-7c',
            label: '7C',
          ),
          AssistanceSeat(
            passengerIndex: 0,
            legOrder: 2,
            seatId: 's-2a',
            label: '2A',
          ),
          AssistanceSeat(
            passengerIndex: 1,
            legOrder: 1,
            seatId: 's-7d',
            label: '7D',
          ),
        ],
      );

      final captured = verify(
        () => dio.post<dynamic>(captureAny(), data: captureAny(named: 'data')),
      ).captured;
      expect(captured.first, ApiConstants.bookingAssistanceResume('resa-1'));
      expect(captured.last, {
        'idempotency_key': 'kora-resume-resa-1',
        'passengers': [
          {
            'passenger_index': 0,
            'seat_assignments': [
              {'leg_order': 1, 'seat_uuid': 's-7c'},
              {'leg_order': 2, 'seat_uuid': 's-2a'},
            ],
          },
          {
            'passenger_index': 1,
            'seat_assignments': [
              {'leg_order': 1, 'seat_uuid': 's-7d'},
            ],
          },
        ],
      });
      // La même réservation, de nouveau retenue et payable.
      expect(hold.id, 'resa-1');
      expect(hold.total, const Price(5200));
      expect(hold.seatLabels, ['7C', '7D']);
    },
  );

  group('trace locale', () {
    late AssistedBookingLocalDataSource local;

    setUp(() async {
      SharedPreferences.setMockInitialValues({});
      local = AssistedBookingLocalDataSource(
        await SharedPreferences.getInstance(),
      );
    });

    test('garde la demande, sièges compris, et la relit', () async {
      await local.remember(assistanceRecord());

      final records = local.records();
      expect(records, [assistanceRecord()]);
      expect(records.single.seats.single.seatId, 'seat-4a');
    });

    test(
      'un nouvel essai sur la même réservation ne la dédouble pas',
      () async {
        await local.remember(assistanceRecord());
        await local.remember(assistanceRecord());
        await local.remember(assistanceRecord(reservationId: 'resa-2'));

        expect(local.records().map((record) => record.reservationId), [
          'resa-2',
          'hold-groupe-1',
        ]);
      },
    );

    test('prévient à chaque écriture', () async {
      final notified = expectLater(local.changes, emits(null));
      await local.remember(assistanceRecord());
      await notified;
    });
  });
}
