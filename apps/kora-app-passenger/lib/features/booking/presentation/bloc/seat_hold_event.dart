part of 'seat_hold_bloc.dart';

sealed class SeatHoldEvent extends Equatable {
  const SeatHoldEvent();

  @override
  List<Object?> get props => [];
}

/// « Continuer » sur le plan : fige le prix, retient les sièges, arme le
/// décompte.
class SeatHoldRequested extends SeatHoldEvent {
  const SeatHoldRequested({required this.departure, required this.seats});

  final Departure departure;

  /// Dans l'ordre de sélection : c'est cet ordre qui attribue les sièges
  /// aux voyageurs du formulaire suivant.
  final List<Seat> seats;

  @override
  List<Object?> get props => [departure, seats];
}

/// Le voyageur revient sur ses choix. Rend les sièges à la vente et coupe
/// le décompte.
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
