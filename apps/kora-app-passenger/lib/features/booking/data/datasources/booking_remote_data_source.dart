import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/error/exceptions.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/departure_search.dart';
import '../../domain/entities/group_ticket.dart';
import '../../domain/entities/line.dart';
import '../../domain/entities/line_stop.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/search_criteria.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/ticket.dart';
import '../../domain/entities/upcoming_trip.dart';
import '../models/city_model.dart';
import '../models/itinerary_model.dart';
import '../models/line_stop_model.dart';
import '../models/passenger_ticket_model.dart';
import '../models/seat_model.dart';
import 'booking_cache_local_data_source.dart';
import 'booking_data_source.dart';

/// Couche DATA : le parcours Bus branché sur l'API KORA.
///
/// Suit `docs/mintlify/guides/bus-search.mdx`. Les deux endpoints du
/// parcours de recherche sont ici :
/// * `GET /api/v1/bus/cities` — les villes des sélecteurs de l'accueil ;
/// * `GET /api/v1/bus/departures` — les résultats de la recherche.
///
/// **Les écrans en aval restent sur les données de démonstration.** Le
/// plan des sièges, le prochain voyage et les billets n'ont pas encore
/// d'endpoint documenté ; plutôt que de les casser, ce data source les
/// délègue à [pending] (le jeu mocké). Chaque méthode déléguée le dit
/// explicitement : le jour où l'API les expose, elles se remplacent une à
/// une sans toucher au reste de l'app.
///
/// Comme les autres data sources, il laisse remonter les exceptions
/// (`DioException`, porteuse d'une `ApiException` typée) : c'est le
/// repository qui les traduit en `Failure`.
class BookingRemoteDataSourceImpl implements BookingDataSource {
  BookingRemoteDataSourceImpl(this._dio, {required this.pending, this.cache});

  final Dio _dio;

  /// La copie locale des billets et des villes. Absente dans les tests
  /// qui n'en ont que faire ; branchée dans l'app, c'est elle qui garde
  /// l'onglet Voyages et l'accueil lisibles hors réseau (APP-PAY-006).
  final BookingCacheLocalDataSource? cache;

  /// Source de repli pour ce que l'API ne sert pas encore : le prochain
  /// voyage de l'accueil. Injectée plutôt que construite
  /// ici pour que les tests puissent vérifier qu'elle est bien appelée —
  /// et pour qu'elle se retire proprement, méthode par méthode, quand
  /// l'API les exposera.
  final BookingDataSource pending;

  @override
  Future<List<City>> getCities() async {
    final List<Map<String, dynamic>> raw;
    try {
      final response = await _dio.get<dynamic>(ApiConstants.busCities);
      raw = ApiJson.list(response.data);
      await cache?.rememberCities(raw);
    } on DioException {
      // Sans villes, l'accueil ne s'ouvre pas : la copie de la dernière
      // visite vaut mieux qu'un écran de panne. Le catalogue bouge peu.
      final kept = cache?.cities() ?? const <Map<String, dynamic>>[];
      if (kept.isEmpty) rethrow;
      return _citiesOf(kept);
    }

    return _citiesOf(raw);
  }

  /// Les villes cherchables, triées comme la liste qu'on parcourt des yeux.
  static List<City> _citiesOf(List<Map<String, dynamic>> raw) {
    final cities = [
      for (final json in raw) CityModel.fromJson(json).toEntity(),
    ]..removeWhere((city) => city.name.isEmpty || !city.isSearchable);

    // Une ville sans arrêt physique est écartée : depuis MEZ-63 la
    // recherche interroge des arrêts, et la proposer mènerait le voyageur
    // à une impasse plutôt qu'à un résultat.
    //
    // L'API ne garantit pas d'ordre ; le sélecteur, lui, est une liste
    // qu'on parcourt des yeux.
    cities.sort((a, b) => a.name.compareTo(b.name));
    return cities;
  }

