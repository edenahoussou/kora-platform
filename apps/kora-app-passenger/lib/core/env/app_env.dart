/// Les 3 environnements possibles pour l'app.
enum Environment { dev, staging, prod }

/// Configuration propre à un environnement (URL de l'API, nom affiché de
/// l'app, activation des logs réseau...).
///
/// **Pourquoi pas juste `--dart-define=API_BASE_URL=...` à la main à
/// chaque build ?** Parce que c'est source d'erreurs (oubli, faute de
/// frappe dans l'URL) et pas versionné. Ici, chaque environnement est une
/// constante nommée dans le code : on ne peut pas se tromper d'URL, et on
/// voit la config complète d'un coup d'œil.
///
/// Comment ça s'active : un des 3 points d'entrée (`lib/main_dev.dart`,
/// `lib/main_staging.dart`, `lib/main_prod.dart`) choisit la bonne
/// constante et la passe à `bootstrap()` (voir `lib/bootstrap.dart`), qui
/// la range dans [AppEnv.current] avant `runApp`.
///
/// N'importe où dans l'app, pour lire la config active :
/// ```dart
/// final url = AppEnv.current.apiBaseUrl;
/// ```
///
/// Lancer l'app dans un environnement précis :
/// ```bash
/// flutter run -t lib/main_dev.dart       # par défaut avec `flutter run`
/// flutter run -t lib/main_staging.dart
/// flutter run -t lib/main_prod.dart
/// ```
class AppEnv {
  const AppEnv._({
    required this.environment,
    required this.appName,
    required this.apiBaseUrl,
    required this.enableNetworkLogs,
  });

  final Environment environment;

  /// Nom affiché de l'app (visible dans le titre `MaterialApp`, utile pour
  /// distinguer un build dev/staging d'un build prod au premier coup d'œil).
  final String appName;

  /// URL de base de l'API KORA EXPRESS pour cet environnement.
  final String apiBaseUrl;

  /// Si `true`, chaque requête/réponse Dio est journalisée (voir
  /// `packages/flutter-networking`). Toujours `false` en prod.
  final bool enableNetworkLogs;

  /// Identifiant client Google **côté serveur** (type « Web »), attendu
  /// par `google_sign_in` pour délivrer un jeton que le back peut
  /// valider.
  ///
  /// Contrairement à l'URL de l'API, il ne peut pas être une constante
  /// versionnée : chaque équipe a son projet Google Cloud, et le
  /// référentiel n'a pas à porter les identifiants d'une console tierce.
  /// Il arrive donc par `--dart-define`, et son absence désactive
  /// proprement le bouton plutôt que de faire échouer le SDK :
  ///
  /// ```bash
  /// flutter run --dart-define=GOOGLE_SERVER_CLIENT_ID=1234.apps.googleusercontent.com
  /// ```
  static const String googleServerClientId = String.fromEnvironment(
    'GOOGLE_SERVER_CLIENT_ID',
  );

  /// Identifiant de l'app Facebook. Il vit aussi dans les ressources
  /// natives (`android/app/src/main/res/values/strings.xml`, `Info.plist`)
  /// que lit le SDK ; celui-ci ne sert qu'à savoir si le bouton est
  /// utilisable.
  static const String facebookAppId = String.fromEnvironment('FACEBOOK_APP_ID');

  bool get isDev => environment == Environment.dev;
  bool get isStaging => environment == Environment.staging;
  bool get isProd => environment == Environment.prod;

  // URLs des déploiements réels (`docs/runbooks/deployment.md`), et non
  // celles de `quickstart.mdx` : `staging-api.korabus.app` et
  // `api.korabus.app` n'existent pas en DNS, une requête vers elles
  // échoue avant même de partir.
  //
  // Elles s'arrêtent au domaine : les chemins d'API portent eux-mêmes
  // leur préfixe `/api/v1` (voir ApiConstants).
  //
  // Le développement tape sur le staging : il n'existe pas d'API de dev
  // déployée, et un backend local ne tourne pas sur toutes les machines.
  static const dev = AppEnv._(
    environment: Environment.dev,
    appName: 'KORA (Dev)',
    apiBaseUrl: 'https://staging.koraexpress.app',
    enableNetworkLogs: true,
  );

  static const staging = AppEnv._(
    environment: Environment.staging,
    appName: 'KORA (Staging)',
    apiBaseUrl: 'https://staging.koraexpress.app',
    enableNetworkLogs: true,
  );

  static const prod = AppEnv._(
    environment: Environment.prod,
    appName: 'KORA',
    apiBaseUrl: 'https://prod.koraexpress.app',
    enableNetworkLogs: false,
  );

  /// Renseigné une seule fois, tout au début de `bootstrap()`, avant tout
  /// le reste (injection de dépendances, `runApp`...). Lire [current]
  /// avant que `bootstrap()` ait tourné est une erreur de programmation
  /// (le `late` lève une exception claire dans ce cas plutôt que de
  /// renvoyer une valeur par défaut silencieuse).
  static late final AppEnv current;
}
