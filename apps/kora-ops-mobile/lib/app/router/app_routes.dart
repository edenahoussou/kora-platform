/// Chemins de routes centralisés — jamais de chaîne littérale répétée dans
/// les écrans ou les `context.go(...)`.
abstract final class AppRoutes {
  static const String splash = '/';

  // Flux d'authentification (voir document.md § Flux d'authentification)
  static const String loginEmail = '/login/email';
  static const String loginOtp = '/login/otp';
  static const String loginOtpInvalid = '/login/otp-invalid';
  static const String loginPin = '/login/pin';
  static const String loginNewDevice = '/login/new-device';

  static const String home = '/home';
  static const String printerTest = '/printer-test';
}
