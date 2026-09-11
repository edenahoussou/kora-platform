import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/error/exceptions.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/departure_search.dart';
import '../../domain/entities/line_stop.dart';
import '../../domain/entities/search_criteria.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/ticket.dart';
import '../../domain/entities/upcoming_trip.dart';
import '../models/city_model.dart';
import '../models/itinerary_model.dart';
import '../models/line_stop_model.dart';
import '../models/seat_model.dart';
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
  BookingRemoteDataSourceImpl(this._dio, {required this.pending});

  final Dio _dio;

  /// Source de repli pour les écrans que l'API ne sert pas encore : plan
  /// des sièges, prochain voyage, billets. Injectée plutôt que construite
  /// ici pour que les tests puissent vérifier qu'elle est bien appelée —
  /// et pour qu'elle se retire proprement, méthode par méthode, quand
  /// l'API les exposera.
  final BookingDataSource pending;

  @override
  Future<List<City>> getCities() async {
    final response = await _dio.get<dynamic>(ApiConstants.busCities);

    final cities = [
      for (final json in ApiJson.list(response.data))
        CityModel.fromJson(json).toEntity(),
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
  @override
  Future<UpcomingTrip?> getNextTrip() => pending.getNextTrip();

  /// Pas encore servi par l'API — voir la note de classe.
  @override
  Future<List<Ticket>> getTickets() => pending.getTickets();

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
