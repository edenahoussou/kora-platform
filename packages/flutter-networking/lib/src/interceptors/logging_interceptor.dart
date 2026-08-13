import 'package:dio/dio.dart';
import 'package:logger/logger.dart';

/// Journalise chaque requête, réponse et erreur passant par [Dio].
///
/// À activer uniquement en dev/staging (voir `NetworkConfig.enableLogging`)
/// — jamais en prod, ni même en profile/release "de test", pour ne jamais
/// exposer de token ou de donnée sensible dans les logs.
class LoggingInterceptor extends Interceptor {
  LoggingInterceptor({Logger? logger}) : _logger = logger ?? Logger();

  final Logger _logger;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    _logger.d('➡️  ${options.method} ${options.uri}');
    handler.next(options);
  }

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    _logger.d('✅ ${response.statusCode} ${response.requestOptions.uri}');
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    _logger.e(
      '❌ ${err.requestOptions.method} ${err.requestOptions.uri}',
      error: err.error ?? err,
    );
    handler.next(err);
  }
}
