import 'package:equatable/equatable.dart';

import 'price.dart';

/// Couche DOMAIN : un prix ferme, arrêté par le serveur pour un tronçon.
///
/// Étape obligée avant de retenir des sièges : `POST bus/holds` réclame un
/// `price_quote_uuid`. Le devis fige le tarif — coefficient de remplissage
/// compris — pour que le montant annoncé au moment du choix soit celui que
/// le voyageur paiera, même si la demande monte entre-temps.
class Quote extends Equatable {
  const Quote({
    required this.id,
    required this.total,
    required this.passengerCount,
    required this.expiresAt,
  });

  final String id;

  /// Le montant pour l'ensemble des voyageurs du devis.
  final Price total;

  /// Le nombre de voyageurs figé par le devis. Le blocage refuse (422) une
  /// liste de sièges qui ne lui correspond pas.
  final int passengerCount;

  /// Le devis a sa propre échéance, distincte de celle du blocage : il
  /// expire d'abord, et plus tôt.
  final DateTime expiresAt;

  @override
  List<Object?> get props => [id, total, passengerCount, expiresAt];
}
