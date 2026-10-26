// La grille de cabine, telle que l'API la pose à côté de ses sièges.
//
// `GET bus/departures/{uuid}/seats` a été étendu **sans rien retirer**
// (ADR 0020) : `data` n'a pas bougé, `grid` et `composition_uuid` sont
// venus s'ajouter à la racine. Ces tests tiennent les deux bouts — lire
// la grille quand elle est là, ne pas trébucher quand elle n'y est pas.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/data/models/seat_grid_model.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat_plan.dart';

void main() {
  group('la grille', () {
    test('rend chaque case à sa place, avec son type', () {
      final grid = SeatGridModel.fromJson({
        'rows': 2,
        'cols': 4,
        'cells': [
          {'kind': 'driver', 'x': 0, 'y': 0},
          {'kind': 'door', 'x': 3, 'y': 0},
          {
            'kind': 'seat',
            'x': 0,
            'y': 1,
            'code': '1A',
            'seatKind': 'standard',
          },
          {'kind': 'aisle', 'x': 2, 'y': 1},
        ],
      });

      expect(grid, isNotNull);
      expect(grid!.rows, 2);
      expect(grid.cols, 4);
      expect(grid.cells, hasLength(4));

      final seat = grid.cells[2];
      expect(seat.kind, SeatCellKind.seat);
      expect(seat.code, '1A');
      expect(seat.x, 0);
      expect(seat.y, 1);
      expect(seat.isBookableSeat, isTrue);

      expect(grid.cells.first.kind, SeatCellKind.driver);
      expect(grid.cells[1].kind, SeatCellKind.door);
      expect(grid.cells.last.kind, SeatCellKind.aisle);
    });

    test('retient ce qui empêche de vendre une place', () {
      // Un siège d'équipage et un siège hors service existent à bord :
      // le plan doit les montrer, et l'écran les rendre intouchables.
      final grid = SeatGridModel.fromJson({
        'rows': 1,
        'cols': 2,
        'cells': [
          {'kind': 'seat', 'x': 0, 'y': 0, 'code': '1A', 'seatKind': 'crew'},
          {'kind': 'seat', 'x': 1, 'y': 0, 'code': '1B', 'maintenance': true},
        ],
      })!;

      expect(grid.cells.first.isCrew, isTrue);
      expect(grid.cells.first.isBookableSeat, isFalse);
      expect(grid.cells.last.isUnderMaintenance, isTrue);
      expect(grid.cells.last.isBookableSeat, isFalse);
    });

    test('met les codes dans la casse de l’inventaire', () {
      // Les cases se joignent aux sièges par leur code : deux casses
      // différentes des deux côtés, et la jointure tombe à côté.
      final grid = SeatGridModel.fromJson({
        'rows': 1,
        'cols': 1,
        'cells': [
          {'kind': 'seat', 'x': 0, 'y': 0, 'code': '2a'},
        ],
      })!;

      expect(grid.cells.single.code, '2A');
    });

    test('ne s’émeut pas d’un type qu’elle ne connaît pas', () {
      // Le serveur peut ajouter des toilettes ou un escalier avant que
      // cette version ne disparaisse des téléphones : la case occupe sa
      // place sans rien prétendre être.
      final grid = SeatGridModel.fromJson({
        'rows': 1,
        'cols': 1,
        'cells': [
          {'kind': 'toilets', 'x': 0, 'y': 0},
        ],
      })!;

      expect(grid.cells.single.kind, SeatCellKind.empty);
    });
  });

  group('sans grille', () {
    test('un bus sans gabarit ne rend rien', () {
      // `grid: null` est un cas prévu, pas une anomalie : l'écran
      // retombe sur le plan déduit des codes.
      expect(SeatGridModel.fromJson(null), isNull);
    });

    test('une grille sans case ne rend rien non plus', () {
      expect(SeatGridModel.fromJson({'rows': 4, 'cols': 4}), isNull);
      expect(
        SeatGridModel.fromJson({'rows': 4, 'cols': 4, 'cells': <Object>[]}),
        isNull,
      );
    });

    test('une grille de sièges seuls ne décrit pas la cabine', () {
      // Les gabarits actuels sont dérivés de l'ancien `layout` : le
      // serveur en tire une grille sans couloir ni porte. La dessiner
      // telle quelle ferait disparaître l'allée centrale, pour une
      // information que les codes donnaient déjà.
      final grid = SeatGridModel.fromJson({
        'rows': 2,
        'cols': 2,
        'cells': [
          {'kind': 'seat', 'x': 0, 'y': 0, 'code': '1A'},
          {'kind': 'seat', 'x': 1, 'y': 0, 'code': '1B'},
        ],
      })!;

      expect(grid.isUsable, isTrue);
      expect(grid.describesCabin, isFalse);
    });

    test('un couloir, une place d’équipage ou un hors-service suffisent', () {
      for (final cell in [
        {'kind': 'aisle', 'x': 1, 'y': 0},
        {'kind': 'seat', 'x': 1, 'y': 0, 'code': '1B', 'seatKind': 'crew'},
        {'kind': 'seat', 'x': 1, 'y': 0, 'code': '1B', 'maintenance': true},
      ]) {
        final grid = SeatGridModel.fromJson({
          'rows': 1,
          'cols': 2,
          'cells': [
            {'kind': 'seat', 'x': 0, 'y': 0, 'code': '1A'},
            cell,
          ],
        })!;

        expect(grid.describesCabin, isTrue, reason: '$cell');
      }
    });

    test('une grille sans siège ne vaut pas mieux que pas de grille', () {
      // Des dimensions et un couloir ne dessinent pas une cabine.
      final grid = SeatGridModel.fromJson({
        'rows': 1,
        'cols': 1,
        'cells': [
          {'kind': 'aisle', 'x': 0, 'y': 0},
        ],
      })!;

      expect(grid.isUsable, isFalse);
    });
  });
}
