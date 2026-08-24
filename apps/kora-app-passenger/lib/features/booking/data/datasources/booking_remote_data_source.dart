import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/search_criteria.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/ticket.dart';
import '../../domain/entities/upcoming_trip.dart';
import '../models/bus_json.dart';
import '../models/city_model.dart';
import '../models/departure_model.dart';
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
      for (final json in BusJson.list(response.data))
        CityModel.fromJson(json).toEntity(),
    ]..removeWhere((city) => city.name.isEmpty);

    // L'API ne garantit pas d'ordre ; le sélecteur, lui, est une liste
    // qu'on parcourt des yeux.
    cities.sort((a, b) => a.name.compareTo(b.name));
    return cities;
  }

  @override
  Future<List<Departure>> searchDepartures(SearchCriteria criteria) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.busDepartures,
      queryParameters: {
        'date': _apiDate(criteria.date),
        // L'API filtre sur des **noms de villes**, pas sur des
        // identifiants — c'est pour ça que `City.id` est le nom lui-même
        // (voir `CityModel.toEntity`).
        'from': criteria.origin.name,
        'to': criteria.destination.name,
      },
    );

    return [
      for (final json in BusJson.list(response.data))
        // Un départ que l'API ne date pas est écarté, pas inventé.
        ?DepartureModel.fromJson(json).toEntity(),
    ];
  }

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
      for (final json in BusJson.list(response.data))
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
