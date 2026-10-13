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

  // --- Notifications push ---

  /// Configuration Firebase **publique** de l'app, par plateforme.
  ///
  /// Authentifié. `?platform=android|ios`. Répond `{enabled: false}` seul
  /// quand l'intégration n'est pas configurée côté OPS : il n'y a alors
  /// rien à initialiser, et l'app se tait plutôt que d'échouer.
  ///
  /// La clé privée du compte de service ne transite jamais par ici —
  /// elle reste au serveur, qui est seul à envoyer les notifications.
  static String passengerFirebaseConfiguration(String platform) =>
      '$_v1/auth/passenger/firebase-configuration?platform=$platform';

  /// Jeton FCM de l'appareil courant.
  ///
  /// `PUT`, authentifié. Corps `{enabled: true, push_token: "…"}` pour
  /// l'inscrire, `{enabled: false}` pour l'effacer — c'est ce second
  /// appel qui sert à la déconnexion.
  static String devicePushToken(String deviceUuid) =>
      '$_v1/auth/devices/$deviceUuid/push-token';

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

  /// Itinéraires desservant un couple d'arrêts, à une date donnée.
  ///
  /// Remplace `bus/departures`, que MEZ-63 a retiré. Le contrat n'est plus
  /// le même : on interroge des **arrêts** — `from_physical_stop_uuid` et
  /// `to_physical_stop_uuid`, pas des noms de villes — et la réponse rend
  /// des itinéraires, chacun composé d'un ou plusieurs tronçons. `date`
  /// (`aaaa-mm-jj`) est obligatoire ; `passenger_count` est facultatif.
  static const String busItineraries = '$_v1/bus/itineraries';

  /// Plan des sièges d'un départ. Exige `origin_stop_uuid` et
  /// `destination_stop_uuid` en paramètres de requête.
  ///
  /// Seul rescapé de `bus/departures/…` : la liste et le détail d'un départ
  /// sont partis avec MEZ-63, le plan des sièges est resté.
  static String busDepartureSeats(String departureUuid) =>
      '$_v1/bus/departures/$departureUuid/seats';

  /// `POST` : le devis d'un itinéraire, un ou plusieurs tronçons, pour un
  /// nombre de voyageurs donné.
  ///
  /// C'est le seul point d'entrée qui rend la remise de groupe au
  /// passager : `group-discounts` et `group-booking-settings` sont fermés
  /// par la permission `group-discounts.view`, réservée à
  /// l'administration.
  static const String itineraryQuotes = '$_v1/bus/itinerary-quotes';

  /// `POST` : le blocage atomique de tous les sièges d'un groupe.
  static const String itineraryHolds = '$_v1/bus/itinerary-holds';

  /// `GET` : les comptes entreprise dont le voyageur est membre actif.
  ///
  /// Ouvert à tous : sans la permission `company-credit.view`, la réponse
  /// se limite aux comptes du demandeur — et masque leurs plafonds.
  static const String companyAccounts = '$_v1/company-accounts';

  /// `GET` : les billets émis d'une réservation, un par voyageur.
  static String bookingTickets(String reservationUuid) =>
      '$_v1/bookings/$reservationUuid/tickets';

  /// Tous les billets du voyageur connecté, paginés — `GET`.
  static const String tickets = '$_v1/tickets';

  /// Le PDF officiel d'un billet, tel que le backend le génère — `GET`,
  /// réponse binaire.
  ///
  /// Livrée par MEZ-82 : réservée au créateur ou au payeur de la
  /// réservation, pour un billet `issued` ou `used`.
  /// Seul l'UUID du billet y figure, jamais son identifiant numérique.
  static String ticketPdf(String ticketUuid) =>
      '$_v1/tickets/${Uri.encodeComponent(ticketUuid)}/pdf';

  /// Libération d'un blocage avant son échéance (`DELETE`, authentifié).
  /// La clé est l'identifiant de la **réservation**, pas du départ.
  ///
  /// A remplacé `DELETE bus/holds/{uuid}`, retiré par MEZ-63 : côté serveur
  /// c'est la même libération, rangée sous la réservation plutôt que sous
  /// le blocage. Une libération qui échoue ne se voit pas — l'app rend la
  /// main tout de suite — mais les sièges resteraient retenus jusqu'à
  /// l'échéance.
  static String booking(String reservationUuid) =>
      '$_v1/bookings/$reservationUuid';

  /// Identités des voyageurs d'une réservation (`POST`, authentifié).
  ///
  /// Rend la réservation entière, `status` et `expires_at` compris : c'est
  /// l'occasion de resynchroniser le décompte sur ce que dit le serveur.
  static String bookingPassengers(String reservationUuid) =>
      '$_v1/bookings/$reservationUuid/passengers';

  /// Annulation d'une réservation (`POST`, authentifié).
  ///
  /// Libère les sièges, passe les billets en remboursé et clôt la
  /// réservation. Idempotent : annuler une réservation déjà annulée ne
  /// fait rien de plus.
  static String bookingCancel(String reservationUuid) =>
      '$_v1/bookings/$reservationUuid/cancel';

  /// Code promo d'une réservation retenue (`PUT`, authentifié).
  ///
  /// `promo_code` non nul applique la remise, `null` la retire : le
  /// serveur recalcule le total en place, sans toucher aux sièges ni aux
  /// voyageurs.
  static String bookingPromoCode(String reservationUuid) =>
      '$_v1/bookings/$reservationUuid/promo-code';

  // --- Réservation assistée (MEZ-80) ---

  /// `POST` : confie la réservation à une agence ; `GET` : l'état du
  /// dossier.
  static String bookingAssistance(String reservationUuid) =>
      '$_v1/bookings/$reservationUuid/assistance';

  /// `POST` : revient au paiement en ligne tant qu'aucun agent n'a pris le
  /// dossier.
  static String bookingAssistanceResume(String reservationUuid) =>
      '$_v1/bookings/$reservationUuid/assistance/resume-online';

  // --- Paiements (documenté) ---

  /// Catalogue des moyens de paiement actifs (`GET`, authentifié).
  ///
  /// À appeler **avant** d'afficher l'écran de paiement : l'app ne doit
  /// proposer que les providers et les réseaux présents dans la réponse
  /// (voir `docs/mintlify/reference/payments/methods.mdx`).
  ///
  /// Malgré le `security: []` que lui donne `api/openapi.yaml`, la route
  /// est bien derrière `auth:sanctum` : sans token elle répond 401, et
  /// c'est tout le groupe `/payments` qui le fait — même une route
  /// inexistante y répond 401 au lieu de 404. La spec OpenAPI est en
  /// retard sur la page de référence, qui, elle, annonce correctement
  /// « Authentification `Bearer` requise ».
  static const String paymentMethods = '$_v1/payments/methods';

  /// `POST` : lance un paiement pour une réservation retenue.
  static const String paymentInitiate = '$_v1/payments/initiate';

  /// `GET` : l'état d'un paiement, interrogé à la cadence que le serveur
  /// annonce (`polling_interval_seconds`).
  static String payment(String paymentUuid) => '$_v1/payments/$paymentUuid';

  /// `POST` : demande au serveur de vérifier lui-même auprès du provider.
  ///
  /// Distinct de [payment], qui ne fait que relire l'état connu : c'est
  /// l'app qui décide quand forcer une vérification, jamais l'app qui
  /// conclut le paiement.
  static String paymentVerify(String paymentUuid) =>
      '$_v1/payments/$paymentUuid/verify';

  // --- Reste de l'API : pas encore documenté ---

  static const String busBooking = '/bus/bookings';

  static const String rideEstimate = '/ride/estimate';
  static const String rideBooking = '/ride/bookings';

  static const String parcelQuote = '/parcels/quote';
  static const String parcelTracking = '/parcels/tracking';

  static const String walletBalance = '/wallet/balance';
  static const String walletTransactions = '/wallet/transactions';
}
