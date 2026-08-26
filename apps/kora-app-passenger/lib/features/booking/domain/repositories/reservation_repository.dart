import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/departure.dart';
import '../entities/quote.dart';
import '../entities/seat.dart';
import '../entities/seat_hold.dart';

/// Couche DOMAIN : retenir des sièges, puis les rendre.
///
/// Son propre contrat, à côté de [DepartureRepository] et de
/// [BookingRepository] : ces trois-là ne parlent pas au même moment du
/// parcours. La recherche est publique et sans session ; le blocage,
/// lui, engage un compte et retire des places de la vente. Les mêler
/// aurait donné un dépôt dont la moitié des méthodes exigent un jeton.
abstract class ReservationRepository {
  /// Fige le prix du tronçon pour [passengers] voyageurs.
  ///
  /// À appeler juste avant [hold] : le blocage réclame l'identifiant du
  /// devis, et un devis expire vite.
  Future<Either<Failure, Quote>> quote({
    required Departure departure,
    required int passengers,
  });

  /// Retire [seats] de la vente et arme le compte à rebours.
  ///
  /// Le nombre de sièges doit égaler le `passengerCount` du devis, sinon
  /// le serveur refuse. Un siège pris entre l'affichage du plan et la
  /// confirmation donne un [SeatUnavailableFailure], porteur du siège
  /// fautif : c'est lui qu'il faut montrer, pas un message générique.
  Future<Either<Failure, SeatHold>> hold({
    required Departure departure,
    required Quote quote,
    required List<Seat> seats,
  });

  /// Rend les sièges avant l'échéance.
  ///
  /// Appelé quand le voyageur revient sur le plan pour changer d'avis, et
  /// quand le décompte tombe à zéro — le serveur libère de son côté, mais
  /// le dire explicitement évite d'attendre son ménage.
  Future<Either<Failure, void>> release(String reservationId);
}
