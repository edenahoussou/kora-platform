// Les billets émis restent consultables sans réseau (APP-PAY-006).
//
// Le QR se présente au convoyeur, souvent là où le réseau ne passe pas :
// ce que le serveur a rendu une fois doit se relire ensuite, hors ligne.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_cache_local_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/reservation_remote_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/repositories/reservation_repository_impl.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_ticket.dart';
import 'package:mocktail/mocktail.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _MockDio extends Mock implements Dio {}

/// La réponse de `GET bookings/{uuid}/tickets`, telle que le serveur la
/// rend.
Map<String, dynamic> _ticketsJson() => {
  'data': [
    {
      'uuid': 'ticket-1',
      'short_code': 'K5F414H5',
      'status': 'issued',
      'qr_code': 'charge-signee-1',
      'passenger': {'first_name': 'Awa', 'last_name': 'KORA'},
      'segments': [
        {'seat_code': '5C'},
      ],
    },
    {
      'uuid': 'ticket-2',
      'short_code': 'K5F414H6',
      'status': 'issued',
      'qr_code': 'charge-signee-2',
      'passenger': {'first_name': 'Dolg', 'last_name': 'BOSS'},
      'segments': [
        {'seat_code': '5D'},
      ],
    },
  ],
};

DioException _offline() => DioException(
  requestOptions: RequestOptions(path: '/api/v1/bookings/resa-1/tickets'),
  type: DioExceptionType.connectionError,
);

void main() {
  late _MockDio dio;
  late SharedPreferences prefs;
  late ReservationRepositoryImpl repository;

  setUp(() async {
    dio = _MockDio();
    SharedPreferences.setMockInitialValues({});
    prefs = await SharedPreferences.getInstance();
    repository = ReservationRepositoryImpl(
      ReservationRemoteDataSource(dio),
      BookingCacheLocalDataSource(prefs),
    );
  });

  Future<List<GroupTicket>> load() async {
    final result = await repository.bookingTickets('resa-1');
    return result.getOrElse(() => const []);
  }

  test('les billets servis par l’API sont gardés sur le téléphone', () async {
    when(() => dio.get<dynamic>(any())).thenAnswer(
      (_) async => Response<dynamic>(
        requestOptions: RequestOptions(path: 'tickets'),
        statusCode: 200,
        data: _ticketsJson(),
      ),
    );

    final tickets = await load();

    expect(tickets, hasLength(2));
    // Servis par le réseau : rien à signaler au voyageur.
    expect(tickets.every((ticket) => ticket.isStale), isFalse);
    expect(
      BookingCacheLocalDataSource(prefs).groupTickets('resa-1'),
      hasLength(2),
    );
  });

  test('hors ligne, la copie du téléphone prend le relais', () async {
    when(() => dio.get<dynamic>(any())).thenAnswer(
      (_) async => Response<dynamic>(
        requestOptions: RequestOptions(path: 'tickets'),
        statusCode: 200,
        data: _ticketsJson(),
      ),
    );
    await load();

    when(() => dio.get<dynamic>(any())).thenThrow(_offline());

    final tickets = await load();

    // Le QR signé, le nom, le siège : tout ce qu'il faut à l'embarquement.
    expect(tickets, hasLength(2));
    expect(tickets.first.qrCode, 'charge-signee-1');
    expect(tickets.first.passengerName, 'Awa KORA');
    expect(tickets.first.seatCode, '5C');
    // L'écran peut dire qu'il montre une copie.
    expect(tickets.every((ticket) => ticket.isStale), isTrue);
  });

  test('une panne du recalcul promo se dit en français', () async {
    when(() => dio.put<dynamic>(any(), data: any(named: 'data'))).thenThrow(
      DioException(
        requestOptions: RequestOptions(path: 'promo-code'),
        type: DioExceptionType.badResponse,
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: 'promo-code'),
          statusCode: 500,
          data: {'message': 'Server Error'},
        ),
      ),
    );

    final result = await repository.updatePromoCode(
      reservationId: 'resa-1',
      promoCode: 'TEST2000',
    );

    // Le « Server Error » de Laravel ne dit rien au voyageur ; sa
    // réservation, elle, tient toujours.
    expect(
      result.fold((failure) => failure.message, (_) => ''),
      contains('n’a pas pu être appliqué'),
    );
  });

  test('sans copie, l’échec reste un échec', () async {
    when(() => dio.get<dynamic>(any())).thenThrow(_offline());

    final result = await repository.bookingTickets('resa-1');

    expect(result.isLeft(), isTrue);
    expect(
      result.fold((failure) => failure, (_) => null),
      isA<NetworkFailure>(),
    );
  });

  test('une émission encore vide n’efface pas les billets gardés', () async {
    when(() => dio.get<dynamic>(any())).thenAnswer(
      (_) async => Response<dynamic>(
        requestOptions: RequestOptions(path: 'tickets'),
        statusCode: 200,
        data: _ticketsJson(),
      ),
    );
    await load();

    // Le serveur répond, mais n'a encore rien émis : l'écran attend, et la
    // copie doit survivre.
    when(() => dio.get<dynamic>(any())).thenAnswer(
      (_) async => Response<dynamic>(
        requestOptions: RequestOptions(path: 'tickets'),
        statusCode: 200,
        data: {'data': <dynamic>[]},
      ),
    );
    expect(await load(), isEmpty);

    expect(
      BookingCacheLocalDataSource(prefs).groupTickets('resa-1'),
      hasLength(2),
    );
  });
}
