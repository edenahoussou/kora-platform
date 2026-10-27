import 'dart:convert';
import 'dart:io';

import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import '../../../../core/database/app_database.dart';
import '../../domain/entities/trip_log.dart';
import '../models/trip_log_dtos.dart';

/// Opération du carnet de bord en attente d'envoi (saisie hors ligne).
class PendingTripLogOperation {
  const PendingTripLogOperation({
    required this.operationUuid,
    required this.departureUuid,
    required this.type,
    required this.payload,
    required this.createdAt,
    this.photoPath,
  });

  final String operationUuid;
  final String departureUuid;

  /// `odometer` ou `expense`.
  final String type;
  final Map<String, dynamic> payload;
  final String? photoPath;
  final DateTime createdAt;

  bool get isOdometer => type == 'odometer';
}

/// Couche DATA : file hors ligne et cache du carnet de bord (sqlite, tables
/// `trip_log_operations` et `trip_log_cache` — voir `AppDatabase` v10).
///
/// Les photos de ticket sont copiées dans un dossier de l'app
/// (`trip_photos/`, à côté de la base) : le fichier temporaire rendu par
/// l'appareil photo peut disparaître avant un envoi différé.
abstract class TripLogLocalDataSource {
  Future<Map<String, dynamic>?> readCache(String departureUuid);
  Future<void> writeCache(String departureUuid, Map<String, dynamic> json);

  /// Relevé compteur hors ligne : remplace un relevé du même type encore
  /// en attente pour ce départ (seule la dernière valeur compte).
  Future<void> enqueueOdometer({
    required String operationUuid,
    required String departureUuid,
    required OdometerKind kind,
    required int valueKm,
    required DateTime recordedAt,
  });

  Future<void> enqueueExpense(TripExpenseDraft draft);

  Future<List<PendingTripLogOperation>> listPending({String? departureUuid});
  Future<int> totalPendingCount();
  Future<void> markSynced(String operationUuid);

  /// Refus définitif du serveur (validation) : sort de la file, l'erreur
  /// est conservée pour diagnostic.
  Future<void> markFailed(String operationUuid, String error);

  /// Copie [sourcePath] dans le stockage de l'app ; renvoie le nouveau
  /// chemin.
  Future<String> persistPhoto(String sourcePath, String clientUuid);
  Future<void> deletePhoto(String? path);
}

class TripLogLocalDataSourceImpl implements TripLogLocalDataSource {
  TripLogLocalDataSourceImpl(this._database);

  final AppDatabase _database;

