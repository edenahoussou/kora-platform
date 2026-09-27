/// Chemins de routes centralisés — jamais de chaîne littérale répétée dans
/// les écrans ou les `context.go(...)`.
abstract final class AppRoutes {
  static const String splash = '/';

  /// Présentation Tournée/Scan/Synchro, affichée une seule fois au tout
  /// premier lancement (voir `OnboardingStore`) — avant l'écran de
  /// connexion, jamais après (voir `AuthGuard`, qui ne redirige jamais
  /// depuis cette route, comme pour `splash`).
  static const String onboarding = '/onboarding';

  // Flux d'authentification (voir document.md § Flux d'authentification) :
  // première connexion sur un appareil = email + mot de passe, puis PIN,
  // puis OTP d'activation de l'appareil ; appareil déjà reconnu (email déjà
  // utilisé une fois) = PIN seul, sans repasser par l'email ni l'OTP.
  static const String loginEmail = '/login/email';
  static const String loginForgotPassword = '/login/forgot-password';
  static const String loginPin = '/login/pin';
  static const String loginOtp = '/login/otp';
  static const String loginOtpInvalid = '/login/otp-invalid';
  static const String loginNewPin = '/login/new-pin';

  /// Liaison du terminal P501 (voir `AuthGuard`, MEZ-91) — affichée entre
  /// la connexion et `/home` tant qu'aucun terminal actif n'est lié à cet
  /// appareil. Jamais la cible d'une navigation explicite, uniquement de
  /// `AuthGuard`/`resolveAuthDestination`.
  static const String terminalSetup = '/terminal/setup';

  // Bottom nav (5 onglets) — voir document.md § Navigation.
  static const String home = '/home';
  static const String tournee = '/tournee';
  static const String scanBoarding = '/scan';
  static const String descenteAnticipee = '/descente-anticipee';
  static const String profil = '/profil';

  // Écrans empilés au-dessus de la bottom nav. Le Manifeste n'est plus un
  // onglet : on y accède en sélectionnant une course depuis l'onglet
  // Tournée (voir document.md § Navigation).
  static const String manifeste = '/tournee/manifeste';
  static const String detailPassager = '/tournee/manifeste/passager';

  /// Manifeste frontière (MEZ-94) : impression thermique 58 mm sur P501,
  /// ou PDF A4 en mode téléphone.
  static const String borderManifestPrint = '/tournee/manifeste/frontiere';
  static const String borderManifestPdf = '/tournee/manifeste/frontiere/pdf';
  static const String boardingPdfPreview = '/scan/resultat/pdf';
  static const String synchro = '/synchro';

  // Sous-écrans du Profil (voir document.md § Profil).
  static const String mesInformations = '/profil/mes-informations';
  static const String securite = '/profil/securite';
  static const String changePin = '/profil/securite/pin';
  static const String preferences = '/profil/preferences';

  static const String printerTest = '/printer-test';

  /// Alertes opérationnelles du convoyeur (MEZ-97), accessible depuis la
  /// cloche de `NotificationButton` sur tous les écrans.
  static const String notifications = '/notifications';
  static const String notificationsSent = '/notifications/sent';
  static const String notificationDetail = '/notifications/detail';

  /// Formulaire de signalement d'incident (MEZ-98) — accessible depuis
  /// Accueil et Manifeste.
  static const String incidentReport = '/tournee/manifeste/incident';
}
