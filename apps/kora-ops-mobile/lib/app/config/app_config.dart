import 'environment.dart';
import 'terminal_mode.dart';

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
    required this.useMockHardware,
    required this.terminalMode,
  });

  final Environment environment;
  final String appName;
  final String apiBaseUrl;
  final bool enableDebugBanner;
  final bool enableLogging;

  /// Détermine si les adaptateurs P501 (scanner, imprimante) utilisent les
  /// implémentations Mock (`true`, par défaut hors prod) ou le SDK matériel
  /// réel (`false`). Voir document.md § Adaptateurs P501.
  ///
  /// Override explicite : `--dart-define=USE_MOCK_HARDWARE=true|false`.
  /// Sans override, `dev`/`staging` utilisent le Mock et `prod` le matériel
  /// réel.
  final bool useMockHardware;

  /// Matériel de scan/impression pour ce build — P501 ou caméra téléphone
  /// (voir [TerminalMode]). Indépendant de [useMockHardware] : le mock
  /// reste prioritaire et disponible dans les deux modes (utile en
  /// dev/CI sans terminal ni caméra), voir document.md § Adaptateurs P501.
  ///
  /// Override explicite : `--dart-define=TERMINAL_MODE=p501|phone`. Sans
  /// override, tous les environnements utilisent P501 — le mode téléphone
  /// est un choix de build explicite, jamais un défaut silencieux.
  final TerminalMode terminalMode;

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

    bool resolveUseMockHardware(bool defaultValue) {
      return kUseMockHardwareOverride;
    }

    final TerminalMode terminalMode = kTerminalModeOverride;

    switch (env) {
      case Environment.dev:
        _instance = AppConfig._(
          environment: env,
          appName: 'KORA OPS (dev)',
          apiBaseUrl: 'http://192.168.1.26/kora-ops-web/public/',
          enableDebugBanner: true,
          enableLogging: true,
          useMockHardware: resolveUseMockHardware(true),
          terminalMode: terminalMode,
        );
      case Environment.staging:
        _instance = AppConfig._(
          environment: env,
          appName: 'KORA OPS (staging)',
          apiBaseUrl: 'https://staging.koraexpress.app',
          enableDebugBanner: true,
          enableLogging: true,
          useMockHardware: resolveUseMockHardware(true),
          terminalMode: terminalMode,
        );
      case Environment.prod:
        _instance = AppConfig._(
          environment: env,
          appName: 'KORA OPS',
          apiBaseUrl: 'https://staging.koraexpress.app',
          enableDebugBanner: false,
          enableLogging: false,
          useMockHardware: resolveUseMockHardware(false),
          terminalMode: terminalMode,
        );
    }
  }
}
