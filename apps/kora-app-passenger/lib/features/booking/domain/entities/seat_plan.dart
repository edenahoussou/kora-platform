import 'package:equatable/equatable.dart';

import 'seat.dart';

/// Ce qu'une cellule de la cabine occupe comme place.
///
/// La grille décrit **tout** le plancher, pas seulement les sièges : le
/// couloir, la porte et le poste de conduite y tiennent une case, ce qui
/// permet de dessiner un bus qui n'est pas en 2+2.
enum SeatCellKind {
  seat,
  aisle,
  empty,
  door,
  driver;

  /// Un type inconnu occupe la place sans rien prétendre être.
  ///
  /// Le serveur peut en ajouter — des toilettes, un escalier — avant que
  /// cette version de l'app ne disparaisse des téléphones. Mieux vaut un
  /// trou dans le plan qu'un plan qui refuse de se dessiner.
  static SeatCellKind of(String raw) => switch (raw) {
    'seat' => SeatCellKind.seat,
    'aisle' => SeatCellKind.aisle,
    'door' => SeatCellKind.door,
    'driver' => SeatCellKind.driver,
    _ => SeatCellKind.empty,
  };
}

/// Une case du plancher, à sa place exacte.
class SeatCell extends Equatable {
  const SeatCell({
    required this.kind,
    required this.x,
    required this.y,
    this.code,
    this.isCrew = false,
    this.isUnderMaintenance = false,
  });

  final SeatCellKind kind;

  /// Colonne et rangée, telles que le serveur les a calculées. L'app ne
  /// les déduit plus du code du siège — voir [SeatPlan].
  final int x;
  final int y;

  /// Le code du siège (`1A`), qui rattache la case à son état dans la
  /// liste plate. `null` pour tout ce qui n'est pas un siège.
  final String? code;

  /// Siège d'équipage : il existe sur le plan, il ne se vend pas.
  final bool isCrew;

  /// Hors service : la place est physiquement là, mais indisponible.
  final bool isUnderMaintenance;

  /// Vrai quand la case est un siège que le voyageur peut espérer
  /// choisir — sous réserve de sa disponibilité, qui vient d'ailleurs.
  bool get isBookableSeat =>
      kind == SeatCellKind.seat && !isCrew && !isUnderMaintenance;

  @override
  List<Object?> get props => [kind, x, y, code, isCrew, isUnderMaintenance];
}

/// La géométrie de la cabine, immuable pour un gabarit donné.
class SeatGrid extends Equatable {
  const SeatGrid({required this.rows, required this.cols, required this.cells});

  final int rows;
  final int cols;
  final List<SeatCell> cells;

  /// Vrai quand la grille a de quoi dessiner un plan.
  ///
  /// Une grille sans dimension ou sans le moindre siège ne vaut pas
  /// mieux que pas de grille du tout : l'écran retombe alors sur le plan
  /// déduit des codes.
  bool get isUsable =>
      rows > 0 &&
      cols > 0 &&
      cells.any((cell) => cell.kind == SeatCellKind.seat);

  /// Vrai quand la grille apprend quelque chose que les codes ne disent
  /// pas déjà.
  ///
  /// Les gabarits actuels sont **dérivés de l'ancien `layout`** : le
  /// serveur en tire une grille qui n'a que des sièges, sans couloir ni
  /// porte ni poste de conduite. La dessiner telle quelle collerait les
  /// quatre sièges d'une rangée les uns contre les autres et ferait
  /// disparaître l'allée centrale — un plan moins lisible qu'avant, pour
  /// une information que les codes donnaient déjà.
  ///
  /// On ne bascule donc sur la grille que lorsqu'elle décrit vraiment la
  /// cabine : une case qui n'est pas un siège, ou un siège qu'on ne peut
  /// pas vendre. Un vrai gabarit, composé dans l'éditeur, en a toujours.
  bool get describesCabin => cells.any(
    (cell) =>
        cell.kind != SeatCellKind.seat ||
        cell.isCrew ||
        cell.isUnderMaintenance,
  );

  @override
  List<Object?> get props => [rows, cols, cells];
}

/// Couche DOMAIN : le plan d'un départ — l'état des sièges, et la
/// géométrie de la cabine quand le serveur la connaît.
///
/// Les deux viennent de la même réponse mais ne vivent pas au même
/// rythme : l'état change à chaque consultation, la géométrie est fixée
/// par le gabarit du bus (voir ADR 0020). C'est pourquoi les sièges
/// portent leur disponibilité et la grille, ses coordonnées ; les deux se
/// recollent sur le **code** du siège.
///
/// [grid] est `null` pour un bus sans gabarit. L'écran garde alors son
/// rendu historique, déduit des codes (`2A` → rangée 2, colonne A), qui
/// suppose une numérotation 2+2.
class SeatPlan extends Equatable {
  const SeatPlan({required this.seats, this.grid, this.compositionId});

  const SeatPlan.flat(this.seats) : grid = null, compositionId = null;

  /// L'inventaire : un siège par place vendable ou non, avec son état
  /// pour le tronçon demandé.
  final List<Seat> seats;

  final SeatGrid? grid;

  /// Le gabarit dont vient [grid] : de quoi reconnaître un plan périmé,
  /// et de quoi le mettre en cache le jour où on le fera.
  final String? compositionId;

  bool get hasGrid => grid?.isUsable ?? false;

  @override
  List<Object?> get props => [seats, grid, compositionId];
}
