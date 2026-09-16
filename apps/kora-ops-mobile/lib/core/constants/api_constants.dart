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
  static String device(String uuid) => '$_v1/auth/devices/$uuid';

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
  static String opsDepartureManifest(String departureUuid) =>
      '$_v1/ops/departures/$departureUuid/manifest';
  static const String opsBoarding = '$_v1/ops/boarding';
  static String opsBoardingReverse(String boardingEventUuid) =>
      '$_v1/ops/boarding/$boardingEventUuid/reverse';
  static const String opsSyncManifestVersions =
      '$_v1/ops/sync/manifest-versions';
  static const String opsSyncBatches = '$_v1/ops/sync/batches';
}
