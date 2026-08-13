import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_http_client_adapter.dart';

void main() {
  group('ErrorInterceptor', () {
    Dio buildDio(FakeAdapterStep step) {
      final adapter = FakeHttpClientAdapter([step]);
      return Dio(BaseOptions(baseUrl: 'https://api.test'))
        ..httpClientAdapter = adapter
        ..interceptors.add(ErrorInterceptor());
    }

    test(
      '401 -> UnauthorizedApiException, lisible via DioException.apiException',
      () async {
        final dio = buildDio(
          (options) async =>
              jsonResponseBody({'message': 'Session expirée'}, 401),
        );

        try {
          await dio.get<dynamic>('/profile');
          fail('devait lever une DioException');
        } on DioException catch (e) {
          expect(e.apiException, isA<UnauthorizedApiException>());
          expect(e.apiException.message, 'Session expirée');
          expect(e.apiException.statusCode, 401);
        }
      },
    );

    test('422 -> ValidationApiException avec les erreurs de champ', () async {
      final dio = buildDio(
        (options) async => jsonResponseBody({
          'message': 'The given data was invalid.',
          'errors': {
            'email': ['Cet email est déjà pris.'],
          },
        }, 422),
      );

      try {
        await dio.get<dynamic>('/register');
        fail('devait lever une DioException');
      } on DioException catch (e) {
        final apiException = e.apiException;
        expect(apiException, isA<ValidationApiException>());
        expect((apiException as ValidationApiException).fieldErrors['email'], [
          'Cet email est déjà pris.',
        ]);
      }
    });

    test('500 -> ServerApiException', () async {
      final dio = buildDio(
        (options) async => jsonResponseBody({'message': 'Boom'}, 500),
      );

      try {
        await dio.get<dynamic>('/profile');
        fail('devait lever une DioException');
      } on DioException catch (e) {
        expect(e.apiException, isA<ServerApiException>());
        expect(e.apiException.statusCode, 500);
      }
    });

    test('erreur de connexion -> NetworkApiException', () async {
      final dio = buildDio(
        (options) async => throw DioException(
          requestOptions: options,
          type: DioExceptionType.connectionError,
        ),
      );

      try {
        await dio.get<dynamic>('/profile');
        fail('devait lever une DioException');
      } on DioException catch (e) {
        expect(e.apiException, isA<NetworkApiException>());
      }
    });

    test('timeout -> TimeoutApiException', () async {
      final dio = buildDio(
        (options) async => throw DioException(
          requestOptions: options,
          type: DioExceptionType.receiveTimeout,
        ),
      );

      try {
        await dio.get<dynamic>('/profile');
        fail('devait lever une DioException');
      } on DioException catch (e) {
        expect(e.apiException, isA<TimeoutApiException>());
      }
    });

    test(
      'sans ErrorInterceptor, apiException retombe sur UnknownApiException',
      () {
        final err = DioException(
          requestOptions: RequestOptions(path: '/x'),
          type: DioExceptionType.unknown,
        );

        expect(err.apiException, isA<UnknownApiException>());
      },
    );
  });
}
