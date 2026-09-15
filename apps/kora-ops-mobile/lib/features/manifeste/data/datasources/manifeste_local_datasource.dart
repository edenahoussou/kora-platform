import 'package:sqflite/sqflite.dart';

import '../../../../core/database/app_database.dart';
import '../../../ops/domain/entities/passenger.dart';

abstract class ManifesteLocalDataSource {
  Future<List<Passenger>> readCache(String departureUuid);

  /// Remplace tout le manifeste en cache pour [departureUuid] — un
  /// manifeste tient sur un bus (quelques dizaines à ~200 lignes), un
  /// remplacement complet à chaque fetch réussi reste largement assez
  /// rapide et évite toute logique de diff.
  Future<void> replaceCache(
    String departureUuid,
    List<Passenger> passengers, {
    String? manifestVersion,
  });
}

class ManifesteLocalDataSourceImpl implements ManifesteLocalDataSource {
  ManifesteLocalDataSourceImpl(this._database);

  final AppDatabase _database;

  @override
  Future<List<Passenger>> readCache(String departureUuid) async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'manifest_cache',
      where: 'departure_uuid = ?',
      whereArgs: <Object?>[departureUuid],
    );

    return rows.map(_passengerOf).toList();
  }

  @override
  Future<void> replaceCache(
    String departureUuid,
    List<Passenger> passengers, {
    String? manifestVersion,
  }) async {
    final Database db = await _database.database;
    final String fetchedAt = DateTime.now().toIso8601String();

    await db.transaction((txn) async {
      await txn.delete(
        'manifest_cache',
        where: 'departure_uuid = ?',
        whereArgs: <Object?>[departureUuid],
      );
      for (final Passenger passenger in passengers) {
        await txn.insert('manifest_cache', <String, Object?>{
          'departure_uuid': departureUuid,
          'ticket_uuid': passenger.id,
          'short_code': passenger.ticketId,
          'passenger_name': passenger.nomComplet,
          'seat_code': passenger.siege,
          'origin_stop': passenger.segmentOrigine,
          'destination_stop': passenger.segmentDestination,
          'segment_status': passenger.statut.name,
          'fetched_at': fetchedAt,
        });
      }

      if (manifestVersion != null) {
        await txn.insert(
          'manifest_meta',
          <String, Object?>{
            'departure_uuid': departureUuid,
            'manifest_version': manifestVersion,
            'fetched_at': fetchedAt,
          },
          conflictAlgorithm: ConflictAlgorithm.replace,
        );
      }
    });
  }

  Passenger _passengerOf(Map<String, Object?> row) => Passenger(
        id: row['ticket_uuid'] as String? ?? '',
        nomComplet: row['passenger_name'] as String? ?? 'Passager',
        siege: row['seat_code'] as String? ?? '—',
        statut: BoardingStatus.values.firstWhere(
          (BoardingStatus s) => s.name == row['segment_status'],
          orElse: () => BoardingStatus.enAttente,
        ),
        segmentOrigine: row['origin_stop'] as String? ?? '—',
        segmentDestination: row['destination_stop'] as String? ?? '—',
        ticketId: row['short_code'] as String? ?? '',
      );
}
