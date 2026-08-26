/// Constantes liées à l'API backend.
///
/// Les chemins d'authentification suivent `docs/mintlify/authentication.mdx`
/// et la spec OpenAPI du dépôt ; les autres restent des placeholders tant
/// que leur endpoint n'est pas documenté.
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

  /// Préfixe commun à toute l'API : les URLs de base s'arrêtent au
  /// domaine (voir [AppEnv.apiBaseUrl]).
  static const String _v1 = '/api/v1';

  // --- Authentification (documentée) ---

  /// Demande d'un code à 6 chiffres. Corps : `email` + `purpose`.
  static const String otpRequest = '$_v1/auth/otp/request';

  /// Vérification du code. Rend un `verification_token` à usage unique.
  static const String otpVerify = '$_v1/auth/otp/verify';

  /// Création du compte : PIN + empreinte d'appareil. Rend le token.
  static const String registerComplete = '$_v1/auth/register/complete';

  /// Connexion e-mail + PIN. Répond 202 `requires_otp` si l'appareil est
  /// inconnu, auquel cas il faut repasser par l'OTP puis rappeler ce
  /// même endpoint avec le `verification_token`.
  static const String login = '$_v1/auth/login';

  static const String logout = '$_v1/auth/logout';
  static const String me = '$_v1/auth/me';

  /// PIN oublié (après un OTP `pin_reset`).
  static const String pinReset = '$_v1/auth/pin/reset';

  /// Changement de PIN par un utilisateur connecté.
  static const String pinChange = '$_v1/auth/pin/change';

  /// Déverrouillage après trop d'erreurs de PIN (OTP `account_unlock`).
  static const String unlock = '$_v1/auth/unlock';

  /// Échange d'un jeton fournisseur (Google, Facebook, TikTok) contre un
  /// token KORA.
  static const String oauthToken = '$_v1/auth/oauth/token';

  static const String devices = '$_v1/auth/devices';
  static String device(String uuid) => '$devices/$uuid';

  static const String preferences = '$_v1/auth/preferences';

  // --- Catalogue Bus (documenté) ---
  //
  // Suit `docs/mintlify/guides/bus-search.mdx` et la spec OpenAPI du
  // dépôt. Ces trois endpoints sont **publics** : ils répondent sans
  // token, ce qui permet de chercher un départ avant de se connecter.

  /// Villes desservies, dérivées des arrêts actifs. Réponse mise en cache
  /// côté serveur. Aucun paramètre.
  static const String busCities = '$_v1/bus/cities';

  /// Lignes du réseau, avec leurs arrêts ordonnés. Filtres facultatifs :
  /// `from` et `to` (noms de villes).
  static const String busLines = '$_v1/bus/lines';

  /// Départs programmés. `date` (`aaaa-mm-jj`) est obligatoire ; `from` et
  /// `to` (noms de villes) sont facultatifs.
  static const String busDepartures = '$_v1/bus/departures';

  /// Plan des sièges d'un départ. Exige `origin_stop_uuid` et
  /// `destination_stop_uuid` en paramètres de requête.
  static String busDepartureSeats(String departureUuid) =>
      '$busDepartures/$departureUuid/seats';

  /// Devis ferme pour un tronçon (`POST`, authentifié). Étape obligée
  /// avant [busHolds], qui en réclame l'identifiant.
  static const String busQuotes = '$_v1/bus/quotes';

  /// Blocage des sièges (`POST`, authentifié). C'est cet appel — et lui
  /// seul — qui retire les places de la vente et arme le compte à
  /// rebours ; le choix sur le plan n'engage rien.
  static const String busHolds = '$_v1/bus/holds';

  /// Libération d'un blocage avant son échéance (`DELETE`, authentifié).
  /// La clé est l'identifiant de la **réservation**, pas du départ.
  static String hold(String reservationUuid) => '$_v1/holds/$reservationUuid';

  // --- Reste de l'API : pas encore documenté ---

  static const String busBooking = '/bus/bookings';

  static const String rideEstimate = '/ride/estimate';
  static const String rideBooking = '/ride/bookings';

  static const String parcelQuote = '/parcels/quote';
  static const String parcelTracking = '/parcels/tracking';

  static const String walletBalance = '/wallet/balance';
  static const String walletTransactions = '/wallet/transactions';
}
