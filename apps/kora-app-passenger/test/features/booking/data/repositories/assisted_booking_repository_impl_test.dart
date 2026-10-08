// Les refus du serveur, traduits pour le voyageur.
//
// La demande d'assistance est le seul appel du parcours qui peut revenir
// en 403 : la politique serveur y exige une permission que tous les
// comptes ne portent pas. Sans traduction, l'écran affichait le message
// brut de Laravel — « This action is unauthorized. » — en anglais, sans
// dire quoi faire ensuite.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/assisted_booking_local_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/assisted_booking_remote_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/repositories/assisted_booking_repository_impl.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/assisted_booking.dart';
import 'package:mocktail/mocktail.dart';

class MockRemote extends Mock implements AssistedBookingRemoteDataSource {}

class MockLocal extends Mock implements AssistedBookingLocalDataSource {}

void main() {
  late MockRemote remote;
  late AssistedBookingRepositoryImpl repository;

  DioException httpError(int statusCode, {Object? body}) {
    final options = RequestOptions(path: '/api/v1/bus/bookings/abc/assistance');
    return DioException(
      requestOptions: options,
      type: DioExceptionType.badResponse,
      response: Response<dynamic>(
        requestOptions: options,
        statusCode: statusCode,
        data: body,
      ),
    );
  }

  Future<Failure?> requestFailure(DioException error) async {
    when(
      () => remote.request(
        reservationId: any(named: 'reservationId'),
        channel: any(named: 'channel'),
        contactName: any(named: 'contactName'),
        contactPhone: any(named: 'contactPhone'),
        idempotencyKey: any(named: 'idempotencyKey'),
      ),
    ).thenThrow(error);

    final result = await repository.request(
      reservationId: 'abc',
      channel: AssistanceChannel.whatsapp,
      contactName: 'Kévine',
      contactPhone: '+22990000000',
      idempotencyKey: 'key-1',
    );

    return result.fold((failure) => failure, (_) => null);
  }

  // `any(named: 'channel')` a besoin d'une valeur de repli : mocktail ne
  // sait pas inventer un enum.
  setUpAll(() => registerFallbackValue(AssistanceChannel.whatsapp));

  setUp(() {
    remote = MockRemote();
    repository = AssistedBookingRepositoryImpl(remote, MockLocal());
  });

  test(
    'traduit le 403 en français et renvoie vers le paiement en ligne',
    () async {
      final failure = await requestFailure(
        httpError(403, body: {'message': 'This action is unauthorized.'}),
      );

      expect(failure, isA<AuthFailure>());
      // Le message anglais du serveur ne doit jamais atteindre l'écran.
      expect(failure!.message, isNot(contains('unauthorized')));
      expect(failure.message, contains('agent'));
      expect(failure.message, contains('en ligne'));
    },
  );

  test('garde le message du support injoignable pour le 503 connu', () async {
    final failure = await requestFailure(
      httpError(
        503,
        body: {
          'error': {'code': 'assisted_booking_support_unavailable'},
        },
      ),
    );

    expect(failure, isA<ServerFailure>());
    expect(failure!.message, contains('injoignable'));
  });
}
