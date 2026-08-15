import 'package:equatable/equatable.dart';

/// L'état d'un siège dans le plan du bus, repris de la maquette
/// (`.seat`, `.seat.taken`, `.seat.held`, `.seat.blocked`).
enum SeatStatus {
  /// Réservable.
  free,

  /// Déjà vendu à un autre voyageur.
  occupied,

  /// Vendu **et** le voyageur est monté à bord. Indisponible comme
  /// [occupied] ; la distinction sert à l'équipage au moment du contrôle,
  /// et le plan des sièges la rend visible.
  embarked,

  /// Retenu temporairement (réservation en cours, minuteur affiché).
  held,

  /// Indisponible à la vente (siège technique, hôtesse, roue).
  blocked,
}

/// Couche DOMAIN : un siège du plan de bus.
///
/// [label] est ce que voit le voyageur (`4A`) ; [row] et [column] sont les
/// coordonnées dans la grille du plan, utilisées pour dessiner la carte
/// des sièges (`SeatMap` du design system).
class Seat extends Equatable {
  const Seat({
    required this.id,
    required this.label,
    required this.row,
    required this.column,
    this.status = SeatStatus.free,
  });

  final String id;
  final String label;
  final int row;
  final int column;
  final SeatStatus status;

  bool get isSelectable => status == SeatStatus.free;

  @override
  List<Object?> get props => [id, label, row, column, status];
}
