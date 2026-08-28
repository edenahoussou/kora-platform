import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
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

  /// Départs correspondant à une recherche. Liste vide si la liaison n'est
  /// pas desservie ce jour-là : c'est un résultat, pas une erreur.
  Future<List<Departure>> searchDepartures(SearchCriteria criteria);

  /// Un départ rechargé par son identifiant, pour rafraîchir un détail
  /// affiché depuis la liste.
  Future<Departure> getDeparture(String departureId);

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
  Future<List<Departure>> searchDepartures(SearchCriteria criteria) async {
    // Une recherche est plus longue qu'une simple lecture : la maquette
    // lui consacre un écran entier (`searching`), qu'on n'aurait jamais le
    // temps de voir avec le délai standard.
    await Future<void>.delayed(latency * 2);
    return mockDeparturesFor(criteria);
  }

  @override
  Future<Departure> getDeparture(String departureId) async {
    await Future<void>.delayed(latency);

    // Le jeu de démonstration ne s'indexe pas par identifiant : il produit
    // des départs à la demande, pour une recherche. Rien à rafraîchir ici.
    throw UnimplementedError(
      'Le jeu de démonstration ne recharge pas un départ isolé.',
    );
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
