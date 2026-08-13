import 'dart:async';

import 'package:dio/dio.dart';

import 'network_cache.dart';

/// Header ajouté à une réponse resservie depuis le cache, pour que l'app
/// puisse par exemple afficher un bandeau "données hors ligne".
const kCacheHeader = 'x-served-from-cache';

/// Met en cache chaque réponse `GET` réussie, et la ressert automatiquement
/// si une requête `GET` identique échoue plus tard pour une raison de
/// connectivité (pas de réseau).
///
/// Opt-in : n'est ajouté à [DioClient] que si un [NetworkCache] est fourni
/// à sa construction. Volontairement limité aux requêtes `GET` — une
/// réponse en cache pour un `POST`/`PUT`/`DELETE` n'aurait pas de sens
/// (ces requêtes mutent un état côté serveur, voir [OfflineRequestQueue]
/// pour la gestion hors ligne de ces méthodes).
class CacheInterceptor extends Interceptor {
  CacheInterceptor(this._cache, {this.maxAge = const Duration(minutes: 5)});

  final NetworkCache _cache;

  /// Durée pendant laquelle une réponse en cache est considérée valide.
  final Duration maxAge;

  static const _retryableForCache = {
    DioExceptionType.connectionError,
    DioExceptionType.connectionTimeout,
    DioExceptionType.receiveTimeout,
  };

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    if (response.requestOptions.method.toUpperCase() == 'GET') {
      unawaited(
        _cache.write(_keyFor(response.requestOptions), {
          'data': response.data,
          'statusCode': response.statusCode,
        }, maxAge: maxAge),
      );
    }
    handler.next(response);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final isGet = err.requestOptions.method.toUpperCase() == 'GET';
    if (!isGet || !_retryableForCache.contains(err.type)) {
      handler.next(err);
      return;
    }

    final cached = await _cache.read(_keyFor(err.requestOptions));
    if (cached is! Map) {
      handler.next(err);
      return;
    }

    handler.resolve(
      Response<dynamic>(
        requestOptions: err.requestOptions,
        data: cached['data'],
        statusCode: cached['statusCode'] as int?,
        headers: Headers.fromMap({
          kCacheHeader: const ['true'],
        }),
      ),
    );
  }

  String _keyFor(RequestOptions options) => '${options.method} ${options.uri}';
}
