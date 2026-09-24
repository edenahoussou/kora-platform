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
    required this.ticketSigningPublicKeyBase64,
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

  /// Clé PUBLIQUE Ed25519 (base64 standard, 32 octets) utilisée pour
  /// vérifier l'authenticité des billets hors ligne (voir
  /// `TicketQrVerifier`) — le pendant public de la clé privée qui signe
  /// les QR côté backend (`BuildTicketQrPayloadAction`,
  /// `config/tickets.php` → `KORA_TICKET_SIGNING_PUBLIC_KEY`). Non
  /// secrète : safe à embarquer dans le code (voir la commande `php
  /// artisan tickets:public-key`, dont c'est la sortie « Base64 » telle
  /// quelle) — seule la clé privée, restée côté serveur, permet de
  /// signer un billet.
  ///
  /// Résolue via `--dart-define=TICKET_SIGNING_PUBLIC_KEY=...` (comme
  /// `ENV`, voir [init]) — PAS un override direct dans le code comme
  /// [kTerminalModeOverride]/[kUseMockHardwareOverride] : cette clé est
  /// amenée à tourner (rotation périodique), un `--dart-define` passé en
  /// CI au moment du build permet de la changer sans toucher au code
  /// source ni recompiler manuellement. Sans override, retombe sur
  /// [_ticketSigningPublicKeyBase64] — la clé actuelle, valable pour les
  /// 3 environnements pour l'instant (comme `apiBaseUrl` pour
  /// staging/prod) ; passer un `--dart-define` différent par
  /// environnement le jour où chaque backend aura son propre keypair.
  final String ticketSigningPublicKeyBase64;

  /// Valeur par défaut si aucun `--dart-define=TICKET_SIGNING_PUBLIC_KEY`
  /// n'est passé au build — sortie de `php artisan tickets:public-key`
  /// (section "Base64") sur le backend KORA OPS au 23/09/2026. À mettre
  /// à jour ici SI la clé tourne et qu'aucun `--dart-define` n'est
  /// utilisé pour la nouvelle valeur (sinon, rien à changer dans le code
  /// — voir docstring [ticketSigningPublicKeyBase64]).
  static const String _ticketSigningPublicKeyBase64 =
      '9csxdLdBfzIoFPHrt76YDy/F43oY6rtU6gCZEcSFE8A=';

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

    // --dart-define=TICKET_SIGNING_PUBLIC_KEY=... — voir docstring
    // [ticketSigningPublicKeyBase64] sur pourquoi celle-ci passe par un
    // vrai dart-define plutôt qu'un override direct dans le code.
    const String ticketSigningPublicKeyBase64 = String.fromEnvironment(
      'TICKET_SIGNING_PUBLIC_KEY',
      defaultValue: _ticketSigningPublicKeyBase64,
    );

    switch (env) {
      case Environment.dev:
        _instance = AppConfig._(
          environment: env,
          appName: 'KORA OPS (dev)',
          apiBaseUrl: 'http://192.168.1.71/kora-ops-web/public/',
          enableDebugBanner: true,
          enableLogging: true,
          useMockHardware: resolveUseMockHardware(true),
          terminalMode: terminalMode,
          ticketSigningPublicKeyBase64: ticketSigningPublicKeyBase64,
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
          ticketSigningPublicKeyBase64: ticketSigningPublicKeyBase64,
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
          ticketSigningPublicKeyBase64: ticketSigningPublicKeyBase64,
        );
    }
  }
}
