import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_http_client_adapter.dart';

void main() {
  group('LoggingInterceptor', () {
    test('laisse passer une réponse réussie sans la modifier', () async {
      final adapter = FakeHttpClientAdapter([
        (options) async => jsonResponseBody({'ok': true}, 200),
      ]);
      final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
        ..httpClientAdapter = adapter
        ..interceptors.add(LoggingInterceptor());

      final response = await dio.get<Map<String, dynamic>>('/ping');

      expect(response.statusCode, 200);
      expect(response.data, {'ok': true});
    });

    test('laisse remonter une erreur inchangée', () async {
      final adapter = FakeHttpClientAdapter([
        (options) async => jsonResponseBody({'message': 'nope'}, 500),
      ]);
      final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
        ..httpClientAdapter = adapter
        ..interceptors.add(LoggingInterceptor());

      expect(
        () => dio.get<dynamic>('/boom'),
        throwsA(
          isA<DioException>().having(
            (e) => e.response?.statusCode,
            'statusCode',
            500,
          ),
        ),
      );
    });
  });
}
