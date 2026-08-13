import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('ApiResult', () {
    test('success expose la donnée et isSuccess/isFailure', () {
      const result = ApiResult<int>.success(42);

      expect(result.isSuccess, isTrue);
      expect(result.isFailure, isFalse);
      expect(result, isA<ApiSuccess<int>>());
      expect((result as ApiSuccess<int>).data, 42);
    });

    test('failure expose l\'exception', () {
      const exception = NetworkApiException();
      const result = ApiResult<int>.failure(exception);

      expect(result.isFailure, isTrue);
      expect(result.isSuccess, isFalse);
      expect((result as ApiFailure<int>).exception, exception);
    });

    test('map transforme la donnée en cas de succès', () {
      const result = ApiResult<int>.success(2);

      final mapped = result.map((data) => data * 10);

      expect((mapped as ApiSuccess<int>).data, 20);
    });

    test('map propage l\'échec sans appeler la transformation', () {
      const result = ApiResult<int>.failure(NetworkApiException());
      var called = false;

      final mapped = result.map((data) {
        called = true;
        return data * 10;
      });

      expect(called, isFalse);
      expect(mapped, isA<ApiFailure<int>>());
    });

    test('fold retourne la bonne branche', () {
      const success = ApiResult<int>.success(1);
      const failure = ApiResult<int>.failure(NotFoundApiException());

      expect(
        success.fold(onSuccess: (d) => 'ok:$d', onFailure: (e) => 'ko'),
        'ok:1',
      );
      expect(
        failure.fold(
          onSuccess: (d) => 'ok:$d',
          onFailure: (e) => 'ko:${e.statusCode}',
        ),
        'ko:404',
      );
    });
  });
}
