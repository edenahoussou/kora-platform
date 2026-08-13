/// Configuration réseau propre à un environnement (dev / staging / prod).
///
/// Regroupe tout ce que [DioClient] a besoin de savoir pour se configurer
/// (URL de base, timeouts, headers par défaut, nombre de tentatives en cas
/// d'erreur transitoire) **sans jamais connaître lui-même ces valeurs** :
/// c'est l'app consommatrice qui construit un [NetworkConfig] à partir de
/// sa propre configuration d'environnement (voir `AppEnv` côté
/// kora-app-passenger) et le passe à [DioClient].
///
/// Ce découplage est le même principe que celui déjà en place pour le
/// token d'accès (`getAccessToken`) : le package reste agnostique de
/// l'app qui l'utilise, réutilisable tel quel par une future app KORA
/// Driver ou par kora-ops-mobile.
///
/// Exemple (typiquement dans `core/di/injector.dart` d'une app) :
/// ```dart
/// NetworkConfig(
///   baseUrl: AppEnv.current.apiBaseUrl,
///   connectTimeout: const Duration(seconds: 15),
///   receiveTimeout: const Duration(seconds: 15),
///   sendTimeout: const Duration(seconds: 15),
///   enableLogging: kDebugMode && AppEnv.current.enableNetworkLogs,
/// )
/// ```
class NetworkConfig {
  const NetworkConfig({
    required this.baseUrl,
    this.connectTimeout = const Duration(seconds: 15),
    this.receiveTimeout = const Duration(seconds: 15),
    this.sendTimeout = const Duration(seconds: 15),
    this.defaultHeaders = const {'Content-Type': 'application/json'},
    this.enableLogging = false,
    this.maxRetries = 2,
    this.retryableMethods = const {'GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'},
  });

  /// URL de base de l'API (ex: `https://api-dev.kora-express.com/v1`).
  final String baseUrl;

  /// Délai maximum pour établir la connexion.
  final Duration connectTimeout;

  /// Délai maximum pour recevoir une réponse.
  final Duration receiveTimeout;

  /// Délai maximum pour envoyer une requête (upload).
  final Duration sendTimeout;

  /// Headers envoyés sur chaque requête, sauf surcharge explicite.
  final Map<String, dynamic> defaultHeaders;

  /// Si `true`, chaque requête/réponse est journalisée via [LoggingInterceptor].
  /// Toujours `false` en prod.
  final bool enableLogging;

  /// Nombre de nouvelles tentatives après une erreur réseau transitoire
  /// (timeout, connexion perdue). `0` désactive le retry.
  final int maxRetries;

  /// Méthodes HTTP considérées idempotentes, donc sûres à rejouer
  /// automatiquement. `POST`/`PATCH` en sont volontairement exclues par
  /// défaut (un retry silencieux pourrait dupliquer une réservation ou un
  /// paiement) — voir [RetryInterceptor].
  final Set<String> retryableMethods;
}
