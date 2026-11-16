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
    required this.journey,
    required this.passengers,
    this.quote,
    this.payer = const GroupPayer.personal(),
  });

  /// Le voyage entier, sièges compris.
  ///
  /// Tous ses tronçons partent dans **un seul devis et un seul
  /// blocage** : le serveur réclame un siège pour chacun, et c'est ce
  /// qui donne au voyageur un paiement unique — qu'il change de bus en
  /// route ou qu'il rentre le surlendemain.
  final Journey journey;

  /// Les voyageurs, dans l'ordre du plan de sièges.
  final List<Passenger> passengers;

  /// Le devis que le récapitulatif affiche — remise de groupe et code promo
  /// compris. `null` s'il n'a pas pu être chargé : on en redemande un.
  final GroupQuote? quote;

  /// Choisi sur l'écran de groupe : personnel ou compte entreprise.
  final GroupPayer payer;

  @override
  List<Object?> get props => [journey, passengers, quote, payer];
}

/// Le voyageur revient sur ses choix. Rend les sièges à la vente et coupe
/// le décompte.
class SeatHoldReleased extends SeatHoldEvent {
  const SeatHoldReleased();
}

/// Les sièges ne sont plus retenus par l'app : paiement abouti,
/// réservation confiée à une agence, ou réservation expirée par le
/// serveur après un refus de paiement.
///
/// Distinct de [SeatHoldReleased], qui rend les places à la vente : ici on
/// coupe seulement le décompte, sans rien libérer. Réutiliser la
/// libération aurait appelé `DELETE holds` sur une réservation payée, ou
/// déjà passée à l'agence.
class SeatHoldSettled extends SeatHoldEvent {
  const SeatHoldSettled();
}

/// La réservation confiée à une agence revient au paiement en ligne : le
/// serveur a repris ses sièges (`resume-online`), le décompte repart.
class SeatHoldResumed extends SeatHoldEvent {
  const SeatHoldResumed({required this.hold, required this.passengers});

  final SeatHold hold;
  final int passengers;

  @override
  List<Object?> get props => [hold, passengers];
}

/// Un battement de seconde du décompte.
class SeatHoldTicked extends SeatHoldEvent {
  const SeatHoldTicked();
}

/// Applique [promoCode] au devis. Porte tout ce qu'il faut pour rebloquer
/// les mêmes sièges si une réservation est déjà retenue.
class SeatHoldRepriceRequested extends SeatHoldEvent {
  const SeatHoldRepriceRequested({
    required this.journey,
    required this.passengers,
    required this.promoCode,
    this.payer = const GroupPayer.personal(),
  });

  /// Le voyage entier, sièges compris : rebloquer les mêmes places
  /// demande de savoir lesquelles, sur chacun de ses tronçons.
  final Journey journey;

  /// Les voyageurs, dans l'ordre du blocage.
  final List<Passenger> passengers;

  final String promoCode;
  final GroupPayer payer;

  @override
  List<Object?> get props => [journey, passengers, promoCode, payer];
}
