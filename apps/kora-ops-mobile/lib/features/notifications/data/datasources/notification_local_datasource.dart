import 'dart:convert';

import 'package:sqflite/sqflite.dart';

import '../../../../core/database/app_database.dart';
import '../../domain/entities/ops_notification.dart';

abstract class NotificationLocalDataSource {
  /// Cache local, les plus récentes d'abord — restaure l'état après un
  /// redémarrage à froid, y compris hors ligne (voir critère MEZ-97
  /// "restaurer les alertes non acquittées après redémarrage du P501").
  Future<List<OpsNotification>> readCache();

  /// Fusionne [notifications] dans le cache (upsert par `uuid` — un rejeu
  /// ne crée jamais de doublon). Un marquage "lu" fait hors ligne et pas
  /// encore synchronisé (`mark_read_pending`) n'est jamais écrasé par un
  /// `is_read=false` renvoyé par le serveur, qui ne le sait pas encore.
  Future<void> upsertAll(List<OpsNotification> notifications);

  /// Marquage optimiste local (avant/sans l'appel réseau) — pose
  /// `mark_read_pending=1`, à réconcilier via [confirmSynced].
  Future<void> markReadLocally(String uuid, DateTime readAt);

  /// Même chose pour toutes les notifications actuellement non lues.
  Future<void> markAllReadLocally(DateTime readAt);

  /// À appeler après un `POST .../read` réussi : lève le drapeau
  /// `mark_read_pending`, le marquage n'a plus besoin d'être réémis.
  Future<void> confirmSynced(String uuid);

  /// `uuid` des notifications marquées lues localement mais jamais
  /// confirmées au serveur (coupure réseau) — à réessayer au retour du
  /// réseau (voir `NotificationRepository.retryPendingReads`).
  Future<List<String>> pendingMarkReadUuids();

  Future<int> unreadCountLocal();
  Future<int> criticalUnreadCountLocal();

  /// Vide le cache — déconnexion sur un terminal partagé.
  Future<void> clearAll();
}

class NotificationLocalDataSourceImpl implements NotificationLocalDataSource {
  NotificationLocalDataSourceImpl(this._database);

  final AppDatabase _database;

