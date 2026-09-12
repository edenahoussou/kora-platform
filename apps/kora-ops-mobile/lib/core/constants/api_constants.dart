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
}
