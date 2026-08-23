import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/city.dart';
import '../entities/departure.dart';

/// Couche DOMAIN : la recherche de départs, isolée du reste du parcours.
///
/// [BookingRepository] couvre tout le tunnel de réservation — sièges,
/// billets, prochain voyage. La recherche, elle, est le seul morceau
/// branché sur le catalogue Bus public (`bus/cities`, `bus/departures`),
/// sans session ni panier : lui donner son propre contrat permet de la
/// faire évoluer, la tester et la moquer sans traîner le reste.
abstract class DepartureRepository {
  /// Villes desservies, pour l'autocomplete de l'accueil.
  Future<Either<Failure, List<City>>> getCities();

  /// Départs d'une liaison à une date donnée.
  ///
  /// Une liste vide est un **succès** — la liaison n'est pas desservie ce
  /// jour-là — et non un [Failure] : l'écran a un état « aucun départ »
  /// dédié, distinct de l'état d'erreur.
  Future<Either<Failure, List<Departure>>> search({
    required String originCity,
    required String destinationCity,
    required DateTime date,
  });
}
