import 'package:sqflite/sqflite.dart';

import '../../../../core/database/app_database.dart';
import '../../domain/entities/incident.dart';

/// Couche DATA : file d'incidents en attente de synchronisation (sqlite,
/// table `incident_operations` — voir `AppDatabase`). Contrairement à
/// `boarding_operations`, pas d'anti-doublon métier à vérifier avant
/// l'envoi : chaque signalement est un événement distinct (deux pannes le
/// même jour sur le même départ sont deux incidents), la seule
/// idempotence nécessaire est celle du rejeu réseau (`operationUuid`).
abstract class IncidentLocalDataSource {
  /// Enregistre l'incident localement — `synced: true` juste après un
  /// envoi réussi (trace locale), `false` s'il vient d'être mis en file
  /// hors ligne.
  Future<void> record(Incident incident, {required bool synced});

  /// Tous les incidents `sync_status = pending`, tous départs confondus
  /// — voir `IncidentRepository.retryPending`.
  Future<List<Incident>> listPending();

  /// Nombre total d'incidents `sync_status = pending`.
  Future<int> totalPendingCount();

  /// Marque un incident comme synchronisé après un rejeu réussi. Jamais
  /// d'exception si [operationUuid] est inconnu localement : silencieux.
  Future<void> markSynced(String operationUuid);
}

class IncidentLocalDataSourceImpl implements IncidentLocalDataSource {
  IncidentLocalDataSourceImpl(this._database);

  final AppDatabase _database;

  @override
  Future<void> record(Incident incident, {required bool synced}) async {
    final Database db = await _database.database;
    await db.insert(
      'incident_operations',
      <String, Object?>{
        'operation_uuid': incident.operationUuid,
        'departure_uuid': incident.departureUuid,
        'type': incident.type.name,
        'description': incident.description,
        'latitude': incident.latitude,
        'longitude': incident.longitude,
        'occurred_at': incident.occurredAt.toIso8601String(),
        'sync_status': synced ? 'synced' : 'pending',
        'created_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  @override
  Future<List<Incident>> listPending() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'incident_operations',
      where: 'sync_status = ?',
      whereArgs: <Object?>['pending'],
      orderBy: 'created_at ASC',
    );
    return rows.map(_incidentOf).toList();
  }

  @override
  Future<int> totalPendingCount() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM incident_operations WHERE sync_status = ?',
      <Object?>['pending'],
    );
    return Sqflite.firstIntValue(rows) ?? 0;
  }

  @override
  Future<void> markSynced(String operationUuid) async {
    final Database db = await _database.database;
    await db.update(
      'incident_operations',
      <String, Object?>{'sync_status': 'synced'},
      where: 'operation_uuid = ?',
      whereArgs: <Object?>[operationUuid],
    );
  }

  Incident _incidentOf(Map<String, Object?> row) => Incident(
        operationUuid: row['operation_uuid'] as String,
        departureUuid: row['departure_uuid'] as String,
        type: IncidentType.fromValue(row['type'] as String?),
        description: row['description'] as String? ?? '',
        occurredAt: DateTime.tryParse(row['occurred_at'] as String? ?? '') ??
            DateTime.now(),
        latitude: row['latitude'] as double?,
        longitude: row['longitude'] as double?,
        pendingSync: (row['sync_status'] as String?) == 'pending',
      );
}
