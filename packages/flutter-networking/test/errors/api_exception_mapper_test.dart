import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart';
import 'package:flutter_test/flutter_test.dart';

DioException _error({
  DioExceptionType type = DioExceptionType.badResponse,
  int? statusCode,
  Object? data,
}) {
  final requestOptions = RequestOptions(path: '/x');
  return DioException(
    requestOptions: requestOptions,
    type: type,
    response: statusCode == null
        ? null
        : Response(
            requestOptions: requestOptions,
            statusCode: statusCode,
            data: data,
          ),
  );
}

void main() {
  group('ApiExceptionMapper', () {
    test(
      'connectionTimeout/sendTimeout/receiveTimeout -> TimeoutApiException',
      () {
        for (final type in [
          DioExceptionType.connectionTimeout,
          DioExceptionType.sendTimeout,
          DioExceptionType.receiveTimeout,
        ]) {
          expect(
            ApiExceptionMapper.fromDioException(_error(type: type)),
            isA<TimeoutApiException>(),
          );
        }
      },
    );

    test('connectionError -> NetworkApiException', () {
      expect(
        ApiExceptionMapper.fromDioException(
          _error(type: DioExceptionType.connectionError),
        ),
        isA<NetworkApiException>(),
      );
    });

    test('cancel -> CancelledApiException', () {
      expect(
        ApiExceptionMapper.fromDioException(
          _error(type: DioExceptionType.cancel),
        ),
        isA<CancelledApiException>(),
      );
    });

    test('401 -> UnauthorizedApiException', () {
      final exception = ApiExceptionMapper.fromDioException(
        _error(statusCode: 401, data: {'message': 'Non autorisé'}),
      );
      expect(exception, isA<UnauthorizedApiException>());
      expect(exception.message, 'Non autorisé');
      expect(exception.statusCode, 401);
    });

    test('403 -> ForbiddenApiException', () {
      expect(
        ApiExceptionMapper.fromDioException(_error(statusCode: 403)),
        isA<ForbiddenApiException>(),
      );
    });

    test('404 -> NotFoundApiException', () {
      expect(
        ApiExceptionMapper.fromDioException(_error(statusCode: 404)),
        isA<NotFoundApiException>(),
      );
    });

    test('422 -> ValidationApiException avec fieldErrors normalisés', () {
      final exception = ApiExceptionMapper.fromDioException(
        _error(
          statusCode: 422,
          data: {
            'message': 'Invalid',
            'errors': {
              'email': ['requis', 'format invalide'],
              'age': 42, // valeur non-liste : doit être tolérée
            },
          },
        ),
      );
      expect(exception, isA<ValidationApiException>());
      final validation = exception as ValidationApiException;
      expect(validation.fieldErrors['email'], ['requis', 'format invalide']);
      expect(validation.fieldErrors['age'], ['42']);
    });

    test('5xx -> ServerApiException avec le code renvoyé', () {
      final exception = ApiExceptionMapper.fromDioException(
        _error(statusCode: 503),
      );
      expect(exception, isA<ServerApiException>());
      expect(exception.statusCode, 503);
    });

    test('code inattendu -> UnknownApiException', () {
      expect(
        ApiExceptionMapper.fromDioException(_error(statusCode: 418)),
        isA<UnknownApiException>(),
      );
    });

    test('corps de réponse non-Map -> ne lève pas, message par défaut', () {
      final exception = ApiExceptionMapper.fromDioException(
        _error(statusCode: 500, data: 'plain text error'),
      );
      expect(exception, isA<ServerApiException>());
      expect(exception.message, isNotEmpty);
    });
  });
}