  @override
  Future<Map<String, dynamic>?> readCache(String departureUuid) async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'trip_log_cache',
      where: 'departure_uuid = ?',
      whereArgs: <Object?>[departureUuid],
      limit: 1,
    );
    if (rows.isEmpty) return null;
    try {
      final Object? decoded = jsonDecode(rows.first['payload'] as String);
      return decoded is Map ? Map<String, dynamic>.from(decoded) : null;
    } on FormatException {
      return null;
    }
  }

  @override
  Future<void> writeCache(
      String departureUuid, Map<String, dynamic> json) async {
    final Database db = await _database.database;
    await db.insert(
      'trip_log_cache',
      <String, Object?>{
        'departure_uuid': departureUuid,
        'payload': jsonEncode(json),
        'fetched_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  @override
  Future<void> enqueueOdometer({
    required String operationUuid,
    required String departureUuid,
    required OdometerKind kind,
    required int valueKm,
    required DateTime recordedAt,
  }) async {
    final Database db = await _database.database;
    await db.transaction((Transaction txn) async {
      final List<Map<String, Object?>> pending = await txn.query(
        'trip_log_operations',
        where: 'departure_uuid = ? AND op_type = ? AND sync_status = ?',
        whereArgs: <Object?>[departureUuid, 'odometer', 'pending'],
      );
      for (final Map<String, Object?> row in pending) {
        final Map<String, dynamic> payload = _decode(row['payload']);
        if (payload['kind'] == kind.value) {
          await txn.delete(
            'trip_log_operations',
            where: 'operation_uuid = ?',
            whereArgs: <Object?>[row['operation_uuid']],
          );
        }
      }
      await txn.insert(
        'trip_log_operations',
        <String, Object?>{
          'operation_uuid': operationUuid,
          'departure_uuid': departureUuid,
          'op_type': 'odometer',
          'payload': jsonEncode(<String, dynamic>{
            'kind': kind.value,
            'value_km': valueKm,
            'recorded_at': recordedAt.toIso8601String(),
          }),
          'photo_path': null,
          'sync_status': 'pending',
          'error': null,
          'created_at': DateTime.now().toIso8601String(),
        },
        conflictAlgorithm: ConflictAlgorithm.replace,
      );
    });
  }

  @override
  Future<void> enqueueExpense(TripExpenseDraft draft) async {
    final Database db = await _database.database;
    await db.insert(
      'trip_log_operations',
      <String, Object?>{
        'operation_uuid': draft.clientUuid,
        'departure_uuid': draft.departureUuid,
        'op_type': 'expense',
        'payload': jsonEncode(TripLogDtos.draftToJson(draft)),
        'photo_path': draft.photoPath,
        'sync_status': 'pending',
        'error': null,
        'created_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  @override
  Future<List<PendingTripLogOperation>> listPending(
      {String? departureUuid}) async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'trip_log_operations',
      where: departureUuid == null
          ? 'sync_status = ?'
          : 'sync_status = ? AND departure_uuid = ?',
      whereArgs: departureUuid == null
          ? <Object?>['pending']
          : <Object?>['pending', departureUuid],
      orderBy: 'created_at ASC',
    );
    return rows
        .map((Map<String, Object?> row) => PendingTripLogOperation(
              operationUuid: row['operation_uuid'] as String,
              departureUuid: row['departure_uuid'] as String,
              type: row['op_type'] as String,
              payload: _decode(row['payload']),
              photoPath: row['photo_path'] as String?,
              createdAt:
                  DateTime.tryParse(row['created_at'] as String? ?? '') ??
                      DateTime.now(),
            ))
        .toList();
  }

  @override
  Future<int> totalPendingCount() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM trip_log_operations WHERE sync_status = ?',
      <Object?>['pending'],
    );
    return Sqflite.firstIntValue(rows) ?? 0;
  }

  @override
  Future<void> markSynced(String operationUuid) async {
    final Database db = await _database.database;
    await db.update(
      'trip_log_operations',
      <String, Object?>{'sync_status': 'synced', 'error': null},
      where: 'operation_uuid = ?',
      whereArgs: <Object?>[operationUuid],
    );
  }

  @override
  Future<void> markFailed(String operationUuid, String error) async {
    final Database db = await _database.database;
    await db.update(
      'trip_log_operations',
      <String, Object?>{'sync_status': 'failed', 'error': error},
      where: 'operation_uuid = ?',
      whereArgs: <Object?>[operationUuid],
    );
  }

  @override
  Future<String> persistPhoto(String sourcePath, String clientUuid) async {
    final String directory =
        p.join(await getDatabasesPath(), 'trip_photos');
    await Directory(directory).create(recursive: true);
    final String extension = p.extension(sourcePath).isEmpty
        ? '.jpg'
        : p.extension(sourcePath).toLowerCase();
    final String target = p.join(directory, '$clientUuid$extension');
    if (p.equals(sourcePath, target)) return target;
    await File(sourcePath).copy(target);
    return target;
  }

  @override
  Future<void> deletePhoto(String? path) async {
    if (path == null) return;
    try {
      final File file = File(path);
      if (await file.exists()) await file.delete();
    } on FileSystemException {
      // Best-effort : un fichier orphelin n'empêche rien.
    }
  }

  Map<String, dynamic> _decode(Object? raw) {
    if (raw is! String) return <String, dynamic>{};
    try {
      final Object? decoded = jsonDecode(raw);
      return decoded is Map
          ? Map<String, dynamic>.from(decoded)
          : <String, dynamic>{};
    } on FormatException {
      return <String, dynamic>{};
    }
  }
}
