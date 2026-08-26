import 'package:equatable/equatable.dart';

import 'price.dart';

/// Couche DOMAIN : des sièges retenus le temps de finaliser la réservation.
///
/// **Le blocage ne commence qu'à la confirmation.** Choisir un siège sur le
/// plan n'engage rien — c'est `POST bus/holds`, déclenché par « Continuer »,
/// qui retire les places de la vente et arme le compte à rebours. La
/// documentation du point d'entrée le dit ainsi : *« The hold starts only
/// when this endpoint is called, after the passenger confirms the selected
/// seats. »*
///
/// [expiresAt] vient du serveur, jamais d'un minuteur local : c'est lui qui
/// décide quand les sièges retournent à la vente. Un décompte calculé dans
/// l'app dériverait de l'horloge du serveur, et le voyageur pourrait payer
/// des places déjà relâchées.
class SeatHold extends Equatable {
  const SeatHold({
    required this.id,
    required this.expiresAt,
    required this.seatLabels,
    required this.total,
  });

  /// L'identifiant de la réservation, à passer à `DELETE holds/{uuid}` pour
  /// libérer les sièges avant l'heure.
  final String id;

  /// L'instant, **en UTC**, où le serveur rendra les sièges à la vente.
  final DateTime expiresAt;

  /// Les sièges retenus (`4A`, `4B`), dans l'ordre où ils ont été choisis.
  final List<String> seatLabels;

  final Price total;

  /// Ce qu'il reste à courir, jamais négatif.
  ///
  /// Prend [now] plutôt que de lire l'horloge : un décompte se teste mal
  /// quand il dépend de l'instant présent.
  Duration remaining(DateTime now) {
    final left = expiresAt.difference(now.toUtc());
    return left.isNegative ? Duration.zero : left;
  }

  bool hasExpired(DateTime now) => remaining(now) == Duration.zero;

  @override
  List<Object?> get props => [id, expiresAt, seatLabels, total];
}
