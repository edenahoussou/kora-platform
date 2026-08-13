import 'dart:async';

import 'package:dio/dio.dart';

/// Clé interne (`RequestOptions.extra`) utilisée pour compter les
/// tentatives déjà effectuées sur une requête donnée.
const _retryCountKey = 'flutter_networking.retry_count';

/// Rejoue automatiquement une requête qui a échoué pour une raison
/// **transitoire** (timeout, connexion perdue) — jamais pour une erreur
/// serveur (4xx/5xx) qui, elle, a de bonnes chances d'échouer à nouveau.
///
/// Ne rejoue que les méthodes HTTP idempotentes ([NetworkConfig.
/// retryableMethods], par défaut GET/HEAD/PUT/DELETE/OPTIONS) : un `POST`
/// (création de réservation, paiement...) n'est jamais rejoué
/// automatiquement, pour ne pas risquer de le dupliquer silencieusement.
class RetryInterceptor extends Interceptor {
  RetryInterceptor({
    required Dio dio,
    this.maxRetries = 2,
    this.retryableMethods = const {'GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'},
    Duration Function(int attempt)? retryDelay,
  }) : _dio = dio, // ignore: prefer_initializing_formals
       _retryDelay = retryDelay ?? _defaultRetryDelay;

  final Dio _dio;
  final int maxRetries;
  final Set<String> retryableMethods;
  final Duration Function(int attempt) _retryDelay;

  static Duration _defaultRetryDelay(int attempt) =>
      Duration(milliseconds: 300 * attempt);

  static const _retryableErrorTypes = {
    DioExceptionType.connectionTimeout,
    DioExceptionType.sendTimeout,
    DioExceptionType.receiveTimeout,
    DioExceptionType.connectionError,
  };

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    if (!_shouldRetry(err)) {
      handler.next(err);
      return;
    }

    final attempt = (err.requestOptions.extra[_retryCountKey] as int? ?? 0) + 1;
    err.requestOptions.extra[_retryCountKey] = attempt;

    await Future<void>.delayed(_retryDelay(attempt));

    try {
      final response = await _dio.fetch<dynamic>(err.requestOptions);
      handler.resolve(response);
    } on DioException catch (retryError) {
      handler.next(retryError);
    }
  }

  bool _shouldRetry(DioException err) {
    if (maxRetries <= 0) return false;
    if (!_retryableErrorTypes.contains(err.type)) return false;
    if (!retryableMethods.contains(err.requestOptions.method.toUpperCase())) {
      return false;
    }
    final attempt = err.requestOptions.extra[_retryCountKey] as int? ?? 0;
    return attempt < maxRetries;
  }
}
