part of 'seat_hold_bloc.dart';

sealed class SeatHoldEvent extends Equatable {
  const SeatHoldEvent();

  @override
  List<Object?> get props => [];
}

/// « Continuer » sur le plan : fige le prix, retient les sièges, arme le
/// décompte.
class SeatHoldRequested extends SeatHoldEvent {
  const SeatHoldRequested({
    required this.departure,
    required this.seats,
    this.returnDeparture,
    this.returnSeats,
  });

  final Departure departure;

  /// Dans l'ordre de sélection : c'est cet ordre qui attribue les sièges
  /// aux voyageurs du formulaire suivant.
  final List<Seat> seats;

  /// Le retour d'un aller-retour, avec ses propres sièges.
  ///
  /// Les deux trajets partent dans **un seul devis et un seul blocage** :
  /// le serveur réclame un siège pour chaque tronçon du devis, et c'est
  /// ce qui donne au voyageur un paiement unique.
  final Departure? returnDeparture;

  /// Un siège de retour par voyageur, dans le même ordre que [seats].
  final List<Seat>? returnSeats;

  @override
  List<Object?> get props => [departure, seats, returnDeparture, returnSeats];
}

/// Le voyageur revient sur ses choix. Rend les sièges à la vente et coupe
/// le décompte.
/// Retient d'un coup tous les sièges d'un groupe.
///
/// Distinct de [SeatHoldRequested] : le groupe passe par le devis
/// d'itinéraire, seul à porter la remise, et par un blocage atomique. Un
/// événement à part plutôt qu'un drapeau sur l'autre — les deux chemins
/// n'appellent ni les mêmes routes ni les mêmes règles.
class SeatHoldGroupRequested extends SeatHoldEvent {
  const SeatHoldGroupRequested({
    required this.departure,
    required this.quote,
    required this.seats,
    this.payer = const GroupPayer.personal(),
  });

  final Departure departure;

  /// Le devis déjà obtenu : c'est son `itinerary_quote_uuid` que réclame le
  /// blocage.
  final GroupQuote quote;

  /// Dans l'ordre de sélection : le voyageur d'indice `i` occupera le
  /// i-ième siège, et le formulaire des identités suivra le même ordre.
  final List<Seat> seats;

  final GroupPayer payer;

  @override
  List<Object?> get props => [departure, quote, seats, payer];
}

class SeatHoldReleased extends SeatHoldEvent {
  const SeatHoldReleased();
}

/// Les identités saisies partent au serveur.
///
/// Le blocage existe déjà — c'est lui qui porte le `reservation_uuid` —
/// donc l'événement ne transporte que les voyageurs.
class SeatHoldPassengersSubmitted extends SeatHoldEvent {
  const SeatHoldPassengersSubmitted(this.passengers);

  final List<Passenger> passengers;

  @override
  List<Object?> get props => [passengers];
}

/// Le paiement a abouti : les sièges ne sont plus retenus, ils sont
/// vendus.
///
/// Distinct de [SeatHoldReleased], qui rend les places à la vente : ici on
/// coupe seulement le décompte, sans rien libérer. Réutiliser la
/// libération aurait appelé `DELETE holds` sur une réservation payée.
class SeatHoldSettled extends SeatHoldEvent {
  const SeatHoldSettled();
}

/// Un battement de seconde du décompte.
class SeatHoldTicked extends SeatHoldEvent {
  const SeatHoldTicked();
}

/// Applique [promoCode] à la réservation bloquée, ou retire le code quand il
/// vaut `null`. Porte tout ce qu'il faut pour rebloquer les mêmes sièges.
class SeatHoldRepriceRequested extends SeatHoldEvent {
  const SeatHoldRepriceRequested({
    required this.departure,
    required this.passengers,
    this.promoCode,
    this.returnDeparture,
    this.returnSeats,
    this.payer = const GroupPayer.personal(),
  });

  final Departure departure;

  /// Les voyageurs, chacun avec son siège aller — dans l'ordre du blocage.
  final List<Passenger> passengers;

  final String? promoCode;
  final Departure? returnDeparture;
  final List<Seat>? returnSeats;
  final GroupPayer payer;

  @override
  List<Object?> get props => [
    departure,
    passengers,
    promoCode,
    returnDeparture,
    returnSeats,
    payer,
  ];
}
