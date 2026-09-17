import 'package:sqflite/sqflite.dart';

import '../../../../core/database/app_database.dart';
import '../../domain/entities/pending_seat_release_operation.dart';
import '../../domain/entities/seat_release_outcome.dart';
import '../../domain/entities/seat_release_outcome_status.dart';

/// Couche DATA : anti-doublon local et file de libérations de siège en
/// attente de synchronisation (sqlite, table `seat_release_operations` —
/// voir `AppDatabase`).
abstract class SeatReleaseLocalDataSource {
  /// `null` si ce billet n'a jamais eu de tentative de ce [type] pour ce
  /// départ sur ce terminal, ou si sa seule trace est un échec technique
  /// (non conclusif — voir `SeatReleaseOutcomeStatus.isConclusive`).
  Future<SeatReleaseOutcome?> findConclusiveOutcome({
    required String departureUuid,
    required String ticketUuid,
    required SeatReleaseType type,
  });

  /// N'enregistre jamais un résultat non conclusif (`error`) — un échec
  /// technique doit rester re-tentable, jamais bloqué localement.
  Future<void> recordOutcome({
    required String operationUuid,
    required SeatReleaseType type,
    required String departureUuid,
    required String ticketUuid,
    String? actualStopUuid,
    required SeatReleaseOutcome outcome,
    required DateTime localRecordedAt,
    required bool synced,
  });

  Future<int> totalPendingCount();

  Future<List<PendingSeatReleaseOperation>> listPending();

  Future<void> markSynced({
    required String operationUuid,
    required SeatReleaseOutcome outcome,
    required DateTime serverRecordedAt,
  });
}

class SeatReleaseLocalDataSourceImpl implements SeatReleaseLocalDataSource {
  SeatReleaseLocalDataSourceImpl(this._database);

  final AppDatabase _database;

  @override
  Future<SeatReleaseOutcome?> findConclusiveOutcome({
    required String departureUuid,
    required String ticketUuid,
    required SeatReleaseType type,
  }) async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'seat_release_operations',
      where: 'departure_uuid = ? AND ticket_uuid = ? AND operation_type = ?',
      whereArgs: <Object?>[departureUuid, ticketUuid, type.syncOperationType],
      orderBy: 'created_at DESC',
      limit: 1,
    );
    if (rows.isEmpty) return null;
    return _outcomeOf(rows.first);
  }

  @override
  Future<void> recordOutcome({
    required String operationUuid,
    required SeatReleaseType type,
    required String departureUuid,
    required String ticketUuid,
    String? actualStopUuid,
    required SeatReleaseOutcome outcome,
    required DateTime localRecordedAt,
    required bool synced,
  }) async {
    if (!outcome.status.isConclusive) return;

    final Database db = await _database.database;
    await db.insert(
      'seat_release_operations',
      <String, Object?>{
        'operation_uuid': operationUuid,
        'departure_uuid': departureUuid,
        'operation_type': type.syncOperationType,
        'ticket_uuid': ticketUuid,
        'actual_stop_uuid': actualStopUuid,
        'local_recorded_at': localRecordedAt.toIso8601String(),
        'server_recorded_at':
            synced ? DateTime.now().toIso8601String() : null,
        'sync_status': synced ? 'synced' : 'pending',
        'result_status': outcome.status.name,
        'result_message': outcome.message,
        'recommended_action': outcome.recommendedAction,
        'created_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  @override
  Future<int> totalPendingCount() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM seat_release_operations WHERE sync_status = ?',
      <Object?>['pending'],
    );
    return Sqflite.firstIntValue(rows) ?? 0;
  }

  @override
  Future<List<PendingSeatReleaseOperation>> listPending() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'seat_release_operations',
      where: 'sync_status = ?',
      whereArgs: <Object?>['pending'],
      orderBy: 'created_at ASC',
      limit: 200,
    );

    return rows
        .map((row) => PendingSeatReleaseOperation(
              operationUuid: row['operation_uuid'] as String,
              type: (row['operation_type'] as String) == 'boarding.no_show'
                  ? SeatReleaseType.noShow
                  : SeatReleaseType.earlyDropOff,
              departureUuid: row['departure_uuid'] as String,
              ticketUuid: row['ticket_uuid'] as String,
              actualStopUuid: row['actual_stop_uuid'] as String?,
              localRecordedAt:
                  DateTime.parse(row['local_recorded_at'] as String),
            ))
        .toList();
  }

  @override
  Future<void> markSynced({
    required String operationUuid,
    required SeatReleaseOutcome outcome,
    required DateTime serverRecordedAt,
  }) async {
    final Database db = await _database.database;
    await db.update(
      'seat_release_operations',
      <String, Object?>{
        'sync_status': 'synced',
        'result_status': outcome.status.name,
        'result_message': outcome.message,
        'recommended_action': outcome.recommendedAction,
        'server_recorded_at': serverRecordedAt.toIso8601String(),
      },
      where: 'operation_uuid = ?',
      whereArgs: <Object?>[operationUuid],
    );
  }

  SeatReleaseOutcome _outcomeOf(Map<String, Object?> row) {
    final String resultStatus = row['result_status'] as String;
    return SeatReleaseOutcome(
      status: SeatReleaseOutcomeStatus.values.firstWhere(
        (SeatReleaseOutcomeStatus s) => s.name == resultStatus,
        orElse: () => SeatReleaseOutcomeStatus.error,
      ),
      message: row['result_message'] as String? ?? '',
      recommendedAction: row['recommended_action'] as String?,
      ticketUuid: row['ticket_uuid'] as String?,
      operationUuid: row['operation_uuid'] as String?,
    );
  }
}