  /// Cherche les trajets d'une ville à une autre, à une date donnée.
  ///
  /// Passe par `bus/itineraries` depuis MEZ-63, qui a retiré
  /// `bus/departures`. Deux conséquences pour l'appelant : la recherche
  /// travaille sur des **arrêts physiques** et non sur des noms de villes,
  /// et elle rend des itinéraires — dont les trajets à correspondance, que
  /// [ItineraryModel] écarte tant que l'app ne sait pas les afficher.
  ///
  /// Un aller-retour change la forme de la réponse : le serveur rend
  /// `{outbound, inbound}` là où un aller simple rend `{data}`. Deux
  /// enveloppes, un seul point d'entrée.
  @override
  Future<DepartureSearch> searchDepartures(SearchCriteria criteria) async {
    final from = criteria.origin.physicalStopId;
    final to = criteria.destination.physicalStopId;

    // Une ville sans arrêt physique ne peut pas être cherchée : le serveur
    // refuserait (422). Le cas vient d'une ville reconstruite ailleurs que
    // depuis `bus/cities` — un billet relu, un jeu de démonstration.
    if (from == null || to == null) {
      throw const ServerException('Ville sans arrêt desservi.');
    }

    final returnDate = criteria.returnDate;

    final response = await _dio.get<dynamic>(
      ApiConstants.busItineraries,
      queryParameters: {
        'from_physical_stop_uuid': from,
        'to_physical_stop_uuid': to,
        'date': _apiDate(criteria.date),
        'passenger_count': criteria.passengers,
        // `trip_type` porte l'intention, `return_date` la précise. Le
        // serveur accepte le premier sans le second : il rend alors les
        // retours des jours qui suivent, et c'est ce qui permet de choisir
        // son aller avant de savoir quand on rentre.
        if (criteria.isRoundTrip) ...{
          'trip_type': 'round_trip',
          if (returnDate != null) 'return_date': _apiDate(returnDate),
        },
      },
    );

    final body = response.data;

    // La forme de la réponse suit `trip_type`, pas la date : c'est
    // l'intention qu'on a envoyée, et le serveur enveloppe les deux sens
    // dès qu'il la reçoit. Se fier à la date ici rendait « aucun départ »
    // sur un aller-retour sans retour daté — les cinq allers étaient bien
    // là, sous une clé qu'on n'ouvrait pas.
    if (!criteria.isRoundTrip) {
      return DepartureSearch.oneWay(_departuresIn(body));
    }

    // Les deux sens ne sont pas sous `data` : le serveur les nomme.
    final map = ApiJson.map(body);
    return DepartureSearch(
      outbound: _departuresIn(map['outbound']),
      inbound: _departuresIn(map['inbound']),
    );
  }

  /// Les départs lisibles d'une liste d'itinéraires.
  ///
  /// Écarte plutôt qu'invente : un itinéraire sans date exploitable, ou
  /// qui impose une correspondance, ne donne pas de départ.
  List<Departure> _departuresIn(Object? json) => [
    for (final itinerary in ApiJson.list(json))
      ?ItineraryModel.fromJson(itinerary).toEntity(),
  ];

  /// Aucun historique de recherche n'est stocké pour l'instant : la liste
  /// vide fait simplement disparaître la section « Recherches récentes »
  /// du sélecteur.
  ///
  /// Elle ne délègue **pas** à [pending] : les villes mockées ne font
  /// pas partie de celles que l'API dessert, et proposer un raccourci vers
  /// une ville inconnue du serveur mènerait à une recherche vide.
  @override
  Future<List<City>> getRecentCities() async => const [];

