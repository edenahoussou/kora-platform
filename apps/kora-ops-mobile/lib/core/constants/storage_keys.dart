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

  /// L'onboarding (présentation Tournée/Scan/Synchro) a été vu — voir
  /// `OnboardingStore`. Non liée à la session : un agent qui se
  /// déconnecte ne doit pas le revoir.
  static const String onboardingSeen = 'onboarding_seen';

  /// Numéro de série du terminal P501 lié à cet appareil (voir
  /// `TerminalLocalDataSource`, MEZ-91). Distinct de `deviceFingerprint` :
  /// le fingerprint identifie le téléphone/PDA pour l'auth utilisateur, le
  /// numéro de série identifie le terminal P501 pour les endpoints
  /// `ops/*`. Persiste après déconnexion, comme le fingerprint — relier de
  /// nouveau le même terminal physique à chaque connexion serait une
  /// friction inutile pour le convoyeur.
  static const String terminalSerialNumber = 'terminal_serial_number';

  /// Dernier instantané du terminal (JSON de `Terminal.toCache`), relu au
  /// démarrage pour un affichage immédiat avant confirmation réseau — voir
  /// `TerminalCubit.bootstrap`.
  static const String terminalSnapshot = 'terminal_snapshot';

  /// Dernier départ sélectionné par l'agent (JSON de `Depart`), relu au
  /// démarrage pour restaurer le départ géré après un redémarrage à froid
  /// du P501 (MEZ-92) — voir `SelectedDepartCubit.bootstrap`. Purgé à la
  /// déconnexion (contrairement au terminal) : un autre agent qui se
  /// connecte sur le même appareil ne doit pas hériter du départ du
  /// précédent.
  static const String selectedDepartureSnapshot = 'selected_departure_snapshot';
}
