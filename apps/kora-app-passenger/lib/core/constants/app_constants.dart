/// Constantes générales de l'application (hors design system, hors API).
class AppConstants {
  const AppConstants._();

  static const String appName = 'KORA';

  /// Nombre d'éléments chargés par page pour les listes paginées
  /// (historique de trajets, transactions wallet, etc.).
  static const int defaultPageSize = 20;

  /// Durée pendant laquelle une réponse peut être considérée "fraîche"
  /// sans re-appeler l'API (utile pour un futur système de cache).
  static const Duration cacheValidity = Duration(minutes: 5);

  /// Longueur attendue pour un code de vérification (OTP) envoyé par SMS.
  static const int otpLength = 6;
}