  @override
  Future<List<OpsNotification>> readCache() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'ops_notifications',
      orderBy: 'created_at DESC',
    );
    return rows.map(_notificationOf).toList();
  }

  @override
  Future<void> upsertAll(List<OpsNotification> notifications) async {
    if (notifications.isEmpty) return;
    final Database db = await _database.database;

    await db.transaction((txn) async {
      for (final OpsNotification notification in notifications) {
        final List<Map<String, Object?>> existingRows = await txn.query(
          'ops_notifications',
          columns: <String>['mark_read_pending', 'is_read', 'read_at'],
          where: 'uuid = ?',
          whereArgs: <Object?>[notification.uuid],
          limit: 1,
        );
        final bool hasPendingLocalRead = existingRows.isNotEmpty &&
            (existingRows.first['mark_read_pending'] as int? ?? 0) == 1;

        final bool isRead = hasPendingLocalRead ? true : notification.isRead;
        final String? readAt = hasPendingLocalRead
            ? existingRows.first['read_at'] as String?
            : notification.readAt?.toIso8601String();

        await txn.insert(
          'ops_notifications',
          <String, Object?>{
            'uuid': notification.uuid,
            'event_uuid': notification.eventUuid,
            'type': notification.type,
            'severity': notification.severity.value,
            'title': notification.title,
            'message': notification.message,
            'action': notification.action.name,
            'is_read': isRead ? 1 : 0,
            'read_at': readAt,
            'created_at': notification.createdAt.toIso8601String(),
            'occurred_at': notification.occurredAt?.toIso8601String(),
            'departure_uuid': notification.departure?.uuid,
            'departure_line_code': notification.departure?.lineCode,
            'departure_date': notification.departure?.date,
            'departure_time': notification.departure?.time,
            'data': jsonEncode(notification.data),
            'mark_read_pending': hasPendingLocalRead ? 1 : 0,
          },
          conflictAlgorithm: ConflictAlgorithm.replace,
        );
      }
    });
  }

  @override
  Future<void> markReadLocally(String uuid, DateTime readAt) async {
    final Database db = await _database.database;
    await db.update(
      'ops_notifications',
      <String, Object?>{
        'is_read': 1,
        'read_at': readAt.toIso8601String(),
        'mark_read_pending': 1,
      },
      where: 'uuid = ?',
      whereArgs: <Object?>[uuid],
    );
  }

  @override
  Future<void> markAllReadLocally(DateTime readAt) async {
    final Database db = await _database.database;
    await db.update(
      'ops_notifications',
      <String, Object?>{
        'is_read': 1,
        'read_at': readAt.toIso8601String(),
        'mark_read_pending': 1,
      },
      where: 'is_read = 0',
    );
  }

  @override
  Future<void> confirmSynced(String uuid) async {
    final Database db = await _database.database;
    await db.update(
      'ops_notifications',
      <String, Object?>{'mark_read_pending': 0},
      where: 'uuid = ?',
      whereArgs: <Object?>[uuid],
    );
  }

  @override
  Future<List<String>> pendingMarkReadUuids() async {
    final Database db = await _database.database;
    final List<Map<String, Object?>> rows = await db.query(
      'ops_notifications',
      columns: <String>['uuid'],
      where: 'mark_read_pending = 1',
    );
    return rows.map((Map<String, Object?> r) => r['uuid'] as String).toList();
  }

  @override
  Future<int> unreadCountLocal() async {
    final Database db = await _database.database;
    final result = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM ops_notifications WHERE is_read = 0',
    );
    return Sqflite.firstIntValue(result) ?? 0;
  }

  @override
  Future<int> criticalUnreadCountLocal() async {
    final Database db = await _database.database;
    final result = await db.rawQuery(
      'SELECT COUNT(*) AS c FROM ops_notifications '
      "WHERE is_read = 0 AND severity = 'critical'",
    );
    return Sqflite.firstIntValue(result) ?? 0;
  }

  @override
  Future<void> clearAll() async {
    final Database db = await _database.database;
    await db.delete('ops_notifications');
  }

  OpsNotification _notificationOf(Map<String, Object?> row) {
    final String? departureUuid = row['departure_uuid'] as String?;
    final Object? rawData = row['data'];
    Map<String, dynamic> data = const <String, dynamic>{};
    if (rawData is String && rawData.isNotEmpty) {
      final decoded = jsonDecode(rawData);
      if (decoded is Map) data = Map<String, dynamic>.from(decoded);
    }

    return OpsNotification(
      uuid: row['uuid'] as String? ?? '',
      eventUuid: row['event_uuid'] as String?,
      type: row['type'] as String? ?? 'unknown',
      severity: OpsNotificationSeverityX.fromValue(row['severity'] as String?),
      title: row['title'] as String? ?? '',
      message: row['message'] as String? ?? '',
      action: OpsNotificationAction.fromValue(row['action'] as String?),
      isRead: (row['is_read'] as int? ?? 0) == 1,
      readAt: row['read_at'] != null
          ? DateTime.tryParse(row['read_at'] as String)
          : null,
      createdAt: DateTime.tryParse(row['created_at'] as String? ?? '') ??
          DateTime.now(),
      occurredAt: row['occurred_at'] != null
          ? DateTime.tryParse(row['occurred_at'] as String)
          : null,
      departure: departureUuid != null
          ? OpsNotificationDeparture(
              uuid: departureUuid,
              lineCode: row['departure_line_code'] as String?,
              date: row['departure_date'] as String?,
              time: row['departure_time'] as String?,
            )
          : null,
      data: data,
    );
  }
}
