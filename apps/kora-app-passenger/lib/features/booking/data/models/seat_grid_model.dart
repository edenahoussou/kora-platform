import '../../../../core/network/api_json.dart';
import '../../domain/entities/seat_plan.dart';

/// Couche DATA : la grille de cabine que `GET bus/departures/{uuid}/seats`
/// pose à côté de sa liste de sièges.
///
/// ```json
/// {
///   "composition_uuid": "9b1deb4d-…",
///   "grid": {
///     "rows": 14,
///     "cols": 5,
///     "cells": [
///       {"kind": "driver", "x": 0, "y": 0},
///       {"kind": "door",   "x": 4, "y": 0},
///       {"kind": "seat",   "x": 0, "y": 1, "code": "1A", "seatKind": "standard"},
///       {"kind": "aisle",  "x": 2, "y": 1},
///       {"kind": "seat",   "x": 4, "y": 1, "code": "1D", "maintenance": true}
///     ]
///   },
///   "data": [ … ]
/// }
/// ```
///
/// Les deux clés sont une **extension rétrocompatible** (ADR 0020) : une
/// version publiée qui ne lit que `data` continue de fonctionner, et un
/// bus sans gabarit les renvoie nulles. D'où la lecture défensive —
/// l'absence de grille n'est pas une anomalie, c'est un cas prévu.
abstract final class SeatGridModel {
  /// La grille portée par la réponse, ou `null` s'il n'y en a pas.
  static SeatGrid? fromJson(Object? raw) {
    if (raw is! Map) return null;

    final json = Map<String, dynamic>.from(raw);
    final cells = [for (final cell in ApiJson.list(json['cells'])) _cell(cell)];

    if (cells.isEmpty) return null;

    return SeatGrid(
      rows: ApiJson.intOrNull(json['rows']) ?? 0,
      cols: ApiJson.intOrNull(json['cols']) ?? 0,
      cells: cells,
    );
  }

  static SeatCell _cell(Map<String, dynamic> json) {
    return SeatCell(
      kind: SeatCellKind.of(ApiJson.string(json['kind'])),
      x: ApiJson.intOrNull(json['x']) ?? 0,
      y: ApiJson.intOrNull(json['y']) ?? 0,
      // Les codes se comparent à ceux de la liste plate : on les met dans
      // la même casse des deux côtés plutôt que d'espérer qu'elles
      // coïncident.
      code: ApiJson.stringOrNull(json['code'])?.toUpperCase(),
      isCrew: ApiJson.string(json['seatKind']) == 'crew',
      isUnderMaintenance: json['maintenance'] == true,
    );
  }
}
