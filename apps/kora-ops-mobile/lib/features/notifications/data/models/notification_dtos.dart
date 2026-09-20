import '../../domain/entities/ops_notification.dart';

/// Couche DATA : lecture de `GET /notifications` — miroir de
/// `OpsNotificationResource` côté backend (voir `OpsNotificationController`,
/// module Notifications, MEZ-96).
abstract final class NotificationDtos {
  static List<OpsNotification> list(Object? body) {
    final Object? rows = body is Map ? body['data'] : null;
    if (rows is! List) return const <OpsNotification>[];

    return rows
        .whereType<Map<dynamic, dynamic>>()
        .map((row) => fromJson(Map<String, dynamic>.from(row)))
        .toList();
  }

  /// Compteurs de `GET /notifications/unread-count` — et `meta` de
  /// `GET /notifications` (même structure `unread_count`/
  /// `critical_unread_count`, réutilisée pour ne pas dupliquer un appel).
  static ({int unread, int criticalUnread}) counts(Object? body) {
    final Object? source = body is Map ? (body['data'] ?? body) : null;
    final Map<dynamic, dynamic> map = source is Map ? source : const {};
    return (
      unread: (map['unread'] as num?)?.toInt() ?? 0,
      criticalUnread: (map['critical_unread'] as num?)?.toInt() ?? 0,
    );
  }

  static OpsNotification fromJson(Map<String, dynamic> json) {
    final Object? departureJson = json['departure'];
    final OpsNotificationDeparture? departure =
        departureJson is Map && departureJson['uuid'] != null
            ? OpsNotificationDeparture(
                uuid: '${departureJson['uuid']}',
                lineCode: departureJson['line_code'] as String?,
                date: departureJson['date'] as String?,
                time: departureJson['time'] as String?,
              )
            : null;

    final Object? rawData = json['data'];

    return OpsNotification(
      uuid: '${json['uuid']}',
      type: json['type'] as String? ?? 'unknown',
      severity: OpsNotificationSeverityX.fromValue(json['severity'] as String?),
      title: json['title'] as String? ?? '',
      message: json['message'] as String? ?? '',
      action: OpsNotificationAction.fromValue(json['action'] as String?),
      isRead: json['is_read'] as bool? ?? false,
      createdAt: DateTime.tryParse(json['created_at'] as String? ?? '') ??
          DateTime.now(),
      eventUuid: json['event_uuid'] as String?,
      readAt: json['read_at'] != null
          ? DateTime.tryParse(json['read_at'] as String)
          : null,
      occurredAt: json['occurred_at'] != null
          ? DateTime.tryParse(json['occurred_at'] as String)
          : null,
      departure: departure,
      data: rawData is Map ? Map<String, dynamic>.from(rawData) : const {},
    );
  }
}
