import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_http_client_adapter.dart';

void main() {
  group('CacheInterceptor', () {
    test(
      'ressert la dernière réponse GET connue quand la requête échoue faute de réseau',
      () async {
        final adapter = FakeHttpClientAdapter([
          (options) async => jsonResponseBody({'name': 'Ada'}, 200),
          (options) async => throw DioException(
            requestOptions: options,
            type: DioExceptionType.connectionError,
          ),
        ]);
        final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
          ..httpClientAdapter = adapter
          ..interceptors.add(CacheInterceptor(InMemoryNetworkCache()));

        final first = await dio.get<Map<String, dynamic>>('/profile');
        expect(first.data, {'name': 'Ada'});

        final second = await dio.get<Map<String, dynamic>>('/profile');
        expect(second.data, {'name': 'Ada'});
        expect(second.headers.value(kCacheHeader), 'true');
      },
    );

    test('propage l\'erreur si rien n\'est en cache', () async {
      final adapter = FakeHttpClientAdapter([
        (options) async => throw DioException(
          requestOptions: options,
          type: DioExceptionType.connectionError,
        ),
      ]);
      final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
        ..httpClientAdapter = adapter
        ..interceptors.add(CacheInterceptor(InMemoryNetworkCache()));

      await expectLater(
        () => dio.get<dynamic>('/profile'),
        throwsA(isA<DioException>()),
      );
    });

    test('ne met jamais en cache une requête POST', () async {
      final cache = InMemoryNetworkCache();
      final adapter = FakeHttpClientAdapter([
        (options) async => jsonResponseBody({'created': true}, 201),
        (options) async => throw DioException(
          requestOptions: options,
          type: DioExceptionType.connectionError,
        ),
      ]);
      final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
        ..httpClientAdapter = adapter
        ..interceptors.add(CacheInterceptor(cache));

      await dio.post<dynamic>('/bookings');

      await expectLater(
        () => dio.post<dynamic>('/bookings'),
        throwsA(isA<DioException>()),
      );
    });
  });
}
