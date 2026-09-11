import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/departure_search.dart';
import '../../domain/entities/line_stop.dart';
import '../../domain/entities/search_criteria.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/ticket.dart';
import '../../domain/entities/upcoming_trip.dart';
import 'booking_mock_data.dart';

/// Couche DATA : source de données du parcours Bus.
///
/// L'interface est volontairement identique à ce que fera l'implémentation
/// réseau du Sprint 2 (`BookingRemoteDataSourceImpl`, qui appellera l'API
/// via `Dio`). Le jour du branchement, une seule ligne changera dans
/// `core/di/injector.dart` — ni le repository, ni les use cases, ni les
/// Blocs, ni les écrans ne bougeront.
///
/// Elle lève des exceptions (couche data) ; c'est le repository qui les
/// traduit en [Failure] (couche domain).
abstract class BookingDataSource {
  Future<List<City>> getCities();

  /// Dernières villes recherchées, mises en avant dans le sélecteur.
  Future<List<City>> getRecentCities();

  /// Départs correspondant à une recherche, un sens ou deux selon que le
  /// voyageur a demandé un retour.
  ///
  /// Un résultat vide est un **résultat**, pas une erreur : la liaison
  /// n'est pas desservie ce jour-là.
  Future<DepartureSearch> searchDepartures(SearchCriteria criteria);

  /// Les arrêts d'une ligne, dans l'ordre du trajet.
  ///
  /// `GET bus/lines/{uuid}` les rend avec la ligne. Une liste vide est un
  /// résultat : une ligne directe n'a que ses deux terminus, que l'écran
  /// de détail affiche déjà par ailleurs.
  Future<List<LineStop>> getLineStops(String lineId);

  /// Plan des sièges d'un départ, dans l'ordre de lecture du bus.
  Future<List<Seat>> getSeats(Departure departure);

  Future<UpcomingTrip?> getNextTrip();

  /// Tous les billets du voyageur, statuts confondus. C'est l'écran qui
  /// les répartit entre ses onglets : le tri par statut est un choix
  /// d'affichage, pas une requête différente.
  Future<List<Ticket>> getTickets();
}

/// Implémentation Sprint 1 : renvoie le jeu de données de
/// `booking_mock_data.dart`, après un court délai artificiel.
///
/// **Pourquoi ce délai ?** Sans lui, les états `loading` des écrans ne
/// seraient jamais visibles et on découvrirait au Sprint 2 que les
/// squelettes de chargement n'ont jamais été testés en vrai. Une source
/// mockée instantanée donne une fausse impression de fluidité.
class BookingMockDataSource implements BookingDataSource {
  BookingMockDataSource({
    this.latency = const Duration(milliseconds: 450),
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now;

  /// Délai simulé avant chaque réponse.
  final Duration latency;

  /// Horloge injectable — les tests figent le temps pour vérifier des
  /// libellés du type « Dans 7 jours ».
  final DateTime Function() _now;

  @override
  Future<List<City>> getCities() async {
    await Future<void>.delayed(latency);
    return kMockCities;
  }

  @override
  Future<List<City>> getRecentCities() async {
    await Future<void>.delayed(latency);
    return mockRecentCities();
  }

  @override
  Future<DepartureSearch> searchDepartures(SearchCriteria criteria) async {
    // Une recherche est plus longue qu'une simple lecture : la maquette
    // lui consacre un écran entier (`searching`), qu'on n'aurait jamais le
    // temps de voir avec le délai standard.
    await Future<void>.delayed(latency * 2);

    final outbound = mockDeparturesFor(criteria);
    final returnDate = criteria.returnDate;
    if (returnDate == null) return DepartureSearch.oneWay(outbound);

    // Le retour, c'est la même liaison prise à l'envers.
    return DepartureSearch(
      outbound: outbound,
      inbound: mockDeparturesFor(
        SearchCriteria(
          origin: criteria.destination,
          destination: criteria.origin,
          date: returnDate,
          passengers: criteria.passengers,
        ),
      ),
    );
  }

  /// Le jeu de démonstration décrit ses lignes par tronçons : les arrêts
  /// s'en déduisent — une origine par tronçon, plus le terminus.
  @override
  Future<List<LineStop>> getLineStops(String lineId) async {
    await Future<void>.delayed(latency);

    final line = kMockLines.where((line) => line.id == lineId).firstOrNull;
    final segments = line?.segments ?? const [];
    if (segments.isEmpty) return const [];

    return [
      for (var i = 0; i < segments.length; i++)
        LineStop(order: i + 1, name: segments[i].origin.name),
      LineStop(
        order: segments.length + 1,
        name: segments.last.destination.name,
      ),
    ];
  }

  @override
  Future<List<Seat>> getSeats(Departure departure) async {
    await Future<void>.delayed(latency);
    return mockSeatsFor(departure);
  }

  @override
  Future<UpcomingTrip?> getNextTrip() async {
    await Future<void>.delayed(latency);
    return mockUpcomingTrip(_now());
  }

  @override
  Future<List<Ticket>> getTickets() async {
    await Future<void>.delayed(latency);
    return mockTickets(_now());
  }
}
