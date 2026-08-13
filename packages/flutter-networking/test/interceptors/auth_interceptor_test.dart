import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_http_client_adapter.dart';

void main() {
  group('AuthInterceptor', () {
    late FakeHttpClientAdapter adapter;
    late Dio dio;

    setUp(() {
      adapter = FakeHttpClientAdapter([
        (options) async => jsonResponseBody({'ok': true}, 200),
      ]);
      dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
        ..httpClientAdapter = adapter;
    });

    test("ajoute l'en-tête Authorization quand un token existe", () async {
      dio.interceptors.add(
        AuthInterceptor(getAccessToken: () async => 'my-token'),
      );

      await dio.get<dynamic>('/profile');

      expect(
        adapter.capturedRequests.single.headers['Authorization'],
        'Bearer my-token',
      );
    });

    test("n'ajoute pas l'en-tête quand il n'y a pas de token", () async {
      dio.interceptors.add(AuthInterceptor(getAccessToken: () async => null));

      await dio.get<dynamic>('/profile');

      expect(
        adapter.capturedRequests.single.headers.containsKey('Authorization'),
        isFalse,
      );
    });

    test("n'ajoute pas l'en-tête pour un token vide", () async {
      dio.interceptors.add(AuthInterceptor(getAccessToken: () async => ''));

      await dio.get<dynamic>('/profile');

      expect(
        adapter.capturedRequests.single.headers.containsKey('Authorization'),
        isFalse,
      );
    });
  });
}
