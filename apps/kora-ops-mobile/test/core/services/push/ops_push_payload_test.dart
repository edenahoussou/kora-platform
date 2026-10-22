import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/services/push/ops_push_payload.dart';
import 'package:kora_ops_mobile/features/notifications/domain/entities/ops_notification.dart';

void main() {
  group('OpsPushPayload.fromData', () {
    test('lit la charge utile complète envoyée par SendOpsPushJob', () {
      final OpsPushPayload payload = OpsPushPayload.fromData(<String, dynamic>{
        'type': 'departure_assigned',
        'event_id': '42',
        'notification_uuid': 'notif-uuid',
        'action': 'open_departure',
        'severity': 'info',
        'departure_uuid': 'dep-uuid',
      });

      expect(payload.type, 'departure_assigned');
      expect(payload.action, OpsNotificationAction.openDeparture);
      expect(payload.severity, OpsNotificationSeverity.info);
      expect(payload.departureUuid, 'dep-uuid');
      expect(payload.notificationUuid, 'notif-uuid');
      expect(payload.targetsDeparture, isTrue);
    });

    test('déduit l\'action du type si le back ne l\'envoie pas encore', () {
      expect(
        OpsPushPayload.fromData(<String, dynamic>{'type': 'manifest_stale'})
            .action,
        OpsNotificationAction.refreshManifest,
      );
      expect(
        OpsPushPayload.fromData(<String, dynamic>{'type': 'sync_failed'})
            .action,
        OpsNotificationAction.retrySync,
      );
      expect(
        OpsPushPayload.fromData(<String, dynamic>{'type': 'terminal_revoked'})
            .action,
        OpsNotificationAction.reauthenticate,
      );
    });

    test('un payload vide ou inconnu ne lève pas et ne vise rien', () {
      final OpsPushPayload payload =
          OpsPushPayload.fromData(<String, dynamic>{'departure_uuid': ''});

      expect(payload.type, 'unknown');
      expect(payload.action, OpsNotificationAction.none);
      expect(payload.departureUuid, isNull);
      expect(payload.targetsDeparture, isFalse);
    });

    test('l\'action explicite prime sur le type', () {
      final OpsPushPayload payload = OpsPushPayload.fromData(<String, dynamic>{
        'type': 'departure_assigned',
        'action': 'none',
      });

      expect(payload.action, OpsNotificationAction.none);
    });
  });

  group('encode / decode (notification locale au premier plan)', () {
    test('aller-retour sans perte', () {
      const OpsPushPayload original = OpsPushPayload(
        type: 'scan_conflict',
        action: OpsNotificationAction.reviewScanConflict,
        severity: OpsNotificationSeverity.critical,
        departureUuid: 'dep-uuid',
        notificationUuid: 'notif-uuid',
      );

      expect(OpsPushPayload.decode(original.encode()), original);
    });

    test('payload absent ou illisible → null', () {
      expect(OpsPushPayload.decode(null), isNull);
      expect(OpsPushPayload.decode(''), isNull);
      expect(OpsPushPayload.decode('pas du json'), isNull);
      expect(OpsPushPayload.decode('[1,2]'), isNull);
    });
  });
}
