import 'package:equatable/equatable.dart';

import 'departure.dart';
import 'itinerary.dart';
import 'price.dart';
import 'seat.dart';

/// Couche DOMAIN : le voyage en cours de réservation.
///
/// Il porte l'aller, le retour éventuel, et les sièges retenus tronçon
/// par tronçon. Avant lui, le tunnel se passait quatre champs de main en
/// main — `departure`, `returnDeparture`, `seats`, `returnSeats` — qui ne
/// savaient dire que « un trajet, ou deux ». Une correspondance en
/// demande autant que de bus, et le serveur en accepte six.
///
/// L'ordre fait foi : les tronçons de l'aller d'abord, ceux du retour
/// ensuite. C'est l'ordre de `leg_order` dans le devis et dans le
/// blocage, et celui dans lequel le voyageur choisit ses places.
class Journey extends Equatable {
  const Journey({
    required this.outbound,
    this.inbound,
    this.seatsByLeg = const [],
  });

  /// L'aller, direct ou à correspondance.
  final Itinerary outbound;

  /// Le retour d'un aller-retour, ou `null`.
  final Itinerary? inbound;

  /// Les sièges retenus : un tableau par tronçon, dans l'ordre de [legs],
  /// chacun rangé dans l'ordre des voyageurs.
  ///
  /// Se remplit tronçon par tronçon, au fil des plans de sièges.
  final List<List<Seat>> seatsByLeg;

  /// Tous les tronçons à pourvoir, aller puis retour.
  List<Departure> get legs => [...outbound.legs, ...?inbound?.legs];

  bool get isRoundTrip => inbound != null;

  /// Le voyage franchit une frontière, sur l'un quelconque de ses
  /// tronçons : c'est le formulaire international qu'il faudra remplir.
  bool get isInternational => legs.any((leg) => leg.isInternational);

  /// Le prix du voyage entier pour un voyageur, aller et retour compris.
  Price get total => Price(
    outbound.total.amount + (inbound?.total.amount ?? 0),
    currency: outbound.total.currency,
  );

  /// Le tronçon dont on choisit les places maintenant, ou `null` quand
  /// ils sont tous pourvus.
  Departure? get pendingLeg =>
      seatsByLeg.length < legs.length ? legs[seatsByLeg.length] : null;

  /// Le rang du tronçon en cours, à partir de 1 — ce que l'écran annonce
  /// (« Trajet 2 sur 3 »).
  int get pendingLegOrder => seatsByLeg.length + 1;

  bool get isComplete =>
      seatsByLeg.length == legs.length && seatsByLeg.isNotEmpty;

  /// Le nombre de voyageurs, tel que le premier plan l'a fixé.
  int get passengerCount => seatsByLeg.isEmpty ? 0 : seatsByLeg.first.length;

  /// Les sièges d'un voyageur, un par tronçon et dans l'ordre du voyage.
  List<Seat> seatsOf(int passengerIndex) => [
    for (final seats in seatsByLeg)
      if (passengerIndex < seats.length) seats[passengerIndex],
  ];

  /// Le même voyage, le tronçon courant pourvu.
  Journey withSeats(List<Seat> seats) => Journey(
    outbound: outbound,
    inbound: inbound,
    seatsByLeg: [...seatsByLeg, seats],
  );

  /// Le même voyage, sièges oubliés : ce qu'on repasse au plan quand le
  /// voyageur revient sur ses choix.
  Journey get withoutSeats => Journey(outbound: outbound, inbound: inbound);

  @override
  List<Object?> get props => [outbound, inbound, seatsByLeg];
}
