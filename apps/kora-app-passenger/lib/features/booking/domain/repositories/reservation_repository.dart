import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/company_account.dart';
import '../entities/departure.dart';
import '../entities/group_payer.dart';
import '../entities/group_quote.dart';
import '../entities/group_ticket.dart';
import '../entities/passenger.dart';
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
  /// Le devis d'un groupe, remise comprise.
  ///
  /// Distinct de [quote] : celui-ci passe par `bus/itinerary-quotes`, le
  /// seul point d'entrée qui applique la remise de groupe et la détaille
  /// au passager. Il sert à **afficher** le prix ; le blocage d'un groupe
  /// s'appuiera sur son `itinerary_quote_uuid`.
  /// Les billets émis d'une réservation, un par voyageur.
  ///
  /// Une liste vide est un succès : l'émission suit le paiement, et le
  /// voyageur peut arriver sur l'écran avant que le serveur ait fini.
  Future<Either<Failure, List<GroupTicket>>> bookingTickets(
    String reservationId,
  );

  /// Les comptes entreprise dont le voyageur est membre actif.
  ///
  /// Une liste vide est un succès : la plupart des voyageurs n'appartiennent
  /// à aucune entreprise, et l'écran doit le dire plutôt que d'échouer.
  Future<Either<Failure, List<CompanyAccount>>> companyAccounts();

  /// Retient d'un seul coup tous les sièges d'un groupe.
  ///
  /// Atomique : soit les N sièges partent ensemble, soit aucun. Retenir la
  /// moitié d'un groupe laisserait une réservation inutilisable et des
  /// places retirées de la vente pour rien.
  Future<Either<Failure, SeatHold>> holdGroup({
    required GroupQuote quote,
    required List<Seat> seats,
    required GroupPayer payer,
    required String idempotencyKey,
  });

  Future<Either<Failure, GroupQuote>> groupQuote({
    required Departure departure,
    required int passengers,
  });

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

  /// Enregistre les identités des voyageurs sur la réservation retenue.
  ///
  /// Rend la réservation rafraîchie : le serveur renvoie `status` et
  /// `expires_at` à jour, et c'est lui qui fait autorité sur le temps
  /// restant.
  Future<Either<Failure, SeatHold>> savePassengers({
    required String reservationId,
    required List<Passenger> passengers,
  });

  /// Rend les sièges avant l'échéance.
  ///
  /// Appelé quand le voyageur revient sur le plan pour changer d'avis, et
  /// quand le décompte tombe à zéro — le serveur libère de son côté, mais
  /// le dire explicitement évite d'attendre son ménage.
  Future<Either<Failure, void>> release(String reservationId);
}
