import 'dart:convert';

import 'package:sqflite/sqflite.dart';

import '../../../../core/database/app_database.dart';

/// Une correction enregistrée hors ligne, en attente d'envoi.
class PendingPassengerCorrection {
  const PendingPassengerCorrection({
    required this.operationUuid,
    required this.departureUuid,
    required this.ticketUuid,
    required this.changes,
    this.reason,
  });

  final String operationUuid;
  final String departureUuid;
  final String ticketUuid;
  final Map<String, Object?> changes;
  final String? reason;
}

abstract class PassengerCorrectionLocalDataSource {
  Future<void> enqueue({
    required String operationUuid,
    required String departureUuid,
    required String ticketUuid,
    required Map<String, Object?> changes,
    String? reason,
  });

  /// Corrections `pending` du départ, de la plus ancienne à la plus récente.
  Future<List<PendingPassengerCorrection>> pending(String departureUuid);

  /// Envoyée et acceptée : retirée de la file.
  Future<void> remove(String operationUuid);

  /// Refusée définitivement : conservée pour trace, plus jamais rejouée.
  Future<void> markRejected(String operationUuid, String error);
}

class PassengerCorrectionLocalDataSourceImpl
    implements PassengerCorrectionLocalDataSource {
  PassengerCorrectionLocalDataSourceImpl(this._database);

  final AppDatabase _database;

  @override
  Future<void> enqueue({
    required String operationUuid,
    required String departureUuid,
    required String ticketUuid,
    required Map<String, Object?> changes,
    String? reason,
  }) async {
    final Database db = await _database.database;
    await db.insert(
      'passenger_correction_operations',
      <String, Object?>{
        'operation_uuid': operationUuid,
        'departure_uuid': departureUuid,
        'ticket_uuid': ticketUuid,
        'payload': jsonEncode(changes),
        'reason': reason,
        'sync_status': 'pending',
        'created_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  @override
  Future<List<PendingPassengerCorrection>> pending(String departureUuid) async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'passenger_correction_operations',
      where: 'departure_uuid = ? AND sync_status = ?',
      whereArgs: <Object?>[departureUuid, 'pending'],
      orderBy: 'created_at ASC',
    );

    return rows.map((Map<String, Object?> row) {
      final Object? decoded = jsonDecode(row['payload'] as String? ?? '{}');
      return PendingPassengerCorrection(
        operationUuid: row['operation_uuid'] as String,
        departureUuid: row['departure_uuid'] as String,
        ticketUuid: row['ticket_uuid'] as String,
        changes: decoded is Map
            ? Map<String, Object?>.from(decoded)
            : <String, Object?>{},
        reason: row['reason'] as String?,
      );
    }).toList();
  }

  @override
  Future<void> remove(String operationUuid) async {
    final Database db = await _database.database;
    await db.delete(
      'passenger_correction_operations',
      where: 'operation_uuid = ?',
      whereArgs: <Object?>[operationUuid],
    );
  }

  @override
  Future<void> markRejected(String operationUuid, String error) async {
    final Database db = await _database.database;
    await db.update(
      'passenger_correction_operations',
      <String, Object?>{'sync_status': 'rejected', 'last_error': error},
      where: 'operation_uuid = ?',
      whereArgs: <Object?>[operationUuid],
    );
  }
}
