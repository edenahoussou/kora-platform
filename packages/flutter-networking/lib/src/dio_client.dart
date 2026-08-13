import 'package:dio/dio.dart';
import 'package:logger/logger.dart';

import 'cache/cache_interceptor.dart';
import 'cache/network_cache.dart';
import 'config/network_config.dart';
import 'interceptors/auth_interceptor.dart';
import 'interceptors/error_interceptor.dart';
import 'interceptors/logging_interceptor.dart';
import 'interceptors/retry_interceptor.dart';

/// Point d'entrée unique pour tout appel réseau HTTP dans une app KORA.
/// C'est la SEULE classe qui configure `Dio` — chaque data source (ex:
/// `AuthRemoteDataSource`) reçoit une instance de [Dio] déjà prête à
/// l'emploi, sans jamais avoir besoin de connaître l'URL de base, les
/// timeouts ou la logique d'authentification.
///
/// **Ce package ne connaît ni l'URL de l'API, ni où est stocké le token,
/// ni si les logs doivent être actifs** — tout est injecté par l'app
/// consommatrice au moment de la construction ([NetworkConfig] +
/// [getAccessToken]). C'est ce qui permet à ce même [DioClient] de servir
/// aussi bien l'environnement dev que staging ou prod (voir
/// `core/env/app_env.dart` et `core/di/injector.dart` de l'app), et d'être
/// réutilisé par une future app KORA Driver sans modification.
///
/// Ce que fait ce client automatiquement pour chaque requête, dans cet
/// ordre à l'erreur (le retry tente de rejouer avant que l'erreur ne soit
/// journalisée puis traduite en [ApiException]) :
/// 1. Ajoute la `baseUrl` et les timeouts de [config]
/// 2. Injecte le token d'accès dans le header `Authorization`, via
///    [getAccessToken], s'il existe ([AuthInterceptor])
/// 3. Ressert une réponse en cache si [cache] est fourni et que la requête
///    échoue faute de connexion ([CacheInterceptor], `GET` uniquement)
/// 4. Rejoue automatiquement les erreurs transitoires (timeout, connexion
///    perdue) sur les méthodes idempotentes ([RetryInterceptor])
/// 5. Journalise si [NetworkConfig.enableLogging] est activé
///    ([LoggingInterceptor])
/// 6. Traduit toute erreur restante en [ApiException] typée
///    ([ErrorInterceptor]) — voir `DioExceptionX.apiException`
///
/// Exemple d'utilisation (typiquement dans `core/di/injector.dart` d'une
/// app) :
/// ```dart
/// DioClient(
///   config: NetworkConfig(
///     baseUrl: AppEnv.current.apiBaseUrl,
///     enableLogging: kDebugMode && AppEnv.current.enableNetworkLogs,
///   ),
///   getAccessToken: () => secureStorage.read(key: StorageKeys.accessToken),
/// )
/// ```
class DioClient {
  DioClient({
    required NetworkConfig config,
    required Future<String?> Function() getAccessToken,
    NetworkCache? cache,
    Logger? logger,
  }) {
    _dio = Dio(
      BaseOptions(
        baseUrl: config.baseUrl,
        connectTimeout: config.connectTimeout,
        receiveTimeout: config.receiveTimeout,
        sendTimeout: config.sendTimeout,
        headers: config.defaultHeaders,
      ),
    );

    _dio.interceptors.addAll([
      // Ajoutés dans cet ordre : sur une erreur, Dio les exécute en ordre
      // inverse (le dernier ajouté est le plus proche du transport réseau
      // et voit l'erreur en premier) — voir la doc de classe ci-dessus.
      ErrorInterceptor(),
      LoggingInterceptor(logger: logger),
      AuthInterceptor(getAccessToken: getAccessToken),
      if (cache != null) CacheInterceptor(cache),
      if (config.maxRetries > 0)
        RetryInterceptor(
          dio: _dio,
          maxRetries: config.maxRetries,
          retryableMethods: config.retryableMethods,
        ),
    ]);
  }

  late final Dio _dio;

  /// L'instance [Dio] prête à l'emploi, à injecter dans les data sources.
  Dio get dio => _dio;
}