  /// Pas encore servi par l'API — voir la note de classe.
  /// `GET bus/lines/{uuid}` : la ligne et ses arrêts actifs, triés.
  ///
  /// Route publique, comme le reste du catalogue Bus. Le serveur ne donne
  /// aucune heure de passage — seulement `km_from_origin` — et l'écran
  /// s'en tient donc là plutôt que d'interpoler des horaires.
  @override
  Future<List<LineStop>> getLineStops(String lineId) async {
    if (lineId.isEmpty) return const [];

    final response = await _dio.get<dynamic>(
      '${ApiConstants.busLines}/$lineId',
    );

    // `stops` vit dans l'objet `data`, pas à la racine : `ApiJson.list`
    // déballerait ici la mauvaise enveloppe.
    final line = ApiJson.map(
      response.data is Map ? (response.data as Map)['data'] : response.data,
    );

    final stops = [
      for (final json in ApiJson.list(line['stops']))
        LineStopModel(json).toEntity(),
    ]..sort((a, b) => a.order.compareTo(b.order));

    return stops;
  }

  @override
  Future<List<Seat>> getSeats(Departure departure) async {
    final origin = departure.originStopId;
    final destination = departure.destinationStopId;

    // Les deux arrêts sont obligatoires côté serveur : sans eux la
    // disponibilité n'a pas de sens, une place étant libre ou non selon
    // le tronçon. Un départ mocké n'en a pas — on retombe alors sur le
    // plan de démonstration plutôt que d'envoyer une requête invalide.
    if (origin == null || destination == null) {
      return pending.getSeats(departure);
    }

    final response = await _dio.get<dynamic>(
      ApiConstants.busDepartureSeats(departure.id),
      queryParameters: {
        'origin_stop_uuid': origin,
        'destination_stop_uuid': destination,
      },
    );

    return [
      for (final json in ApiJson.list(response.data))
        SeatModel.fromJson(json).toEntity(),
    ];
  }

  /// Pas encore servi par l'API — voir la note de classe.
  /// Le prochain voyage de l'accueil : le départ confirmé le plus proche,
  /// lu dans les billets du voyageur (`GET /api/v1/tickets`).
  ///
  /// `null` quand il n'y en a aucun — l'accueil propose alors de chercher un
  /// trajet plutôt que d'afficher un voyage de démonstration.
  @override
  Future<UpcomingTrip?> getNextTrip() async {
    final now = DateTime.now();
    final upcoming = [
      for (final ticket in await getTickets())
        if (ticket.status == TicketStatus.confirmed &&
            ticket.departure.departsAt.isAfter(now))
          ticket,
    ]..sort((a, b) => a.departure.departsAt.compareTo(b.departure.departsAt));

    if (upcoming.isEmpty) return null;

    final next = upcoming.first;
    return UpcomingTrip(departure: next.departure, seat: next.seat);
  }

  /// Les billets du voyageur connecté — `GET /api/v1/tickets`.
  ///
  /// Le serveur rend **un billet par voyageur**, du plus récemment émis au
  /// plus ancien, par pages de quinze. L'onglet Voyages, lui, montre des
  /// voyages : les billets d'une même réservation deviennent une seule
  /// carte, leurs sièges mis bout à bout. Sans ça, un groupe de cinq
  /// remplissait l'écran de cinq cartes identiques.
  ///
  /// Toutes les pages sont lues, dans la limite de [_maxTicketPages] : les
  /// onglets se trient côté écran, et un voyage à venir peut très bien
  /// dormir en page deux derrière des billets récemment émis.
  ///
  /// Contrairement au journal local qu'il remplace, il voit aussi les
  /// billets achetés au guichet ou sur un autre téléphone : le serveur
  /// retient ceux dont le voyageur est l'auteur **ou** le payeur.
  @override
  Future<List<Ticket>> getTickets() async {
    final raw = <Map<String, dynamic>>[];

    try {
      for (var page = 1; page <= _maxTicketPages; page++) {
        final response = await _dio.get<dynamic>(
          ApiConstants.tickets,
          queryParameters: {'page': page},
        );

        raw.addAll(ApiJson.list(response.data));

        if (page >= _lastPageOf(response.data)) break;
      }
    } on DioException {
      // Hors réseau, la copie du téléphone vaut mieux qu'un écran d'erreur
      // devant le convoyeur : c'est par cette liste qu'on rejoint le QR.
      // Sans copie, l'échec reste un échec.
      final kept = cache?.tickets() ?? const <Map<String, dynamic>>[];
      if (kept.isEmpty) rethrow;
      return _tripsOf([for (final json in kept) PassengerTicketModel(json)]);
    }

    await cache?.rememberTickets(raw);
    await _rememberQrCodes(raw);

    return _tripsOf([for (final json in raw) PassengerTicketModel(json)]);
  }

