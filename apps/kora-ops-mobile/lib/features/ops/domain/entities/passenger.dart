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

  Passenger copyWith({BoardingStatus? statut}) => Passenger(
        id: id,
        nomComplet: nomComplet,
        siege: siege,
        statut: statut ?? this.statut,
        segmentOrigine: segmentOrigine,
        segmentDestination: segmentDestination,
        ticketId: ticketId,
        telephone: telephone,
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
      ];
}
