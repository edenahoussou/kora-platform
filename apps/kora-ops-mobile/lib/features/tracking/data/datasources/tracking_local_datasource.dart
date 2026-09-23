import 'package:sqflite/sqflite.dart';

import '../../../../core/database/app_database.dart';
import '../../domain/entities/tracking_position.dart';
import '../../domain/entities/tracking_position_source.dart';
import '../../domain/entities/tracking_position_status.dart';
import '../../domain/entities/tracking_session_snapshot.dart';

/// Couche DATA : file de positions en attente d'envoi (table
/// `tracking_positions_queue`) et état de la session de suivi en cours
/// (`tracking_session_state`) — voir `AppDatabase` (MEZ-101).
///
/// Utilisée à la fois par l'isolate principal (lecture seule, pour
/// l'indicateur de file en attente affiché au convoyeur) et par
/// `TrackingTaskHandler` dans l'isolate du service premier plan (lecture
/// ET écriture) — chacun avec sa propre connexion `AppDatabase` vers le
/// même fichier sqlite (voir sa docstring).
abstract class TrackingLocalDataSource {
  /// Enfile une position — `synced: true` juste après un envoi réussi
  /// (trace locale), `false` si elle vient d'être mise en file (hors
  /// ligne ou lot pas encore tenté).
  Future<void> enqueue(TrackingPosition position, {required bool synced});

  /// Toutes les positions `sync_status = pending` du départ courant,
  /// triées par séquence — voir `TrackingTaskHandler._flush`.
  Future<List<TrackingPosition>> listPending(String departureUuid);

  Future<int> totalPendingCount();

  /// Marque une position comme synchronisée après un envoi réussi ou un
  /// rejet définitif du serveur (voir docstring `TrackingRemoteDataSource.
  /// sendBatch` — un point rejeté reste localement pour trace mais ne
  /// doit plus être réessayé). Jamais d'exception si [positionUuid] est
  /// inconnu localement.
  Future<void> markSynced(String positionUuid);

  /// Purge les positions déjà synchronisées de plus de [olderThan] —
  /// appelée périodiquement par `TrackingTaskHandler` pour ne pas laisser
  /// la file grossir indéfiniment sur un terminal qui reste des semaines
  /// sans réinstallation.
  Future<void> pruneSynced({required Duration olderThan});

  /// État du suivi en cours, `null` si aucun suivi n'a jamais démarré ou
  /// après [clearSession]. Voir `TrackingSessionSnapshot`.
  Future<TrackingSessionSnapshot?> readSession();

  Future<void> saveSession(TrackingSessionSnapshot session);

  /// Prochaine valeur de séquence pour la session en cours (incrémente et
  /// persiste atomiquement) — jamais recalculée depuis la file, qui peut
  /// avoir des lignes déjà purgées (voir [pruneSynced]).
  Future<int> nextSequence();

  Future<void> clearSession();
}

class TrackingLocalDataSourceImpl implements TrackingLocalDataSource {
  TrackingLocalDataSourceImpl(this._database);

  final AppDatabase _database;

