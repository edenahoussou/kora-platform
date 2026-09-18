import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:sqflite/sqflite.dart';

import '../../../../core/constants/storage_keys.dart';
import '../../../../core/database/app_database.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/entities/terminal.dart';
import '../../domain/entities/terminal_activity_type.dart';
import '../../domain/entities/terminal_status.dart';

/// Couche DATA : persistance locale du terminal (stockage **sécurisé** —
/// même raison que `AuthLocalDataSource`, ce n'est pas un secret mais ça
/// identifie l'appareil de façon stable) et journal d'activité (sqlite —
/// voir `AppDatabase`).
abstract class TerminalLocalDataSource {
  Future<String?> readSerialNumber();
  Future<Terminal?> readSnapshot();
  Future<void> saveTerminal(Terminal terminal);
  Future<void> clear();

  /// Journalise localement une activité **avant** la tentative réseau — un
  /// crash pendant l'envoi ne doit pas faire disparaître l'entrée. Marquée
  /// synchronisée après succès réseau (voir `TerminalRepositoryImpl.
  /// logActivity`) ; sinon reste disponible pour un futur rattrapage
  /// (étape Synchro).
  Future<int> recordActivity(
    TerminalActivityType type, {
    Map<String, dynamic>? context,
    required DateTime localRecordedAt,
  });
  Future<void> markActivitySynced(int id);
}

class TerminalLocalDataSourceImpl implements TerminalLocalDataSource {
  TerminalLocalDataSourceImpl(this._storage, this._database);

  final FlutterSecureStorage _storage;
  final AppDatabase _database;

  @override
  Future<String?> readSerialNumber() =>
      _storage.read(key: StorageKeys.terminalSerialNumber);

  @override
  Future<Terminal?> readSnapshot() async {
    try {
      final String? raw =
          await _storage.read(key: StorageKeys.terminalSnapshot);
      if (raw == null || raw.isEmpty) return null;

      final Map<String, dynamic> json = jsonDecode(raw) as Map<String, dynamic>;
      return _fromJson(json);
    } on Object catch (error) {
      throw CacheException('Terminal local illisible : $error');
    }
  }

  @override
  Future<void> saveTerminal(Terminal terminal) async {
    try {
      await _storage.write(
        key: StorageKeys.terminalSerialNumber,
        value: terminal.serialNumber,
      );
      await _storage.write(
        key: StorageKeys.terminalSnapshot,
        value: jsonEncode(_toJson(terminal)),
      );
    } on Object catch (error) {
      throw CacheException('Impossible d’enregistrer le terminal : $error');
    }
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: StorageKeys.terminalSerialNumber);
    await _storage.delete(key: StorageKeys.terminalSnapshot);
  }

  @override
  Future<int> recordActivity(
    TerminalActivityType type, {
    Map<String, dynamic>? context,
    required DateTime localRecordedAt,
  }) async {
    final Database db = await _database.database;
    return db.insert('terminal_activity_log', <String, Object?>{
      'type': type.value,
      'context': context == null ? null : jsonEncode(context),
      'local_recorded_at': localRecordedAt.toIso8601String(),
      'synced': 0,
    });
  }

  @override
  Future<void> markActivitySynced(int id) async {
    final Database db = await _database.database;
    await db.update(
      'terminal_activity_log',
      <String, Object?>{'synced': 1},
      where: 'id = ?',
      whereArgs: <Object?>[id],
    );
  }

  Map<String, dynamic> _toJson(Terminal terminal) => <String, dynamic>{
        'uuid': terminal.uuid,
        'serial_number': terminal.serialNumber,
        'status': terminal.status.value,
        'agency_name': terminal.agencyName,
        'assigned_user_name': terminal.assignedUserName,
        'last_handshake_at': terminal.lastHandshakeAt?.toIso8601String(),
        'last_sync_at': terminal.lastSyncAt?.toIso8601String(),
      };

  Terminal _fromJson(Map<String, dynamic> json) => Terminal(
        uuid: json['uuid'] as String? ?? '',
        serialNumber: json['serial_number'] as String? ?? '',
        status: TerminalStatus.fromValue(json['status'] as String?),
        agencyName: json['agency_name'] as String?,
        assignedUserName: json['assigned_user_name'] as String?,
        lastHandshakeAt: json['last_handshake_at'] == null
            ? null
            : DateTime.tryParse(json['last_handshake_at'] as String),
        lastSyncAt: json['last_sync_at'] == null
            ? null
            : DateTime.tryParse(json['last_sync_at'] as String),
      );
}
