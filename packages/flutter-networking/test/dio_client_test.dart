import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/fake_http_client_adapter.dart';

void main() {
  group('DioClient', () {
    test('applique la NetworkConfig (baseUrl, timeouts, headers)', () {
      final client = DioClient(
        config: const NetworkConfig(
          baseUrl: 'https://api.kora.test/v1',
          connectTimeout: Duration(seconds: 7),
          maxRetries: 0,
        ),
        getAccessToken: () async => null,
      );

      expect(client.dio.options.baseUrl, 'https://api.kora.test/v1');
      expect(client.dio.options.connectTimeout, const Duration(seconds: 7));
      expect(client.dio.options.headers['Content-Type'], 'application/json');
    });

    test('injecte le token via AuthInterceptor sur chaque requête', () async {
      final adapter = FakeHttpClientAdapter([
        (options) async => jsonResponseBody({'ok': true}, 200),
      ]);
      final client = DioClient(
        config: const NetworkConfig(
          baseUrl: 'https://api.kora.test',
          maxRetries: 0,
        ),
        getAccessToken: () async => 'abc123',
      );
      client.dio.httpClientAdapter = adapter;

      await client.dio.get<dynamic>('/profile');

      expect(
        adapter.capturedRequests.single.headers['Authorization'],
        'Bearer abc123',
      );
    });

    test('traduit une erreur serveur en ApiException typée', () async {
      final adapter = FakeHttpClientAdapter([
        (options) async =>
            jsonResponseBody({'message': 'Session expirée'}, 401),
      ]);
      final client = DioClient(
        config: const NetworkConfig(
          baseUrl: 'https://api.kora.test',
          maxRetries: 0,
        ),
        getAccessToken: () async => null,
      );
      client.dio.httpClientAdapter = adapter;

      try {
        await client.dio.get<dynamic>('/profile');
        fail('devait lever une DioException');
      } on DioException catch (e) {
        expect(e.apiException, isA<UnauthorizedApiException>());
      }
    });

    test(
      'rejoue une requête GET en erreur transitoire quand maxRetries > 0',
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
        final client = DioClient(
          config: const NetworkConfig(
            baseUrl: 'https://api.kora.test',
            maxRetries: 1,
          ),
          getAccessToken: () async => null,
        );
        client.dio.httpClientAdapter = adapter;

        final response = await client.dio.get<dynamic>('/profile');

        expect(response.data, {'ok': true});
        expect(attempts, 2);
      },
    );

    test(
      'ressert une réponse en cache quand un NetworkCache est fourni',
      () async {
        final adapter = FakeHttpClientAdapter([
          (options) async => jsonResponseBody({'name': 'Ada'}, 200),
          (options) async => throw DioException(
            requestOptions: options,
            type: DioExceptionType.connectionError,
          ),
        ]);
        final client = DioClient(
          config: const NetworkConfig(
            baseUrl: 'https://api.kora.test',
            maxRetries: 0,
          ),
          getAccessToken: () async => null,
          cache: InMemoryNetworkCache(),
        );
        client.dio.httpClientAdapter = adapter;

        await client.dio.get<dynamic>('/profile');
        final cached = await client.dio.get<dynamic>('/profile');

        expect(cached.data, {'name': 'Ada'});
        expect(cached.headers.value(kCacheHeader), 'true');
      },
    );
  });
}
