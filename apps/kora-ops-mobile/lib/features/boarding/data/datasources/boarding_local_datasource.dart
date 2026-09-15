import 'package:sqflite/sqflite.dart';

import '../../../../core/database/app_database.dart';
import '../../domain/entities/boarding_outcome.dart';
import '../../domain/entities/boarding_outcome_status.dart';
import '../../domain/entities/pending_boarding_operation.dart';

/// Couche DATA : anti-double-scan local et file d'embarquements en
/// attente de synchronisation (sqlite, table `boarding_operations` — voir
/// `AppDatabase`).
abstract class BoardingLocalDataSource {
  /// `null` si ce billet (même [qrPayload]/[shortCode] pour
  /// [departureUuid]) n'a jamais été scanné sur ce terminal, ou si sa
  /// seule trace est un échec technique (non conclusif — voir
  /// `BoardingOutcomeStatus.isConclusive`).
  Future<BoardingOutcome?> findConclusiveOutcome({
    required String departureUuid,
    String? qrPayload,
    String? shortCode,
  });

  /// N'enregistre jamais un résultat non conclusif (`error`, voir
  /// `BoardingOutcomeStatus.isConclusive`) — un échec technique doit
  /// rester re-scannable, jamais bloqué localement.
  Future<void> recordOutcome({
    required String operationUuid,
    required String departureUuid,
    String? qrPayload,
    String? shortCode,
    required BoardingOutcome outcome,
    required DateTime localRecordedAt,
    required bool synced,
  });

  Future<int> pendingCount(String departureUuid);

  /// Toutes les opérations `sync_status = pending`, tous départs
  /// confondus (MEZ-88) — voir `SyncRepository.syncNow`.
  Future<List<PendingBoardingOperation>> listPending();

  /// Nombre total d'opérations `sync_status = pending`, tous départs
  /// confondus — affiché sur `SyncStatusBanner`/l'onglet Synchro.
  Future<int> totalPendingCount();

  /// Marque une opération comme synchronisée avec le résultat renvoyé par
  /// le lot (voir `SyncBatchResource.operations[].result`) — jamais
  /// d'exception si [operationUuid] est inconnu localement (lot rejoué
  /// après une réinstallation, par ex.) : silencieux.
  Future<void> markSynced({
    required String operationUuid,
    required BoardingOutcome outcome,
    required DateTime serverRecordedAt,
  });
}

class BoardingLocalDataSourceImpl implements BoardingLocalDataSource {
  BoardingLocalDataSourceImpl(this._database);

  final AppDatabase _database;

  @override
  Future<BoardingOutcome?> findConclusiveOutcome({
    required String departureUuid,
    String? qrPayload,
    String? shortCode,
  }) async {
    final Database db = await _database.database;

    final String column = qrPayload != null ? 'qr_payload' : 'short_code';
    final String value = qrPayload ?? shortCode ?? '';
    if (value.isEmpty) return null;

    final List<Map<String, Object?>> rows = await db.query(
      'boarding_operations',
      where: 'departure_uuid = ? AND $column = ?',
      whereArgs: <Object?>[departureUuid, value],
      orderBy: 'created_at DESC',
      limit: 1,
    );
    if (rows.isEmpty) return null;

    return _outcomeOf(rows.first);
  }

  @override
  Future<void> recordOutcome({
    required String operationUuid,
    required String departureUuid,
    String? qrPayload,
    String? shortCode,
    required BoardingOutcome outcome,
    required DateTime localRecordedAt,
    required bool synced,
  }) async {
    if (!outcome.status.isConclusive) return;

    final Database db = await _database.database;
    await db.insert(
      'boarding_operations',
      <String, Object?>{
        'operation_uuid': operationUuid,
        'departure_uuid': departureUuid,
        'qr_payload': qrPayload,
        'short_code': shortCode,
        'ticket_uuid': outcome.ticketUuid,
        'local_recorded_at': localRecordedAt.toIso8601String(),
        'server_recorded_at': synced ? DateTime.now().toIso8601String() : null,
        'sync_status': synced ? 'synced' : 'pending',
        'result_status': outcome.status.name,
        'result_message': outcome.message,
        'created_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  @override
  Future<int> pendingCount(String departureUuid) async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM boarding_operations '
      'WHERE departure_uuid = ? AND sync_status = ?',
      <Object?>[departureUuid, 'pending'],
    );
    return Sqflite.firstIntValue(rows) ?? 0;
  }

  @override
  Future<List<PendingBoardingOperation>> listPending() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'boarding_operations',
      where: 'sync_status = ?',
      whereArgs: <Object?>['pending'],
      orderBy: 'created_at ASC',
      limit: 200, // plafond serveur (voir SubmitSyncBatchRequest, max:200)
    );

    return rows
        .map((row) => PendingBoardingOperation(
              operationUuid: row['operation_uuid'] as String,
              departureUuid: row['departure_uuid'] as String,
              qrPayload: row['qr_payload'] as String?,
              shortCode: row['short_code'] as String?,
              localRecordedAt:
                  DateTime.parse(row['local_recorded_at'] as String),
            ))
        .toList();
  }

  @override
  Future<int> totalPendingCount() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM boarding_operations WHERE sync_status = ?',
      <Object?>['pending'],
    );
    return Sqflite.firstIntValue(rows) ?? 0;
  }

  @override
  Future<void> markSynced({
    required String operationUuid,
    required BoardingOutcome outcome,
    required DateTime serverRecordedAt,
  }) async {
    final Database db = await _database.database;
    await db.update(
      'boarding_operations',
      <String, Object?>{
        'sync_status': 'synced',
        'result_status': outcome.status.name,
        'result_message': outcome.message,
        'server_recorded_at': serverRecordedAt.toIso8601String(),
        if (outcome.ticketUuid != null) 'ticket_uuid': outcome.ticketUuid,
      },
      where: 'operation_uuid = ?',
      whereArgs: <Object?>[operationUuid],
    );
  }

  BoardingOutcome _outcomeOf(Map<String, Object?> row) {
    final String resultStatus = row['result_status'] as String;
    return BoardingOutcome(
      status: BoardingOutcomeStatus.values.firstWhere(
        (BoardingOutcomeStatus s) => s.name == resultStatus,
        orElse: () => BoardingOutcomeStatus.error,
      ),
      message: row['result_message'] as String? ?? '',
      ticketUuid: row['ticket_uuid'] as String?,
      operationUuid: row['operation_uuid'] as String?,
    );
  }
}
