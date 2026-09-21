import 'package:sqflite/sqflite.dart';

import '../../../../core/database/app_database.dart';
import '../../domain/entities/border_manifest_print_job.dart';

/// Accès sqlite (`border_manifest_print_jobs`) aux demandes d'impression du
/// manifeste frontière. Aucune logique métier ici : la machine à états vit
/// dans `BorderManifestPrintRepositoryImpl`.
abstract class BorderManifestPrintLocalDataSource {
  Future<void> insert(BorderManifestPrintJob job);

  Future<void> update(BorderManifestPrintJob job);

  Future<BorderManifestPrintJob?> find(String jobUuid);

  /// Job le plus récent dont le statut n'est pas `completed`.
  Future<BorderManifestPrintJob?> findUnfinished(String departureUuid);

  /// Job le plus récent, tous statuts confondus.
  Future<BorderManifestPrintJob?> findLast(String departureUuid);
}

class BorderManifestPrintLocalDataSourceImpl
    implements BorderManifestPrintLocalDataSource {
  BorderManifestPrintLocalDataSourceImpl(this._database);

  final AppDatabase _database;

  static const String _table = 'border_manifest_print_jobs';

  @override
  Future<void> insert(BorderManifestPrintJob job) async {
    final Database db = await _database.database;
    await db.insert(_table, _toRow(job));
  }

  @override
  Future<void> update(BorderManifestPrintJob job) async {
    final Database db = await _database.database;
    await db.update(
      _table,
      _toRow(job),
      where: 'job_uuid = ?',
      whereArgs: <Object?>[job.jobUuid],
    );
  }

  @override
  Future<BorderManifestPrintJob?> find(String jobUuid) async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      _table,
      where: 'job_uuid = ?',
      whereArgs: <Object?>[jobUuid],
      limit: 1,
    );
    return rows.isEmpty ? null : _fromRow(rows.first);
  }

  @override
  Future<BorderManifestPrintJob?> findUnfinished(String departureUuid) async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      _table,
      where: 'departure_uuid = ? AND status != ?',
      whereArgs: <Object?>[
        departureUuid,
        BorderManifestPrintJobStatus.completed.name,
      ],
      orderBy: 'created_at DESC',
      limit: 1,
    );
    return rows.isEmpty ? null : _fromRow(rows.first);
  }

  @override
  Future<BorderManifestPrintJob?> findLast(String departureUuid) async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      _table,
      where: 'departure_uuid = ?',
      whereArgs: <Object?>[departureUuid],
      orderBy: 'created_at DESC',
      limit: 1,
    );
    return rows.isEmpty ? null : _fromRow(rows.first);
  }

  Map<String, Object?> _toRow(BorderManifestPrintJob job) => <String, Object?>{
        'job_uuid': job.jobUuid,
        'departure_uuid': job.departureUuid,
        'requested_copies': job.requestedCopies,
        'confirmed_copies': job.confirmedCopies,
        'in_flight_copy': job.inFlightCopy,
        'status': job.status.name,
        'last_error': job.lastError,
        'manifest_version': job.manifestVersion,
        'user_uuid': job.userUuid,
        'terminal_serial': job.terminalSerial,
        'created_at': job.createdAt.toIso8601String(),
        'updated_at': job.updatedAt.toIso8601String(),
      };

  BorderManifestPrintJob _fromRow(Map<String, Object?> row) {
    final DateTime createdAt =
        DateTime.tryParse(row['created_at'] as String? ?? '') ??
            DateTime.fromMillisecondsSinceEpoch(0);

    return BorderManifestPrintJob(
      jobUuid: row['job_uuid']! as String,
      departureUuid: row['departure_uuid']! as String,
      requestedCopies: row['requested_copies']! as int,
      confirmedCopies: row['confirmed_copies'] as int? ?? 0,
      inFlightCopy: row['in_flight_copy'] as int?,
      status: BorderManifestPrintJobStatus.values.firstWhere(
        (BorderManifestPrintJobStatus s) => s.name == row['status'],
        // Statut illisible : le plus prudent est « interrompu » (jamais
        // « terminé », qui masquerait des exemplaires manquants).
        orElse: () => BorderManifestPrintJobStatus.interrupted,
      ),
      lastError: row['last_error'] as String?,
      manifestVersion: row['manifest_version'] as String?,
      userUuid: row['user_uuid'] as String?,
      terminalSerial: row['terminal_serial'] as String?,
      createdAt: createdAt,
      updatedAt:
          DateTime.tryParse(row['updated_at'] as String? ?? '') ?? createdAt,
    );
  }
}
