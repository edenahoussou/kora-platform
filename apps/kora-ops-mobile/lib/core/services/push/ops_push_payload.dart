import 'dart:convert';

import 'package:equatable/equatable.dart';

import '../../../features/notifications/domain/entities/ops_notification.dart';

/// Charge utile `data` d'un push OPS — miroir de `SendOpsPushJob` côté
/// backend (kora-ops-web) : uniquement des codes et des UUID, jamais de
/// donnée métier (montant, passager...). Toutes les clés sont des chaînes
/// (contrainte FCM).
///
/// | clé                 | exemple             |
/// |---------------------|---------------------|
/// | `type`              | `departure_assigned`|
/// | `action`            | `open_departure`    |
/// | `severity`          | `info`              |
/// | `departure_uuid`    | `9f1c…`             |
/// | `notification_uuid` | `0b7e…`             |
/// | `event_id`          | `42` (interne, ignoré côté mobile) |
class OpsPushPayload extends Equatable {
  const OpsPushPayload({
    required this.type,
    required this.action,
    required this.severity,
    this.departureUuid,
    this.notificationUuid,
  });

  /// Parse le `RemoteMessage.data` FCM (ou le payload JSON d'une
  /// notification locale). Ne lève jamais : un payload inconnu donne un
  /// [OpsNotificationAction.none] qui ouvre simplement la liste.
  factory OpsPushPayload.fromData(Map<String, dynamic> data) {
    final String type = _string(data['type']) ?? 'unknown';
    final String? rawAction = _string(data['action']);

    return OpsPushPayload(
      type: type,
      // Repli pour un backend pas encore à jour (sans clé `action`) : on
      // déduit l'action du type, même table que `OpsNotificationType::action()`.
      action: rawAction != null
          ? OpsNotificationAction.fromValue(rawAction)
          : _actionForType(type),
      severity: OpsNotificationSeverityX.fromValue(_string(data['severity'])),
      departureUuid: _string(data['departure_uuid']),
      notificationUuid: _string(data['notification_uuid']),
    );
  }

  /// Relit le payload sérialisé par [encode] (notification locale affichée
  /// au premier plan). `null` si absent ou illisible.
  static OpsPushPayload? decode(String? raw) {
    if (raw == null || raw.isEmpty) return null;
    try {
      final Object? json = jsonDecode(raw);
      if (json is! Map) return null;
      return OpsPushPayload.fromData(Map<String, dynamic>.from(json));
    } on FormatException {
      return null;
    }
  }

  final String type;
  final OpsNotificationAction action;
  final OpsNotificationSeverity severity;
  final String? departureUuid;
  final String? notificationUuid;

  /// Le tap doit-il viser un départ précis (manifeste) ?
  bool get targetsDeparture => switch (action) {
        OpsNotificationAction.openDeparture ||
        OpsNotificationAction.refreshManifest ||
        OpsNotificationAction.reviewScanConflict =>
          true,
        _ => false,
      };

  String encode() => jsonEncode(<String, String>{
        'type': type,
        'action': _actionValue(action),
        'severity': severity.value,
        if (departureUuid != null) 'departure_uuid': departureUuid!,
        if (notificationUuid != null) 'notification_uuid': notificationUuid!,
      });

  static String? _string(Object? value) {
    if (value == null) return null;
    final String text = '$value'.trim();
    return text.isEmpty ? null : text;
  }

  static OpsNotificationAction _actionForType(String type) => switch (type) {
        'departure_assigned' ||
        'departure_modified' ||
        'no_show_closed' ||
        'departure_departed' ||
        'incident_reported' ||
        'incident_critical' ||
        'departure_delayed' =>
          OpsNotificationAction.openDeparture,
        'manifest_stale' => OpsNotificationAction.refreshManifest,
        'terminal_revoked' => OpsNotificationAction.reauthenticate,
        'scan_conflict' => OpsNotificationAction.reviewScanConflict,
        'operation_rejected' => OpsNotificationAction.reviewRejectedOperations,
        'sync_failed' => OpsNotificationAction.retrySync,
        'print_error' => OpsNotificationAction.checkPrinter,
        _ => OpsNotificationAction.none,
      };

  static String _actionValue(OpsNotificationAction action) => switch (action) {
        OpsNotificationAction.none => 'none',
        OpsNotificationAction.openDeparture => 'open_departure',
        OpsNotificationAction.refreshManifest => 'refresh_manifest',
        OpsNotificationAction.reauthenticate => 'reauthenticate',
        OpsNotificationAction.reviewScanConflict => 'review_scan_conflict',
        OpsNotificationAction.reviewRejectedOperations =>
          'review_rejected_operations',
        OpsNotificationAction.retrySync => 'retry_sync',
        OpsNotificationAction.checkPrinter => 'check_printer',
      };

  @override
  List<Object?> get props =>
      <Object?>[type, action, severity, departureUuid, notificationUuid];
}
