/// Chemins de l'API IdentityAccess (module `kora-ops-web`).
///
/// [AppConfig.apiBaseUrl] ne porte que le domaine (ex.
/// `https://api-dev.kora-express.com`) ; chaque chemin ici inclut son
/// propre préfixe `/api/v1` — voir `routes/api.php` côté backend.
abstract final class ApiConstants {
  static const String _v1 = '/api/v1';

  static const String otpRequest = '$_v1/auth/otp/request';
  static const String otpVerify = '$_v1/auth/otp/verify';
  static const String login = '$_v1/auth/login';
  static const String pinReset = '$_v1/auth/pin/reset';
  static const String pinChange = '$_v1/auth/pin/change';
  static const String unlock = '$_v1/auth/unlock';
  static const String me = '$_v1/auth/me';
  static const String logout = '$_v1/auth/logout';
  static const String devices = '$_v1/auth/devices';

  /// Distribution hors store (MEZ-150) : vérification de mise à jour au
  /// lancement, publique (avant toute session). Les réponses
  /// handshake/sync/activity portent le même bloc dans `data.update`.
  static const String appUpdateCheck = '$_v1/apps/ops-mobile/update';

  /// Préférences du compte (`push_notifications`...) — voir
  /// `UserPreferenceController`. Couper le push purge les tokens côté back.
  static const String preferences = '$_v1/auth/preferences';
  static String device(String uuid) => '$_v1/auth/devices/$uuid';
  static String devicePushToken(String uuid) =>
      '$_v1/auth/devices/$uuid/push-token';

  // OPS Mobile — terminal P501 (voir routes/api.php § ops-mobile/terminal,
  // MEZ-91). `sync` et `activity` exigent un terminal actif (middleware
  // `terminal.active`, en-tête X-Terminal-Serial — voir
  // TerminalHeaderInterceptor) ; `handshake` non, c'est lui qui l'établit.
  static const String terminalHandshake = '$_v1/ops-mobile/terminal/handshake';
  static const String terminalSync = '$_v1/ops-mobile/terminal/sync';
  static const String terminalActivity = '$_v1/ops-mobile/terminal/activity';

  // OPS Mobile — départs, manifeste, embarquement, synchro (MEZ-91).
  static const String opsDepartures = '$_v1/ops/departures';
  static String opsDepartureShow(String departureUuid) =>
      '$_v1/ops/departures/$departureUuid';

  /// Progression du départ par le convoyeur (embarquement → parti →
  /// arrivé, MEZ-98).
  static String opsDepartureStatus(String departureUuid) =>
      '$_v1/ops/departures/$departureUuid/status';
  static String opsDepartureDelay(String departureUuid) =>
      '$_v1/ops/departures/$departureUuid/delay';

  /// Signalement d'incident par le convoyeur (MEZ-98).
  static const String opsIncidents = '$_v1/ops/incidents';

  /// Ingestion par lots des positions du convoyeur (MEZ-100/MEZ-101).
  /// Idempotent via `position_uuid` — voir TrackingRemoteDataSource.
  static const String opsTrackingPositionsBatch =
      '$_v1/ops/tracking/positions/batch';
  static String opsDepartureManifest(String departureUuid) =>
      '$_v1/ops/departures/$departureUuid/manifest';

  /// Manifeste frontière en PDF A4 — mode téléphone uniquement (MEZ-94) :
  /// remplace l'impression thermique 58 mm du P501.
  static String opsDepartureManifestPdf(String departureUuid) =>
      '$_v1/ops/departures/$departureUuid/manifest/pdf';

  /// Correction par le convoyeur des informations d'un passager
  /// (`PATCH`, voir `OpsPassengerCorrectionController`).
  static String opsPassengerCorrection(
          String departureUuid, String ticketUuid) =>
      '$_v1/ops/departures/$departureUuid/tickets/$ticketUuid/passenger';
  static const String opsBoarding = '$_v1/ops/boarding';
  static String opsBoardingReverse(String boardingEventUuid) =>
      '$_v1/ops/boarding/$boardingEventUuid/reverse';

  /// PDF de confirmation d'embarquement — mode téléphone uniquement (pas
  /// d'imprimante thermique). Voir ticket "Supporter le scan par caméra
  /// téléphone en alternative au P501".
  static String opsBoardingTicketPdf(String departureUuid, String ticketUuid) =>
      '$_v1/ops/boarding/$departureUuid/tickets/$ticketUuid/pdf';
  static const String opsSyncManifestVersions =
      '$_v1/ops/sync/manifest-versions';
  static const String opsSyncBatches = '$_v1/ops/sync/batches';

  // OPS Mobile — descente anticipée et no-show (MEZ-87).
  static const String opsSeatReleases = '$_v1/ops/seat-releases';
  static const String opsSeatReleasesNoShowClosure =
      '$_v1/ops/seat-releases/no-show-closure';

  // Notifications opérationnelles (MEZ-96/MEZ-97) — même contrat OPS
  // Mobile / OPS Web (voir OpsNotificationController).
  /// Config publique du chat support tawk.to pour l'app convoyeur.
  static const String supportChatConfiguration =
      '$_v1/ops/support-chat-configuration';

  static const String notificationsIndex = '$_v1/notifications';
  static const String notificationsSentIndex = '$_v1/notifications/sent';
  static const String notificationsUnreadCount =
      '$_v1/notifications/unread-count';
  static const String notificationsMarkAllRead = '$_v1/notifications/read-all';
  static String notificationMarkRead(String uuid) =>
      '$_v1/notifications/$uuid/read';
}
