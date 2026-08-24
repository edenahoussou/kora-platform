// Lecture d'un siège, à partir du JSON exact de la staging.
//
// Le plan visuel repose entièrement sur cette traduction : un statut mal
// lu, et l'app vend une place déjà occupée.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/data/models/seat_model.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';

void main() {
  /// Réponse réelle de `GET bus/departures/{uuid}/seats`.
  Map<String, dynamic> json({
    String code = '2A',
    String type = 'standard',
    bool sellable = true,
    String status = 'available',
  }) => {
    'uuid': '33c429d7-3ed8-4f33-88b8-1ad0cb4eaa19',
    'code': code,
    'type': type,
    'sellable': sellable,
    'availability_status': status,
  };

  Seat seat(Map<String, dynamic> raw) => SeatModel.fromJson(raw).toEntity();

  group('statuts', () {
    test('`available` est réservable', () {
      expect(seat(json()).status, SeatStatus.free);
      expect(seat(json()).isSelectable, isTrue);
    });

    test('`occupied` ne l’est pas', () {
      expect(seat(json(status: 'occupied')).status, SeatStatus.occupied);
    });

    test('`blocked` ne l’est pas', () {
      expect(seat(json(status: 'blocked')).status, SeatStatus.blocked);
    });

    test('un siège invendable est bloqué, même annoncé libre', () {
      // Les places d'équipage arrivent `available` mais `sellable: false` :
      // les offrir à la vente serait le pire des deux mondes.
      final crew = seat(json(type: 'crew', sellable: false));

      expect(crew.status, SeatStatus.blocked);
      expect(crew.isSelectable, isFalse);
    });

    test('un statut inconnu ne se vend pas', () {
      // Mieux vaut refuser une place libre que d'en vendre une qui ne
      // l'est plus.
      expect(seat(json(status: 'quelque_chose')).status, SeatStatus.blocked);
    });
  });

  group('position dans le plan', () {
    test('déduit la rangée et la colonne du code', () {
      final a = seat(json(code: '2A'));
      expect(a.row, 2);
      expect(a.column, 1);

      final d = seat(json(code: '12D'));
      expect(d.row, 12);
      expect(d.column, 4);
    });

    test('accepte une minuscule et des espaces', () {
      final s = seat(json(code: ' 3c '));
      expect(s.row, 3);
      expect(s.column, 3);
    });

    test('un code hors convention retombe sur (0, 0) sans lever', () {
      // L'API n'envoie aucune coordonnée : le plan repose sur la
      // convention de nommage. Un code exotique ne doit pas faire tomber
      // l'écran entier.
      final s = seat(json(code: 'HORS-PLAN'));
      expect(s.row, 0);
      expect(s.column, 0);
      expect(s.label, 'HORS-PLAN');
    });
  });

  test('garde l’identifiant et le libellé de l’API', () {
    final s = seat(json(code: '4B'));

    expect(s.id, '33c429d7-3ed8-4f33-88b8-1ad0cb4eaa19');
    expect(s.label, '4B');
  });
}
