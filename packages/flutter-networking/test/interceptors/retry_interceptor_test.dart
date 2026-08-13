import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_http_client_adapter.dart';

void main() {
  group('RetryInterceptor', () {
    test(
      'rejoue une requête GET après une erreur transitoire, puis réussit',
      () async {
        var attempts = 0;
        final adapter = FakeHttpClientAdapter([
          (options) async {
            attempts++;
            throw DioException(
              requestOptions: options,
              type: DioExceptionType.connectionError,
            );
          },
          (options) async {
            attempts++;
            return jsonResponseBody({'ok': true}, 200);
          },
        ]);
        final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
          ..httpClientAdapter = adapter;
        dio.interceptors.add(
          RetryInterceptor(
            dio: dio,
            maxRetries: 2,
            retryDelay: (_) => Duration.zero,
          ),
        );

        final response = await dio.get<Map<String, dynamic>>('/profile');

        expect(response.data, {'ok': true});
        expect(attempts, 2);
      },
    );

    test('abandonne après épuisement des tentatives', () async {
      final adapter = FakeHttpClientAdapter([
        (options) async => throw DioException(
          requestOptions: options,
          type: DioExceptionType.connectionTimeout,
        ),
      ]);
      final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
        ..httpClientAdapter = adapter;
      dio.interceptors.add(
        RetryInterceptor(
          dio: dio,
          maxRetries: 2,
          retryDelay: (_) => Duration.zero,
        ),
      );

      await expectLater(
        () => dio.get<dynamic>('/profile'),
        throwsA(isA<DioException>()),
      );
      // 1 tentative initiale + 2 retries = 3 appels au transport.
      expect(adapter.callCount, 3);
    });

    test('ne rejoue jamais un POST (méthode non idempotente)', () async {
      final adapter = FakeHttpClientAdapter([
        (options) async => throw DioException(
          requestOptions: options,
          type: DioExceptionType.connectionError,
        ),
      ]);
      final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
        ..httpClientAdapter = adapter;
      dio.interceptors.add(
        RetryInterceptor(
          dio: dio,
          maxRetries: 2,
          retryDelay: (_) => Duration.zero,
        ),
      );

      await expectLater(
        () => dio.post<dynamic>('/bookings'),
        throwsA(isA<DioException>()),
      );
      expect(adapter.callCount, 1);
    });

    test('ne rejoue jamais une erreur serveur (4xx/5xx)', () async {
      final adapter = FakeHttpClientAdapter([
        (options) async => jsonResponseBody({'message': 'boom'}, 500),
      ]);
      final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
        ..httpClientAdapter = adapter;
      dio.interceptors.add(
        RetryInterceptor(
          dio: dio,
          maxRetries: 2,
          retryDelay: (_) => Duration.zero,
        ),
      );

      await expectLater(
        () => dio.get<dynamic>('/profile'),
        throwsA(isA<DioException>()),
      );
      expect(adapter.callCount, 1);
    });

    test('maxRetries = 0 désactive complètement le retry', () async {
      final adapter = FakeHttpClientAdapter([
        (options) async => throw DioException(
          requestOptions: options,
          type: DioExceptionType.connectionError,
        ),
      ]);
      final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
        ..httpClientAdapter = adapter;
      dio.interceptors.add(RetryInterceptor(dio: dio, maxRetries: 0));

      await expectLater(
        () => dio.get<dynamic>('/profile'),
        throwsA(isA<DioException>()),
      );
      expect(adapter.callCount, 1);
    });
  });
}
