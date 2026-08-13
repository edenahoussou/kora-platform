import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_http_client_adapter.dart';

void main() {
  group('OfflineRequestQueue', () {
    test('enqueue ajoute une requête en attente', () {
      final queue = OfflineRequestQueue();
      expect(queue.isEmpty, isTrue);

      queue.enqueue(RequestOptions(path: '/bookings', method: 'POST'));

      expect(queue.isNotEmpty, isTrue);
      expect(queue.pending, hasLength(1));
      expect(queue.pending.single.options.path, '/bookings');
    });

    test(
      'flush rejoue les requêtes en attente et les retire de la file',
      () async {
        final queue = OfflineRequestQueue()
          ..enqueue(RequestOptions(path: '/a', method: 'POST'))
          ..enqueue(RequestOptions(path: '/b', method: 'POST'));

        final adapter = FakeHttpClientAdapter([
          (options) async => jsonResponseBody({'ok': true}, 200),
        ]);
        final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
          ..httpClientAdapter = adapter;

        final flushed = await queue.flush(dio);

        expect(flushed.map((r) => r.options.path), ['/a', '/b']);
        expect(queue.isEmpty, isTrue);
      },
    );

    test(
      'flush s\'arrête à la première requête qui échoue encore, en préservant l\'ordre',
      () async {
        final queue = OfflineRequestQueue()
          ..enqueue(RequestOptions(path: '/a', method: 'POST'))
          ..enqueue(RequestOptions(path: '/b', method: 'POST'));

        var call = 0;
        final adapter = FakeHttpClientAdapter([
          (options) async {
            call++;
            if (call == 1) return jsonResponseBody({'ok': true}, 200);
            throw DioException(
              requestOptions: options,
              type: DioExceptionType.connectionError,
            );
          },
        ]);
        final dio = Dio(BaseOptions(baseUrl: 'https://api.test'))
          ..httpClientAdapter = adapter;

        final flushed = await queue.flush(dio);

        expect(flushed.map((r) => r.options.path), ['/a']);
        expect(queue.pending.map((r) => r.options.path), ['/b']);
      },
    );

    test('clear vide la file sans rejouer les requêtes', () {
      final queue = OfflineRequestQueue()
        ..enqueue(RequestOptions(path: '/a'))
        ..clear();

      expect(queue.isEmpty, isTrue);
    });
  });
}
