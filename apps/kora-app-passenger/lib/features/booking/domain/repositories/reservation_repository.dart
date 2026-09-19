import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/company_account.dart';
import '../entities/departure.dart';
import '../entities/group_payer.dart';
import '../entities/group_quote.dart';
import '../entities/group_ticket.dart';
import '../entities/passenger.dart';
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
  ///
  /// Un siège pris entre l'affichage du plan et la confirmation donne un
  /// [SeatUnavailableFailure], porteur du siège fautif : c'est lui qu'il
  /// faut montrer, pas un message générique.
  ///
  /// Sur un aller-retour, [returnSeats] porte le siège de retour de chaque
  /// voyageur, dans le même ordre : les deux trajets tiennent dans un seul
  /// blocage, donc un seul paiement.
  Future<Either<Failure, SeatHold>> holdGroup({
    required GroupQuote quote,
    required List<Seat> seats,
    required GroupPayer payer,
    required String idempotencyKey,
    List<Seat>? returnSeats,
  });

  /// Fige le prix du trajet pour [passengers] voyageurs, remise comprise.
  ///
  /// À appeler juste avant [holdGroup], qui réclame l'identifiant du
  /// devis — et un devis expire vite. Seul devis restant : MEZ-63 a retiré
  /// `bus/quotes`, et `bus/itinerary-quotes` sert désormais le voyageur
  /// seul comme le groupe, un voyageur seul étant un groupe d'un que le
  /// barème laisse au plein tarif.
  ///
  /// Un aller-retour tient dans le même devis : [returnDeparture] en est
  /// le second tronçon, et son total couvre les deux trajets.
  Future<Either<Failure, GroupQuote>> groupQuote({
    required Departure departure,
    required int passengers,
    Departure? returnDeparture,
    String? promoCode,
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
