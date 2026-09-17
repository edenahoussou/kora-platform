import 'package:equatable/equatable.dart';

/// Statut d'embarquement d'un passager sur le départ courant.
enum BoardingStatus { enAttente, embarque, refuse, descenduAnticipe }

extension BoardingStatusX on BoardingStatus {
  String get label => switch (this) {
        BoardingStatus.enAttente => 'En attente',
        BoardingStatus.embarque => 'Embarqué',
        BoardingStatus.refuse => 'Refusé',
        BoardingStatus.descenduAnticipe => 'Descente anticipée',
      };
}

/// Un passager du départ géré, tel qu'il apparaît dans le Manifeste.
class Passenger extends Equatable {
  const Passenger({
    required this.id,
    required this.nomComplet,
    required this.siege,
    required this.statut,
    required this.segmentOrigine,
    required this.segmentDestination,
    required this.ticketId,
    this.telephone,
    this.originStopUuid,
    this.destinationStopUuid,
    this.boardingEventUuid,
  });

  final String id;
  final String nomComplet;

  /// Ex : "12A"
  final String siege;

  final BoardingStatus statut;

  /// Ex : "Cotonou"
  final String segmentOrigine;

  /// Ex : "Porto-Novo"
  final String segmentDestination;

  final String ticketId;
  final String? telephone;

  /// UUID de l'arrêt d'origine/destination du segment (voir
  /// `OpsManifestRowResource.originStop`/`destinationStop`) — utilisés
  /// pour calculer les arrêts compatibles d'une descente anticipée (voir
  /// `compatibleStopsFor`, MEZ-87). `null` si le manifeste vient du cache
  /// local d'une version antérieure à MEZ-87 (redemandera un
  /// rafraîchissement).
  final String? originStopUuid;
  final String? destinationStopUuid;

  /// UUID du dernier `BoardingEvent` accepté pour ce billet sur ce départ
  /// — `null` si jamais embarqué. Reste renseigné même après une
  /// annulation (voir note côté backend, `OpsManifestRowResource`) : ne
  /// jamais s'y fier seul, toujours croiser avec `statut ==
  /// BoardingStatus.embarque` avant de proposer "Annuler l'embarquement".
  final String? boardingEventUuid;

  Passenger copyWith({BoardingStatus? statut}) => Passenger(
        id: id,
        nomComplet: nomComplet,
        siege: siege,
        statut: statut ?? this.statut,
        segmentOrigine: segmentOrigine,
        segmentDestination: segmentDestination,
        ticketId: ticketId,
        telephone: telephone,
        originStopUuid: originStopUuid,
        destinationStopUuid: destinationStopUuid,
        boardingEventUuid: boardingEventUuid,
      );

  @override
  List<Object?> get props => <Object?>[
        id,
        nomComplet,
        siege,
        statut,
        segmentOrigine,
        segmentDestination,
        ticketId,
        telephone,
        originStopUuid,
        destinationStopUuid,
        boardingEventUuid,
      ];
}