  /// Garde les QR de chaque réservation, tirés de la même réponse.
  ///
  /// `GET /tickets` porte déjà la charge signée, le nom du voyageur et son
  /// siège : inutile d'appeler `bookings/{uuid}/tickets` pour les avoir.
  /// Le voyageur n'a donc **rien à ouvrir ni à télécharger** avant de
  /// perdre le réseau — ouvrir l'onglet Voyages suffit.
  Future<void> _rememberQrCodes(List<Map<String, dynamic>> raw) async {
    final store = cache;
    if (store == null) return;

    final byReservation = <String, List<GroupTicket>>{};
    for (final json in raw) {
      final entry = PassengerTicketModel(json);
      final reservationId = entry.reservationId;
      if (reservationId == null || entry.qrCode.isEmpty) continue;

      byReservation.putIfAbsent(reservationId, () => []).add(
        GroupTicket(
          id: entry.uuid,
          shortCode: entry.shortCode,
          // `/tickets` parle le vocabulaire des voyages (`confirmed`,
          // `completed`, `cancelled`) ; l'écran des QR, celui du contrôle.
          status: switch (entry.status) {
            'cancelled' => GroupTicketStatus.cancelled,
            _ => GroupTicketStatus.issued,
          },
          qrCode: entry.qrCode,
          passengerName: entry.passengerName,
          seatCode: entry.seatCode,
        ),
      );
    }

    for (final entry in byReservation.entries) {
      await store.rememberGroupTickets(entry.key, entry.value);
    }
  }

  /// Plafond de pages lues d'un coup. Quinze billets par page, cela fait
  /// trois cents billets — bien au-delà de ce qu'un voyageur accumule, et
  /// assez bas pour qu'une pagination détraquée ne tourne pas sans fin.
  static const int _maxTicketPages = 20;

  /// La dernière page annoncée par `meta` : `last_page`, que Laravel
  /// fournit, ou à défaut le total rapporté à la taille des pages. Une
  /// réponse sans `meta` n'a qu'une page.
  static int _lastPageOf(Object? body) {
    final meta = ApiJson.map(body is Map ? body['meta'] : null);

    final last = ApiJson.intOrNull(meta['last_page']);
    if (last != null) return last;

    final total = ApiJson.intOrNull(meta['total']) ?? 0;
    final perPage = ApiJson.intOrNull(meta['per_page']) ?? 0;
    if (total <= 0 || perPage <= 0) return 1;

    return (total / perPage).ceil();
  }

  /// Regroupe les billets par réservation, dans l'ordre du serveur.
  static List<Ticket> _tripsOf(List<PassengerTicketModel> entries) {
    final groups = <String, List<PassengerTicketModel>>{};
    for (final entry in entries) {
      // Un billet sans réservation connue forme son propre voyage.
      groups
          .putIfAbsent(entry.reservationId ?? entry.uuid, () => [])
          .add(entry);
    }

    return [for (final group in groups.values) ?_tripOf(group)];
  }

