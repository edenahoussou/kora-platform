import 'package:dio/dio.dart';
import 'package:logger/logger.dart';

/// Point d'entrée unique pour tout appel réseau HTTP dans une app KORA.
/// C'est la SEULE classe qui configure `Dio` — chaque data source (ex:
/// `AuthRemoteDataSource`) reçoit une instance de [Dio] déjà prête à
/// l'emploi, sans jamais avoir besoin de connaître l'URL de base, les
/// timeouts ou la logique d'authentification.
///
/// **Ce package ne connaît ni l'URL de l'API, ni où est stocké le token,
/// ni si les logs doivent être actifs** — tout est injecté par l'app
/// consommatrice au moment de la construction. C'est ce qui permet à ce
/// même [DioClient] de servir aussi bien l'environnement dev que staging
/// ou prod (voir `core/env/app_env.dart` et `core/di/injector.dart` de
/// l'app), et d'être réutilisé par une future app KORA Driver sans
/// modification.
///
/// Ce que fait ce client automatiquement pour chaque requête :
/// 1. Ajoute la `baseUrl` et les timeouts fournis au constructeur
/// 2. Injecte le token d'accès dans le header `Authorization`, via
///    [getAccessToken], s'il existe
/// 3. Journalise les requêtes/réponses si [enableLogging] est activé
///
/// Exemple d'utilisation (typiquement dans `core/di/injector.dart` d'une
/// app) :
/// ```dart
/// DioClient(
///   baseUrl: AppEnv.current.apiBaseUrl,
///   connectTimeout: const Duration(milliseconds: 15000),
///   receiveTimeout: const Duration(milliseconds: 15000),
///   sendTimeout: const Duration(milliseconds: 15000),
///   getAccessToken: () => secureStorage.read(key: StorageKeys.accessToken),
///   enableLogging: kDebugMode,
/// )
/// ```
class DioClient {
  DioClient({
    required String baseUrl,
    required Duration connectTimeout,
    required Duration receiveTimeout,
    required Duration sendTimeout,
    required Future<String?> Function() getAccessToken,
    bool enableLogging = false,
    Map<String, dynamic> defaultHeaders = const {'Content-Type': 'application/json'},
  }) :
        // Nom de paramètre public volontairement différent du champ privé
        // (`getAccessToken` en API, `_getAccessToken` en interne) : un
        // "initializing formal" (`this._getAccessToken`) exposerait le nom
        // privé comme nom de paramètre nommé, inutilisable depuis un autre
        // fichier (core/di/injector.dart).
        // ignore: prefer_initializing_formals
        _getAccessToken = getAccessToken {
    _dio = Dio(
      BaseOptions(
        baseUrl: baseUrl,
        connectTimeout: connectTimeout,
        receiveTimeout: receiveTimeout,
        sendTimeout: sendTimeout,
        headers: defaultHeaders,
      ),
    );

    _dio.interceptors.addAll([
      _authInterceptor(),
      if (enableLogging) _loggingInterceptor(),
    ]);
  }

  late final Dio _dio;
  final Future<String?> Function() _getAccessToken;
  final Logger _logger = Logger();

  /// L'instance [Dio] prête à l'emploi, à injecter dans les data sources.
  Dio get dio => _dio;

  Interceptor _authInterceptor() {
    return InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _getAccessToken();
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) async {
        // Emplacement naturel pour, plus tard, tenter un refresh du token
        // sur une erreur 401 puis rejouer la requête. Volontairement
        // laissé simple pour l'instant (pas de vraie API à appeler).
        handler.next(error);
      },
    );
  }

  Interceptor _loggingInterceptor() {
    return InterceptorsWrapper(
      onRequest: (options, handler) {
        _logger.d('➡️  ${options.method} ${options.uri}');
        handler.next(options);
      },
      onResponse: (response, handler) {
        _logger.d('✅ ${response.statusCode} ${response.requestOptions.uri}');
        handler.next(response);
      },
      onError: (error, handler) {
        _logger.e(
          '❌ ${error.requestOptions.method} ${error.requestOptions.uri}',
          error: error,
        );
        handler.next(error);
      },
    );
  }
}
