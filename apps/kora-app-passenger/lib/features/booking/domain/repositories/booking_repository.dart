import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/city.dart';
import '../entities/departure.dart';
import '../entities/search_criteria.dart';
import '../entities/seat.dart';
import '../entities/ticket.dart';
import '../entities/upcoming_trip.dart';

/// Couche DOMAIN : contrat du parcours de réservation Bus.
///
/// Le domaine ne sait pas d'où viennent les données. Aujourd'hui elles
/// sortent d'un jeu de données mocké (`BookingMockDataSource`) ; au Sprint 2
/// elles viendront de l'API KORA. Aucune ligne de ce fichier, ni des Blocs
/// qui l'utilisent, n'aura à changer ce jour-là.
///
/// Les méthodes des écrans suivants du ticket (liste des départs, détail,
/// plan des sièges) seront ajoutées ici au fur et à mesure — on n'écrit pas
/// de contrat que personne n'appelle encore.
abstract class BookingRepository {
  /// Villes desservies, pour les sélecteurs départ / arrivée de l'accueil.
  Future<Either<Failure, List<City>>> getCities();

  /// Dernières villes recherchées, proposées en raccourci dans le
  /// sélecteur de ville.
  Future<Either<Failure, List<City>>> getRecentCities();

  /// Départs correspondant aux critères de recherche.
  ///
  /// Une liste vide est un succès — la liaison n'est pas desservie ce
  /// jour-là — et non un [Failure] : l'écran a un état « aucun départ »
  /// dédié, distinct de l'état d'erreur.
  Future<Either<Failure, List<Departure>>> searchDepartures(
    SearchCriteria criteria,
  );

  /// Plan des sièges d'un départ.
  Future<Either<Failure, List<Seat>>> getSeats(Departure departure);

  /// Prochain voyage réservé, ou `null` si l'utilisateur n'en a aucun
  /// (c'est un cas normal, pas une erreur — d'où le `Right(null)` plutôt
  /// qu'un [Failure]).
  Future<Either<Failure, UpcomingTrip?>> getNextTrip();

  /// Billets du voyageur, tous statuts confondus.
  ///
  /// Une liste vide est un succès : c'est le cas de quelqu'un qui n'a
  /// encore rien réservé, et l'écran a un état dédié pour le dire.
  Future<Either<Failure, List<Ticket>>> getTickets();
}
