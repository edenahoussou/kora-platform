/// Clés utilisées dans le stockage sécurisé (flutter_secure_storage) et
/// SharedPreferences. Centralisées ici pour éviter toute chaîne de
/// caractères éparpillée dans le code — voir document.md § Sécurité.
abstract final class StorageKeys {
  /// Session complète (token + utilisateur + expiration), sérialisée en
  /// JSON, dans le stockage sécurisé. Ne contient jamais le PIN.
  static const String session = 'auth_session';

  /// Dernier e-mail connecté sur cet appareil — sert à l'écran PIN qui ne
  /// redemande pas l'e-mail sur un appareil déjà reconnu.
  static const String lastEmail = 'auth_last_email';

  /// Empreinte stable de l'appareil, générée une fois puis relue : elle
  /// doit rester identique d'un lancement à l'autre pour que le serveur
  /// continue de reconnaître l'appareil.
  static const String deviceFingerprint = 'device_fingerprint';
}
