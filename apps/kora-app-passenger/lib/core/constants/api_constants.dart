/// Constantes liées à l'API backend.
///
/// **Aucune vraie API n'existe encore pour ce projet** : les valeurs
/// ci-dessous sont des placeholders clairement identifiables.
///
/// L'URL de base n'est PAS ici : elle dépend de l'environnement (dev /
/// staging / prod) et vit dans [AppEnv.apiBaseUrl] (voir
/// `core/env/app_env.dart`). Ce fichier ne contient que ce qui ne change
/// jamais selon l'environnement : timeouts et chemins d'endpoints.
class ApiConstants {
  const ApiConstants._();

  /// Délai maximum (en millisecondes) pour établir la connexion.
  static const int connectTimeoutMs = 15000;

  /// Délai maximum (en millisecondes) pour recevoir une réponse.
  static const int receiveTimeoutMs = 15000;

  /// Délai maximum (en millisecondes) pour envoyer une requête (upload).
  static const int sendTimeoutMs = 15000;

  // --- Endpoints ---
  // Regroupés ici pour que chaque appel réseau utilise TOUJOURS une
  // constante plutôt qu'une chaîne de caractères écrite en dur.
  //
  // Exemple d'utilisation dans un data source :
  // dio.post(ApiConstants.login, data: {...});

  static const String login = '/auth/login';
  static const String register = '/auth/register';
  static const String refreshToken = '/auth/refresh';
  static const String logout = '/auth/logout';

  static const String profile = '/users/me';

  static const String busSearch = '/bus/search';
  static const String busBooking = '/bus/bookings';

  static const String rideEstimate = '/ride/estimate';
  static const String rideBooking = '/ride/bookings';

  static const String parcelQuote = '/parcels/quote';
  static const String parcelTracking = '/parcels/tracking';

  static const String walletBalance = '/wallet/balance';
  static const String walletTransactions = '/wallet/transactions';
}
