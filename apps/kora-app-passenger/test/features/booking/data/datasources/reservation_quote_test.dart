// Ce que le data source envoie et relit sur les points d'entrée
// d'itinéraire : le devis, puis le blocage.
//
// Le test du modèle vérifie la lecture d'un devis ; celui-ci vérifie que
// le data source lui donne bien le bon objet. La distinction n'est pas
// théorique : `ItineraryController::quote` répond
// `response()->json(['data' => [...]])`, et lire ce corps à plat rendait
// un devis sans identifiant — que le blocage réclame juste après, en 422.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/constants/api_constants.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/reservation_remote_data_source.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/city.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_payer.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_quote.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/line.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:mocktail/mocktail.dart';

class MockDio extends Mock implements Dio {}

void main() {
  late MockDio dio;
  late ReservationRemoteDataSource dataSource;

  final departure = Departure(
    id: '56f674c2-7795-4427-9839-ff5de7345ade',
    line: const Line(
      id: 'l-1',
      code: 'L1',
      origin: City(id: 'Cotonou', name: 'Cotonou', code: 'COT'),
      destination: City(id: 'Abidjan', name: 'Abidjan', code: 'ABI'),
      duration: Duration(minutes: 1110),
      distanceKm: 0,
    ),
    departsAt: DateTime(2026, 9, 9, 7),
    comfort: BusComfort.confort,
    price: const Price(31600),
    seatsTotal: 55,
    seatsAvailable: 32,
    originStopId: 'd27a305c-4a4b-405c-9ba6-0371e69024dd',
    destinationStopId: '6b0c1666-6eaa-4b67-a216-df1667ae6be1',
  );

  /// La réponse de la staging, enveloppe comprise.
  Map<String, dynamic> quoteResponse() => {
    'data': {
      'itinerary_quote_uuid': 'e0f1a2b3-4c5d-6e7f-8091-a2b3c4d5e6f7',
      'trip_type': 'one_way',
      'total_amount': 31600,
      'currency': 'XOF',
      'expires_at': '2026-09-09T06:15:00+00:00',
      'legs': [
        {'leg_order': 1, 'quote_uuid': 'lq-1', 'final_amount': 31600},
      ],
      'pricing': {
        'subtotal_amount': 31600,
        'dynamic_adjustment_amount': 0,
        'group_discount': {'applied': false},
        'total_amount': 31600,
        'currency': 'XOF',
      },
    },
  };

  setUp(() {
    dio = MockDio();
    dataSource = ReservationRemoteDataSource(dio);

    when(() => dio.post<dynamic>(any(), data: any(named: 'data'))).thenAnswer(
      (_) async => Response<dynamic>(
        requestOptions: RequestOptions(path: '/'),
        statusCode: 200,
        data: quoteResponse(),
      ),
    );
  });

  test('déballe l’enveloppe `data` du devis', () async {
    // Sans ça le devis remonte vide : l'identifiant manque, et c'est le
    // blocage qui échoue, loin de la cause.
    final quote = await dataSource.groupQuote(
      departure: departure,
      passengers: 1,
    );

    expect(quote.id, 'e0f1a2b3-4c5d-6e7f-8091-a2b3c4d5e6f7');
    expect(quote.total, const Price(31600));
    expect(quote.passengerCount, 1);
    expect(quote.expiresAt, DateTime.utc(2026, 9, 9, 6, 15));
  });

  test('envoie le tronçon tel que le valide le serveur', () async {
    await dataSource.groupQuote(departure: departure, passengers: 5);

    final sent = verify(
      () => dio.post<dynamic>(
        ApiConstants.itineraryQuotes,
        data: captureAny(named: 'data'),
      ),
    ).captured.single;

    expect(sent, {
      'passenger_count': 5,
      'legs': [
        {
          'leg_order': 1,
          'departure_uuid': '56f674c2-7795-4427-9839-ff5de7345ade',
          'origin_stop_uuid': 'd27a305c-4a4b-405c-9ba6-0371e69024dd',
          'destination_stop_uuid': '6b0c1666-6eaa-4b67-a216-df1667ae6be1',
        },
      ],
    });
  });

  group('holdGroup', () {
    /// La réservation retenue, telle que la rend `ReservationResource`.
    Map<String, dynamic> holdResponse() => {
      'data': {
        'uuid': '3c9a77b1-2e4d-4f60-9a12-77c0de55aa01',
        'status': 'held',
        'expires_at': '2026-09-09T06:20:00+00:00',
        'total_amount': 31600,
        'currency': 'XOF',
        'segments': [
          {'seat_uuid': 'st-1', 'seat_code': '4A', 'status': 'held'},
        ],
      },
    };

    setUp(() {
      when(() => dio.post<dynamic>(any(), data: any(named: 'data'))).thenAnswer(
        (_) async => Response<dynamic>(
          requestOptions: RequestOptions(path: '/'),
          statusCode: 201,
          data: holdResponse(),
        ),
      );
    });

    const quote = GroupQuote(
      id: 'e0f1a2b3-4c5d-6e7f-8091-a2b3c4d5e6f7',
      subtotal: Price(31600),
      total: Price(31600),
      passengerCount: 1,
    );

    const seats = [Seat(id: 'st-1', label: '4A', row: 4, column: 1)];

    test('déballe l’enveloppe `data` du blocage', () async {
      // Même piège que le devis : `ReservationResource` enveloppe, et lire
      // à plat rendrait un blocage sans identifiant — donc impossible à
      // libérer, et un formulaire voyageur adressé à personne.
      final hold = await dataSource.holdGroup(
        quote: quote,
        seats: seats,
        payer: const GroupPayer.personal(),
        idempotencyKey: 'kora-1',
      );

      expect(hold.id, '3c9a77b1-2e4d-4f60-9a12-77c0de55aa01');
      expect(hold.seatLabels, ['4A']);
      expect(hold.total, const Price(31600));
      expect(hold.expiresAt, DateTime.utc(2026, 9, 9, 6, 20));
    });

    test('un paiement personnel ne déclare aucun payeur', () async {
      // Le serveur retombe alors sur le titulaire de la session. Envoyer
      // un payeur que l'app devine lui ferait créer un second compte pour
      // le même voyageur.
      await dataSource.holdGroup(
        quote: quote,
        seats: seats,
        payer: const GroupPayer.personal(),
        idempotencyKey: 'kora-1',
      );

      final sent =
          verify(
                () => dio.post<dynamic>(
                  ApiConstants.itineraryHolds,
                  data: captureAny(named: 'data'),
                ),
              ).captured.single
              as Map<String, dynamic>;

      expect(sent.containsKey('payer'), isFalse);
      expect(sent['itinerary_quote_uuid'], quote.id);
      expect(sent['idempotency_key'], 'kora-1');
      expect(sent['passengers'], [
        {
          'passenger_index': 0,
          'seat_assignments': [
            {'leg_order': 1, 'seat_uuid': 'st-1'},
          ],
        },
      ]);
    });

    test('un compte entreprise part comme payeur', () async {
      await dataSource.holdGroup(
        quote: quote,
        seats: seats,
        payer: const GroupPayer.company(accountId: 'ca-1', name: 'STB SARL'),
        idempotencyKey: 'kora-1',
      );

      final sent =
          verify(
                () => dio.post<dynamic>(any(), data: captureAny(named: 'data')),
              ).captured.single
              as Map<String, dynamic>;

      expect(sent['payer'], {
        'type': 'company',
        'company_account_uuid': 'ca-1',
      });
    });
  });
}
