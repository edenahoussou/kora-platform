/// Clés utilisées dans le stockage sécurisé (flutter_secure_storage) et
/// SharedPreferences. Centralisées ici pour éviter toute chaîne de
/// caractères éparpillée dans le code.
abstract final class StorageKeys {
  /// Session complète (token + utilisateur + expiration), sérialisée en
  /// JSON, dans le stockage sécurisé. Ne contient jamais le PIN.
  static const String session = 'auth_session';

  /// Dernier identifiant (email ou téléphone) connecté sur cet appareil —
  /// sert à l'écran PIN qui ne le redemande pas sur un appareil déjà
  /// reconnu.
  static const String lastIdentifier = 'auth_last_identifier';

  /// Ancienne clé (avant l'ajout du login par téléphone) — lue en repli
  /// par [AuthLocalDataSource.readLastIdentifier] le temps que les
  /// appareils déjà installés se reconnectent au moins une fois.
  static const String legacyLastEmail = 'auth_last_email';

  /// Empreinte stable de l'appareil, générée une fois puis relue : elle
  /// doit rester identique d'un lancement à l'autre pour que le serveur
  /// continue de reconnaître l'appareil.
  static const String deviceFingerprint = 'device_fingerprint';

  /// L'onboarding (présentation Tournée/Scan/Synchro) a été vu.
  static const String onboardingSeen = 'onboarding_seen';

  /// Numéro de série du terminal P501 lié à cet appareil.
  static const String terminalSerialNumber = 'terminal_serial_number';

  /// Dernier instantané du terminal (JSON de `Terminal.toCache`).
  static const String terminalSnapshot = 'terminal_snapshot';

  /// Dernier départ sélectionné par l'agent (JSON de `Depart`).
  static const String selectedDepartureSnapshot = 'selected_departure_snapshot';

  /// Dernier couple `deviceUuid|tokenFCM` confirmé par
  /// `PUT auth/devices/{uuid}/push-token` — voir `PushTokenStore`.
  static const String pushTokenRegistration = 'push_token_registration';

  /// Mise à jour hors store (MEZ-150) : build dont la proposition non
  /// obligatoire a été reportée (« Plus tard »), et échéance du report
  /// (epoch ms) — SharedPreferences.
  static const String appUpdateSnoozedBuild = 'app_update_snoozed_build';
  static const String appUpdateSnoozedUntil = 'app_update_snoozed_until';

  /// Dernier bloc `update` reçu (JSON) : garde une mise à jour obligatoire
  /// bloquante même après un redémarrage hors ligne (MEZ-150).
  static const String appUpdateLastBlock = 'app_update_last_block';
}
