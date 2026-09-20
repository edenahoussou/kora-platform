import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show NetworkApiException;
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/notifications/data/datasources/notification_local_datasource.dart';
import 'package:kora_ops_mobile/features/notifications/data/datasources/notification_remote_datasource.dart';
import 'package:kora_ops_mobile/features/notifications/data/repositories/notification_repository_impl.dart';
import 'package:kora_ops_mobile/features/notifications/domain/entities/ops_notification.dart';
import 'package:mocktail/mocktail.dart';

class MockNotificationRemoteDataSource extends Mock
    implements NotificationRemoteDataSource {}

class MockNotificationLocalDataSource extends Mock
    implements NotificationLocalDataSource {}

DioException _networkError() => DioException(
      requestOptions: RequestOptions(path: '/api/v1/notifications'),
      error: const NetworkApiException(),
      type: DioExceptionType.connectionError,
    );

void main() {
  setUpAll(() {
    registerFallbackValue(DateTime(2026, 1, 1));
  });

  late MockNotificationRemoteDataSource remote;
  late MockNotificationLocalDataSource local;

  final OpsNotification remoteNotification = OpsNotification(
    uuid: 'notif-1',
    type: 'scan_conflict',
    severity: OpsNotificationSeverity.critical,
    title: 'Conflit de scan',
    message: 'Un billet a été scanné sur deux terminaux.',
    action: OpsNotificationAction.reviewScanConflict,
    isRead: false,
    createdAt: DateTime(2026, 9, 21, 7, 41),
  );

  NotificationRepositoryImpl repository() =>
      NotificationRepositoryImpl(remote, local);

  setUp(() {
    remote = MockNotificationRemoteDataSource();
    local = MockNotificationLocalDataSource();
  });

  group('list()', () {
    test(
      'en ligne : fusionne la page distante dans le cache local (dédup par '
      'uuid assurée par upsertAll) puis relit le cache comme état de '
      'vérité — jamais la réponse réseau brute',
      () async {
        when(() => remote.list())
            .thenAnswer((_) async => <OpsNotification>[remoteNotification]);
        when(() => local.upsertAll(any())).thenAnswer((_) async {});
        when(() => local.readCache())
            .thenAnswer((_) async => <OpsNotification>[remoteNotification]);

        final result = await repository().list();

        expect(result.isSuccess, isTrue);
        result.when(
          onSuccess: (notifications) =>
              expect(notifications, <OpsNotification>[remoteNotification]),
          onFailure: (_) => fail('devait réussir'),
        );
        verify(() => local.upsertAll(<OpsNotification>[remoteNotification]))
            .called(1);
        verify(() => local.readCache()).called(1);
      },
    );

    test(
      'hors ligne : retombe sur le cache local plutôt que d\'échouer — '
      'restaure les alertes déjà connues sans appel réseau réussi',
      () async {
        when(() => remote.list()).thenThrow(_networkError());
        when(() => local.readCache())
            .thenAnswer((_) async => <OpsNotification>[remoteNotification]);

        final result = await repository().list();

        expect(result.isSuccess, isTrue);
        result.when(
          onSuccess: (notifications) =>
              expect(notifications, <OpsNotification>[remoteNotification]),
          onFailure: (_) => fail('devait retomber sur le cache'),
        );
        verifyNever(() => local.upsertAll(any()));
      },
    );
  });

  group('markRead()', () {
    test(
      'marque localement puis confirme au serveur — le drapeau '
      '"en attente" est levé après succès',
      () async {
        when(() => local.markReadLocally(any(), any()))
            .thenAnswer((_) async {});
        when(() => remote.markRead('notif-1')).thenAnswer((_) async {});
        when(() => local.confirmSynced('notif-1')).thenAnswer((_) async {});

        await repository().markRead('notif-1');

        verify(() => local.markReadLocally('notif-1', any())).called(1);
        verify(() => remote.markRead('notif-1')).called(1);
        verify(() => local.confirmSynced('notif-1')).called(1);
      },
    );

    test(
      'hors ligne : le marquage local reste posé, jamais confirmé — sera '
      'réémis par retryPendingReads() (trace de l\'action locale conservée)',
      () async {
        when(() => local.markReadLocally(any(), any()))
            .thenAnswer((_) async {});
        when(() => remote.markRead('notif-1')).thenThrow(_networkError());

        await repository().markRead('notif-1');

        verify(() => local.markReadLocally('notif-1', any())).called(1);
        verifyNever(() => local.confirmSynced(any()));
      },
    );
  });

  group('retryPendingReads()', () {
    test(
      'réémet chaque marquage "lu" en attente et confirme les succès',
      () async {
        when(() => local.pendingMarkReadUuids())
            .thenAnswer((_) async => <String>['a', 'b']);
        when(() => remote.markRead('a')).thenAnswer((_) async {});
        when(() => remote.markRead('b')).thenThrow(_networkError());
        when(() => local.confirmSynced(any())).thenAnswer((_) async {});

        await repository().retryPendingReads();

        verify(() => local.confirmSynced('a')).called(1);
        verifyNever(() => local.confirmSynced('b'));
      },
    );
  });
}
