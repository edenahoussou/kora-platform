import 'environment.dart';

/// Configuration centralisée de l'application, résolue une seule fois au
/// démarrage à partir de `--dart-define=ENV=...`. Aucune URL d'API ne doit
/// être écrite en dur ailleurs dans le code : tout passe par [AppConfig].
///
/// Lancement :
///   flutter run --dart-define=ENV=dev
///   flutter run --dart-define=ENV=staging
///   flutter run --dart-define=ENV=prod
class AppConfig {
  AppConfig._({
    required this.environment,
    required this.appName,
    required this.apiBaseUrl,
    required this.enableDebugBanner,
    required this.enableLogging,
  });

  final Environment environment;
  final String appName;
  final String apiBaseUrl;
  final bool enableDebugBanner;
  final bool enableLogging;

  static AppConfig? _instance;

  static AppConfig get instance {
    final AppConfig? instance = _instance;
    if (instance == null) {
      throw StateError(
        'AppConfig non initialisé. Appeler AppConfig.init() dans main() avant runApp().',
      );
    }
    return instance;
  }

  static void init() {
    const String rawEnv = String.fromEnvironment('ENV', defaultValue: 'dev');
    final Environment env = EnvironmentX.fromString(rawEnv);

    switch (env) {
      case Environment.dev:
        _instance = AppConfig._(
          environment: env,
          appName: 'KORA OPS (dev)',
          apiBaseUrl: 'https://api-dev.kora-express.com',
          enableDebugBanner: true,
          enableLogging: true,
        );
      case Environment.staging:
        _instance = AppConfig._(
          environment: env,
          appName: 'KORA OPS (staging)',
          apiBaseUrl: 'https://api-staging.kora-express.com',
          enableDebugBanner: true,
          enableLogging: true,
        );
      case Environment.prod:
        _instance = AppConfig._(
          environment: env,
          appName: 'KORA OPS',
          apiBaseUrl: 'https://api.kora-express.com',
          enableDebugBanner: false,
          enableLogging: false,
        );
    }
  }
}
