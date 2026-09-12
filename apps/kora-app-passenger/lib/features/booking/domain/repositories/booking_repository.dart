import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/city.dart';
import '../entities/departure.dart';
import '../entities/line_stop.dart';
import '../entities/seat.dart';
import '../entities/ticket.dart';
import '../entities/upcoming_trip.dart';

/// Couche DOMAIN : contrat du parcours de réservation Bus.
///
/// Le domaine ne sait pas d'où viennent les données : c'est le service
/// locator qui décide, et lui seul, si elles sortent de l'API ou du jeu
/// de démonstration.
///
/// La **recherche** de départs n'est pas ici : elle a son propre contrat,
/// [DepartureRepository]. Elle interroge le catalogue Bus public, sans
/// session ni panier, là où ce dépôt-ci porte le tunnel de réservation —
/// sièges, billets, prochain voyage.
abstract class BookingRepository {
  /// Dernières villes recherchées, proposées en raccourci dans le
  /// sélecteur de ville.
  Future<Either<Failure, List<City>>> getRecentCities();

  /// Plan des sièges d'un départ.
  /// Les arrêts d'une ligne, dans l'ordre du trajet.
  ///
  /// Vide pour une ligne dont le serveur n'a pas publié d'arrêts : c'est
  /// un résultat, pas une erreur, et l'écran de détail tait alors sa
  /// section « Les étapes ».
  Future<Either<Failure, List<LineStop>>> getLineStops(String lineId);

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
