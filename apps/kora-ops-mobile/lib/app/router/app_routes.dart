/// Chemins de routes centralisés — jamais de chaîne littérale répétée dans
/// les écrans ou les `context.go(...)`.
abstract final class AppRoutes {
  static const String splash = '/';

  // Flux d'authentification — PIN pur + OTP de confirmation d'appareil
  // (pas d'auto-inscription côté OPS, voir document.md § Flux
  // d'authentification).
  static const String loginPin = '/login/pin';
  static const String loginOtp = '/login/otp';
  static const String loginOtpInvalid = '/login/otp-invalid';
  static const String loginNewDevice = '/login/new-device';

  // Bottom nav (5 onglets) — voir document.md § Navigation.
  static const String home = '/home';
  static const String manifeste = '/manifeste';
  static const String scanBoarding = '/scan';
  static const String descenteAnticipee = '/descente-anticipee';
  static const String synchro = '/synchro';

  // Écrans empilés au-dessus de la bottom nav.
  static const String detailPassager = '/manifeste/passager';
  static const String scanResult = '/scan/resultat';
  static const String selectionDepart = '/depart/selection';

  static const String printerTest = '/printer-test';
}
