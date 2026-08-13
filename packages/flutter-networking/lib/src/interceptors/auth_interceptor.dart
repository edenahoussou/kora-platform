import 'package:dio/dio.dart';

/// Injecte automatiquement le token d'accès dans le header `Authorization`
/// de chaque requête sortante.
///
/// Volontairement séparé de [DioClient] (plutôt qu'inline) pour être
/// testable isolément : on peut vérifier son comportement avec un
/// [RequestInterceptorHandler] réel, sans avoir à monter tout un [Dio].
///
/// Ne connaît ni où le token est stocké, ni comment il est rafraîchi —
/// seulement comment aller le chercher via [getAccessToken], fourni par
/// l'app consommatrice (typiquement un `FlutterSecureStorage.read`).
class AuthInterceptor extends Interceptor {
  AuthInterceptor({required Future<String?> Function() getAccessToken})
    // Nom de paramètre public volontairement différent du champ privé
    // (initializing formal impossible sans exposer `_getAccessToken` comme
    // nom de paramètre nommé, inutilisable depuis un autre fichier).
    // ignore: prefer_initializing_formals
    : _getAccessToken = getAccessToken;

  final Future<String?> Function() _getAccessToken;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await _getAccessToken();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }
}
