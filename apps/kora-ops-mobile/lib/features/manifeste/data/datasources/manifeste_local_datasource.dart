import 'package:sqflite/sqflite.dart';

import '../../../../core/database/app_database.dart';
import '../../../ops/domain/entities/passenger.dart';

/// Manifeste en cache pour un départ, avec ses métadonnées — voir
/// `manifest_meta` (`AppDatabase`).
class CachedManifest {
  const CachedManifest({
    required this.passengers,
    this.version,
    this.fetchedAt,
  });

  final List<Passenger> passengers;
  final String? version;
  final DateTime? fetchedAt;
}

abstract class ManifesteLocalDataSource {
  /// `null` si aucun manifeste n'a jamais été mis en cache pour ce
  /// départ (jamais une liste vide sans métadonnées — voir
  /// `ManifesteRepositoryImpl.load`, qui distingue "jamais chargé" de
  /// "chargé mais aucun passager").
  Future<CachedManifest?> readCache(String departureUuid);

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
  Future<CachedManifest?> readCache(String departureUuid) async {
    final Database db = await _database.database;

    final List<Map<String, Object?>> metaRows = await db.query(
      'manifest_meta',
      where: 'departure_uuid = ?',
      whereArgs: <Object?>[departureUuid],
      limit: 1,
    );
    if (metaRows.isEmpty) return null;

    final List<Map<String, Object?>> rows = await db.query(
      'manifest_cache',
      where: 'departure_uuid = ?',
      whereArgs: <Object?>[departureUuid],
    );

    return CachedManifest(
      passengers: rows.map(_passengerOf).toList(),
      version: metaRows.first['manifest_version'] as String?,
      fetchedAt:
          DateTime.tryParse(metaRows.first['fetched_at'] as String? ?? ''),
    );
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

      // Toujours écrite, même sans manifestVersion (jamais renvoyé par le
      // backend en pratique) : c'est cette ligne qui marque "ce départ a
      // déjà été téléchargé au moins une fois" — voir readCache, qui s'en
      // sert pour distinguer un cache vide d'une absence de cache.
      await txn.insert(
        'manifest_meta',
        <String, Object?>{
          'departure_uuid': departureUuid,
          'manifest_version': manifestVersion,
          'fetched_at': fetchedAt,
        },
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
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
