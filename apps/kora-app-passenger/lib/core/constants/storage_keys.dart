/// Clés utilisées pour le stockage local (SharedPreferences et
/// FlutterSecureStorage).
///
/// Centraliser les clés ici évite deux problèmes fréquents : une faute de
/// frappe qui casse silencieusement une lecture/écriture, et deux features
/// différentes qui utilisent la même clé par erreur.
class StorageKeys {
  const StorageKeys._();

  // --- SharedPreferences (données NON sensibles) ---
  static const String themeMode = 'kora_theme_mode';
  static const String onboardingSeen = 'kora_onboarding_seen';
  static const String preferredLanguage = 'kora_preferred_language';

  // --- FlutterSecureStorage (données SENSIBLES) ---
  // Ne jamais mettre un token, un mot de passe ou une donnée bancaire
  // dans SharedPreferences : toujours passer par FlutterSecureStorage
  // (voir core/di/injector.dart, DioClient est câblé sur cette clé via
  // `getAccessToken`, et packages/flutter-networking/lib/src/dio_client.dart
  // pour l'intercepteur qui le lit).
  static const String accessToken = 'kora_secure_access_token';
  static const String refreshToken = 'kora_secure_refresh_token';
  static const String userId = 'kora_secure_user_id';
}