  @override
  Future<void> enqueue(TrackingPosition position, {required bool synced}) async {
    final Database db = await _database.database;
    await db.insert(
      'tracking_positions_queue',
      <String, Object?>{
        'position_uuid': position.positionUuid,
        'departure_uuid': position.departureUuid,
        'sequence': position.sequence,
        'status': position.status.value,
        'latitude': position.latitude,
        'longitude': position.longitude,
        'accuracy_meters': position.accuracyMeters,
        'speed_kmh': position.speedKmh,
        'heading_degrees': position.headingDegrees,
        'source': position.source.name,
        'recorded_at': position.recordedAt.toIso8601String(),
        'sync_status': synced ? 'synced' : 'pending',
        'created_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  @override
  Future<List<TrackingPosition>> listPending(String departureUuid) async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'tracking_positions_queue',
      where: 'departure_uuid = ? AND sync_status = ?',
      whereArgs: <Object?>[departureUuid, 'pending'],
      orderBy: 'sequence ASC',
    );
    return rows.map(_positionOf).toList();
  }

  @override
  Future<int> totalPendingCount() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM tracking_positions_queue WHERE sync_status = ?',
      <Object?>['pending'],
    );
    return Sqflite.firstIntValue(rows) ?? 0;
  }

  @override
  Future<void> markSynced(String positionUuid) async {
    final Database db = await _database.database;
    await db.update(
      'tracking_positions_queue',
      <String, Object?>{'sync_status': 'synced'},
      where: 'position_uuid = ?',
      whereArgs: <Object?>[positionUuid],
    );
  }

  @override
  Future<void> pruneSynced({required Duration olderThan}) async {
    final Database db = await _database.database;
    final String threshold = DateTime.now().subtract(olderThan).toIso8601String();
    await db.delete(
      'tracking_positions_queue',
      where: 'sync_status = ? AND created_at < ?',
      whereArgs: <Object?>['synced', threshold],
    );
  }

  @override
  Future<TrackingSessionSnapshot?> readSession() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows =
        await db.query('tracking_session_state', where: 'id = 1', limit: 1);
    if (rows.isEmpty) return null;

    final Map<String, Object?> row = rows.first;
    return TrackingSessionSnapshot(
      departureUuid: row['departure_uuid'] as String,
      terminalSerial: row['terminal_serial'] as String,
      isActive: (row['status'] as String?) == 'active',
      lastSequence: row['last_sequence'] as int? ?? 0,
      startedAt: DateTime.tryParse(row['started_at'] as String? ?? '') ??
          DateTime.now(),
    );
  }

  @override
  Future<void> saveSession(TrackingSessionSnapshot session) async {
    final Database db = await _database.database;
    await db.insert(
      'tracking_session_state',
      <String, Object?>{
        'id': 1,
        'departure_uuid': session.departureUuid,
        'terminal_serial': session.terminalSerial,
        'status': session.isActive ? 'active' : 'stopped',
        'last_sequence': session.lastSequence,
        'started_at': session.startedAt.toIso8601String(),
        'updated_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  @override
  Future<int> nextSequence() async {
    final Database db = await _database.database;

    // PAS de `db.transaction()` ici (corrigé le 24/09/2026) : cet isolate
    // (le service premier plan, voir `TrackingTaskHandler`) a sa PROPRE
    // connexion vers le même fichier sqlite que le reste de l'app — une
    // transaction explicite ouverte ici entrait en conflit avec celles
    // de l'autre connexion (isolate principal), provoquant
    // `SqfliteDatabaseException: no current transaction / COMMIT`. Pas
    // besoin de transaction pour la cohérence : cette méthode n'est
    // jamais appelée deux fois en parallèle DANS ce même isolate (toujours
    // `await`ée séquentiellement, un point GPS à la fois) — Dart étant
    // mono-thread par isolate, lecture puis écriture simples suffisent.
    final List<Map<String, Object?>> rows =
        await db.query('tracking_session_state', where: 'id = 1', limit: 1);
    final int current =
        rows.isEmpty ? 0 : (rows.first['last_sequence'] as int? ?? 0);
    final int next = current + 1;

    if (rows.isNotEmpty) {
      await db.update(
        'tracking_session_state',
        <String, Object?>{
          'last_sequence': next,
          'updated_at': DateTime.now().toIso8601String(),
        },
        where: 'id = 1',
      );
    }

    return next;
  }

  @override
  Future<void> clearSession() async {
    final Database db = await _database.database;
    await db.delete('tracking_session_state', where: 'id = 1');
  }

  TrackingPosition _positionOf(Map<String, Object?> row) => TrackingPosition(
        positionUuid: row['position_uuid'] as String,
        departureUuid: row['departure_uuid'] as String,
        sequence: row['sequence'] as int,
        status: TrackingPositionStatus.fromValue(row['status'] as String?),
        source: TrackingPositionSource.fromValue(row['source'] as String?),
        recordedAt: DateTime.tryParse(row['recorded_at'] as String? ?? '') ??
            DateTime.now(),
        latitude: row['latitude'] as double?,
        longitude: row['longitude'] as double?,
        accuracyMeters: row['accuracy_meters'] as double?,
        speedKmh: row['speed_kmh'] as double?,
        headingDegrees: row['heading_degrees'] as double?,
        pendingSync: true,
      );
}
