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

  /// Date d'expiration du token (ISO 8601). Il n'existe pas d'endpoint de
  /// rafraîchissement : l'app doit savoir toute seule quand la session est
  /// périmée, sans attendre un 401.
  static const String tokenExpiresAt = 'kora_secure_token_expires_at';

  /// Profil du dernier utilisateur connecté, en JSON. Permet d'ouvrir
  /// l'app sur son nom sans attendre le réseau.
  static const String authUser = 'kora_secure_auth_user';

  /// Empreinte de l'appareil envoyée à l'API. Générée une fois puis
  /// relue : c'est sa stabilité qui évite un OTP à chaque connexion.
  static const String deviceFingerprint = 'kora_secure_device_fingerprint';
}
