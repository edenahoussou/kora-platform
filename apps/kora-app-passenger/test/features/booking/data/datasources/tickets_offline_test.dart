// L'onglet Voyages hors réseau (APP-PAY-006).
//
// C'est par cette liste qu'on rejoint le QR : vide, elle rendrait le
// billet injoignable là où l'on en a le plus besoin — à l'embarquement.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_cache_local_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_remote_data_source.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/ticket.dart';
import 'package:mocktail/mocktail.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _MockDio extends Mock implements Dio {}

/// Un billet de `GET /tickets`, tel que le serveur le rend.
Map<String, dynamic> _ticketJson() => {
  'uuid': 'ticket-1',
  'short_code': 'K5F414H5',
  'status': 'confirmed',
  'qr_code': 'charge-signee-1',
  'reservation_uuid': 'resa-1',
  'amount': 9000,
  'origin': {'city': 'Cotonou', 'city_code': 'CTN'},
  'destination': {'city': 'Parakou', 'city_code': 'PKR'},
  'departure': {
    'uuid': 'departure-1',
    'departs_at': '2026-09-26T13:00:00+00:00',
    'line_code': 'L1',
  },
  'seat': {'code': '5C'},
  'passenger': {'first_name': 'Awa', 'last_name': 'KORA'},
};

Response<dynamic> _page() => Response<dynamic>(
  requestOptions: RequestOptions(path: '/api/v1/tickets'),
  statusCode: 200,
  data: {
    'data': [_ticketJson()],
    'meta': {'last_page': 1},
  },
);

DioException _offline() => DioException(
  requestOptions: RequestOptions(path: '/api/v1/tickets'),
  type: DioExceptionType.connectionError,
);

void main() {
  late _MockDio dio;
  late SharedPreferences prefs;
  late BookingRemoteDataSourceImpl dataSource;

  setUp(() async {
    dio = _MockDio();
    SharedPreferences.setMockInitialValues({});
    prefs = await SharedPreferences.getInstance();
    dataSource = BookingRemoteDataSourceImpl(
      dio,
      pending: BookingMockDataSource(),
      cache: BookingCacheLocalDataSource(prefs),
    );
  });

  Future<List<Ticket>> tickets() => dataSource.getTickets();

  test('les billets chargés sont gardés sur le téléphone', () async {
    when(
      () => dio.get<dynamic>(any(), queryParameters: any(named: 'queryParameters')),
    ).thenAnswer((_) async => _page());

    expect(await tickets(), hasLength(1));
    expect(BookingCacheLocalDataSource(prefs).tickets(), hasLength(1));
  });

  test('hors réseau, l’onglet Voyages sert la copie', () async {
    when(
      () => dio.get<dynamic>(any(), queryParameters: any(named: 'queryParameters')),
    ).thenAnswer((_) async => _page());
    await tickets();

    when(
      () => dio.get<dynamic>(any(), queryParameters: any(named: 'queryParameters')),
    ).thenThrow(_offline());

    final kept = await tickets();

    expect(kept, hasLength(1));
    expect(kept.single.reference, 'K5F414H5');
    expect(kept.single.reservationId, 'resa-1');
    expect(kept.single.seat.label, '5C');
  });

  test('les QR sont gardés sans rien ouvrir ni télécharger', () async {
    when(
      () => dio.get<dynamic>(any(), queryParameters: any(named: 'queryParameters')),
    ).thenAnswer((_) async => _page());

    await tickets();

    // La liste des voyages porte déjà la charge signée : le voyageur n'a
    // pas eu à ouvrir l'écran des QR pour l'avoir hors réseau.
    final kept = BookingCacheLocalDataSource(prefs).groupTickets('resa-1');
    expect(kept.single.qrCode, 'charge-signee-1');
    expect(kept.single.passengerName, 'Awa KORA');
    expect(kept.single.seatCode, '5C');
    expect(kept.single.status.isUsable, isTrue);
  });

  test('hors réseau, l’accueil garde ses villes', () async {
    final cities = Response<dynamic>(
      requestOptions: RequestOptions(path: '/api/v1/bus/cities'),
      statusCode: 200,
      data: {
        'data': [
          {
            'city': 'Cotonou',
            'country': 'BJ',
            'physical_stops': [
              {'uuid': 'stop-ctn', 'name': 'Cotonou'},
            ],
          },
        ],
      },
    );
    when(() => dio.get<dynamic>(any())).thenAnswer((_) async => cities);
    expect(await dataSource.getCities(), hasLength(1));

    when(() => dio.get<dynamic>(any())).thenThrow(_offline());

    // Sans villes, l'accueil affichait « Impossible de charger l'accueil ».
    final kept = await dataSource.getCities();
    expect(kept.single.name, 'Cotonou');
  });

  test('sans copie, l’échec remonte', () async {
    when(
      () => dio.get<dynamic>(any(), queryParameters: any(named: 'queryParameters')),
    ).thenThrow(_offline());

    await expectLater(tickets(), throwsA(isA<DioException>()));
  });
}