  /// Une réservation devenue carte de l'onglet Voyages.
  ///
  /// `null` quand le serveur ne dit pas quand part le bus : sans date, la
  /// carte ne saurait ni se ranger dans un onglet ni afficher son compte à
  /// rebours, et en inventer une serait pire que l'omettre.
  static Ticket? _tripOf(List<PassengerTicketModel> group) {
    final first = group.first;
    final departsAt = first.departsAt;
    if (departsAt == null) return null;

    final seats = [
      for (final entry in group)
        if (entry.seatCode.isNotEmpty) entry.seatCode,
    ];

    return Ticket(
      // Le code imprimé sur le billet, pas l'identifiant de réservation :
      // c'est lui qu'on présente au contrôle.
      reference: first.shortCode.isNotEmpty ? first.shortCode : first.uuid,
      reservationId: first.reservationId,
      ticketIds: [for (final entry in group) entry.uuid],
      // La charge signée du premier voyageur. Un groupe passe de toute
      // façon par la liste de ses billets, où chacun a la sienne.
      qrCode: first.qrCode,
      departure: Departure(
        id: ApiJson.string(first.departure['uuid']),
        line: Line(
          id: '',
          code: ApiJson.stringOrNull(first.departure['line_code']),
          origin: _cityOf(first.origin),
          destination: _cityOf(first.destination),
          duration: Duration.zero,
          distanceKm: 0,
        ),
        departsAt: departsAt,
        comfort: BusComfort.confort,
        price: Price(first.amount),
        seatsTotal: 0,
        seatsAvailable: 0,
      ),
      seat: Seat(
        id: '',
        label: seats.join(', '),
        row: 0,
        column: 0,
        status: SeatStatus.occupied,
      ),
      status: _statusOf(group, departsAt),
    );
  }

  /// Une ville du billet : le code à trois lettres du serveur (`CTN`) est
  /// repris tel quel, l'arrêt devient la gare.
  static City _cityOf(Map<String, dynamic> json) {
    final name = ApiJson.string(json['city']);
    final code = ApiJson.string(json['city_code']);

    return City(
      id: name,
      name: name,
      code: code.isNotEmpty ? code : City.named(name).code,
      station: ApiJson.stringOrNull(json['stop_name']),
    );
  }

  /// L'onglet d'une réservation.
  ///
  /// Le serveur a déjà traduit ses statuts (`confirmed`, `completed`,
  /// `cancelled`). On garde le plus vivant des billets du groupe : un seul
  /// voyageur annulé ne fait pas un voyage annulé.
  ///
  /// Une correction s'y ajoute, et une seule : un billet `confirmed` dont le
  /// départ est passé bascule dans les voyages effectués. Le serveur laisse
  /// `issued` un billet que personne n'a scanné — un voyageur qui n'est pas
  /// monté, un contrôle manqué — et ce voyage-là n'est plus « à venir ». Un
  /// statut inconnu de cette version compte comme confirmé : le rendre
  /// annulé ferait passer un billet valable pour un billet mort.
  static TicketStatus _statusOf(
    List<PassengerTicketModel> group,
    DateTime departsAt,
  ) {
    final statuses = {for (final entry in group) entry.status};

    final TicketStatus status;
    if (statuses.every((value) => value == 'cancelled')) {
      status = TicketStatus.cancelled;
    } else if (statuses.every(
      (value) => value == 'completed' || value == 'cancelled',
    )) {
      status = TicketStatus.completed;
    } else {
      status = TicketStatus.confirmed;
    }

    if (status == TicketStatus.confirmed &&
        departsAt.isBefore(DateTime.now())) {
      return TicketStatus.completed;
    }

    return status;
  }

  /// `2026-08-20` — le format exigé par le paramètre `date`.
  ///
  /// Écrit à la main plutôt qu'avec `DateFormat` : ce format-ci ne doit
  /// **jamais** suivre la langue de l'utilisateur, contrairement à tous
  /// ceux de `KoraDateFormats`.
  static String _apiDate(DateTime date) {
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '${date.year.toString().padLeft(4, '0')}-$month-$day';
  }
}
