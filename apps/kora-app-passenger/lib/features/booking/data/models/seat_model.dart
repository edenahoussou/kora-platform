import '../../domain/entities/seat.dart';
import 'bus_json.dart';

/// Couche DATA : un siège tel que le renvoie
/// `GET bus/departures/{uuid}/seats`.
///
/// ```json
/// {
///   "uuid": "2d6328fc-…",
///   "code": "2A",
///   "type": "standard",
///   "sellable": true,
///   "availability_status": "available"
/// }
/// ```
///
/// La disponibilité est calculée **pour le tronçon demandé** : c'est le
/// serveur qui répond « occupé de Cotonou à Porto-Novo », l'app n'a rien
/// à recouper.
class SeatModel {
  const SeatModel({
    required this.uuid,
    required this.code,
    required this.type,
    required this.sellable,
    required this.availabilityStatus,
  });

  factory SeatModel.fromJson(Map<String, dynamic> json) {
    return SeatModel(
      uuid: BusJson.string(json['uuid']),
      code: BusJson.string(json['code']),
      type: BusJson.string(json['type']),
      sellable: json['sellable'] == true,
      availabilityStatus: BusJson.string(json['availability_status']),
    );
  }

  final String uuid;

  /// Ce que lit le voyageur, et d'où se déduit la place dans le plan.
  final String code;

  /// `standard`, `premium` ou `crew`.
  final String type;

  final bool sellable;

  /// `available`, `occupied` ou `blocked`.
  final String availabilityStatus;

  Seat toEntity() {
    final position = _SeatPosition.parse(code);

    return Seat(
      id: uuid,
      label: code,
      row: position.row,
      column: position.column,
      status: _status(),
    );
  }

  /// L'API ne connaît que trois états. `embarked` et `held`, que porte
  /// aussi le domaine, viennent de l'embarquement et de la réservation en
  /// cours : aucun endpoint ne les expose ici.
  SeatStatus _status() {
    // Un siège invendable est bloqué quoi qu'annonce sa disponibilité :
    // l'équipage occupe des places `available` qu'on ne doit pas offrir.
    if (!sellable) return SeatStatus.blocked;

    return switch (availabilityStatus) {
      'available' => SeatStatus.free,
      'occupied' => SeatStatus.occupied,
      'blocked' => SeatStatus.blocked,
      // Un statut inconnu ne se vend pas : mieux vaut refuser une place
      // libre que d'en vendre une qui ne l'est plus.
      _ => SeatStatus.blocked,
    };
  }
}

/// Place dans la grille, déduite du code (`2A` → rangée 2, colonne A).
///
/// L'API n'envoie **aucune coordonnée** : ni rangée, ni colonne, ni côté
/// du couloir. Le plan repose donc sur cette convention de nommage, la
/// seule que suivent les bus KORA aujourd'hui. Un véhicule numéroté
/// autrement afficherait un plan déformé — c'est le prix à payer tant que
/// la réponse ne porte pas la disposition.
extension type const _SeatPosition._((int row, int column) _value) {
  factory _SeatPosition.parse(String code) {
    final match = RegExp(r'^(\d+)\s*([A-Za-z])$').firstMatch(code.trim());
    if (match == null) return const _SeatPosition._((0, 0));

    final row = int.tryParse(match.group(1)!) ?? 0;
    // `A` → 1, `B` → 2… La casse ne compte pas.
    final column = match.group(2)!.toUpperCase().codeUnitAt(0) - 64;

    return _SeatPosition._((row, column));
  }

  int get row => _value.$1;
  int get column => _value.$2;
}
