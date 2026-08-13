import 'package:dio/dio.dart';

import '../errors/api_exception.dart';
import '../errors/api_exception_mapper.dart';

/// Dernier interceptor de la chaîne d'erreur : traduit systématiquement le
/// [DioException] en [ApiException] via [ApiExceptionMapper], et l'attache
/// à `DioException.error` avant de laisser l'erreur remonter à l'appelant.
///
/// Dio impose que tout appel (`dio.get(...)`, `dio.post(...)`...) qui
/// échoue lève un [DioException] — on ne peut pas changer ce type-là.
/// C'est pour ça que l'exception typée n'est pas levée directement : elle
/// est portée par `DioException.error`, lisible via l'extension
/// [DioExceptionX.apiException] :
/// ```dart
/// try {
///   await dio.get('/users/me');
/// } on DioException catch (e) {
///   final apiException = e.apiException; // ApiException, jamais null
/// }
/// ```
class ErrorInterceptor extends Interceptor {
  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final apiException = ApiExceptionMapper.fromDioException(err);
    handler.next(
      err.copyWith(error: apiException, message: apiException.message),
    );
  }
}

/// Accès pratique à l'[ApiException] portée par un [DioException] déjà
/// passé par [ErrorInterceptor]. Retombe sur [UnknownApiException] dans le
/// cas — normalement impossible si [ErrorInterceptor] est bien enregistré
/// sur le [Dio] utilisé — où `error` n'a pas été peuplé.
extension DioExceptionX on DioException {
  ApiException get apiException {
    final current = error;
    return current is ApiException
        ? current
        : UnknownApiException(message ?? 'Une erreur inattendue est survenue.');
  }
}
