part of 'seat_hold_bloc.dart';

sealed class SeatHoldEvent extends Equatable {
  const SeatHoldEvent();

  @override
  List<Object?> get props => [];
}

/// « Continuer vers le paiement » sur le récapitulatif : fige le prix,
/// retient les sièges, rattache les voyageurs, arme le décompte.
class SeatHoldCheckoutRequested extends SeatHoldEvent {
  const SeatHoldCheckoutRequested({
    required this.departure,
    required this.passengers,
    this.quote,
    this.returnDeparture,
    this.returnSeats,
    this.payer = const GroupPayer.personal(),
  });

  /// L'aller, sur un aller-retour : c'est lui que porte le devis.
  final Departure departure;

  /// Les voyageurs, chacun avec son siège aller, dans l'ordre du plan.
  final List<Passenger> passengers;

  /// Le devis que le récapitulatif affiche — remise de groupe et code promo
  /// compris. `null` s'il n'a pas pu être chargé : on en redemande un.
  final GroupQuote? quote;

  /// Le retour d'un aller-retour, avec ses propres sièges.
  ///
  /// Les deux trajets partent dans **un seul devis et un seul blocage** :
  /// le serveur réclame un siège pour chaque tronçon du devis, et c'est
  /// ce qui donne au voyageur un paiement unique.
  final Departure? returnDeparture;

  /// Un siège de retour par voyageur, dans le même ordre que [passengers].
  final List<Seat>? returnSeats;

  /// Choisi sur l'écran de groupe : personnel ou compte entreprise.
  final GroupPayer payer;

  @override
  List<Object?> get props => [
    departure,
    passengers,
    quote,
    returnDeparture,
    returnSeats,
    payer,
  ];
}

/// Le voyageur revient sur ses choix. Rend les sièges à la vente et coupe
/// le décompte.
class SeatHoldReleased extends SeatHoldEvent {
  const SeatHoldReleased();
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

/// Applique [promoCode] au devis. Porte tout ce qu'il faut pour rebloquer
/// les mêmes sièges si une réservation est déjà retenue.
class SeatHoldRepriceRequested extends SeatHoldEvent {
  const SeatHoldRepriceRequested({
    required this.departure,
    required this.passengers,
    required this.promoCode,
    this.returnDeparture,
    this.returnSeats,
    this.payer = const GroupPayer.personal(),
  });

  final Departure departure;

  /// Les voyageurs, chacun avec son siège aller — dans l'ordre du blocage.
  final List<Passenger> passengers;

  final String promoCode;
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
