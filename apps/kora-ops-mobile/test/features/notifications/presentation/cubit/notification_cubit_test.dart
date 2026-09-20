import 'dart:async';

import 'package:bloc_test/bloc_test.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/auth_user.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:kora_ops_mobile/features/notifications/domain/entities/ops_notification.dart';
import 'package:kora_ops_mobile/features/notifications/domain/repositories/notification_repository.dart';
import 'package:kora_ops_mobile/features/notifications/presentation/cubit/notification_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockNotificationRepository extends Mock
    implements NotificationRepository {}

class MockConnectivity extends Mock implements Connectivity {}

class MockAuthCubit extends Mock implements AuthCubit {}

void main() {
  late MockNotificationRepository repository;
  late MockConnectivity connectivity;
  late MockAuthCubit authCubit;
  late StreamController<AuthState> authController;
  late StreamController<List<ConnectivityResult>> connectivityController;

  const AuthUser user = AuthUser(uuid: 'user-1', name: 'Brice', email: 'a@b.c');

  OpsNotification notification({
    String uuid = 'notif-1',
    bool isRead = false,
    OpsNotificationSeverity severity = OpsNotificationSeverity.warning,
  }) =>
      OpsNotification(
        uuid: uuid,
        type: 'manifest_stale',
        severity: severity,
        title: 'Manifeste à actualiser',
        message: 'Le manifeste a changé côté serveur.',
        action: OpsNotificationAction.refreshManifest,
        isRead: isRead,
        createdAt: DateTime(2026, 9, 21, 7, 40),
      );

  setUp(() {
    repository = MockNotificationRepository();
    connectivity = MockConnectivity();
    authCubit = MockAuthCubit();
    authController = StreamController<AuthState>.broadcast();
    connectivityController =
        StreamController<List<ConnectivityResult>>.broadcast();

    when(() => authCubit.stream).thenAnswer((_) => authController.stream);
    when(() => connectivity.onConnectivityChanged)
        .thenAnswer((_) => connectivityController.stream);
    when(() => connectivity.checkConnectivity())
        .thenAnswer((_) async => <ConnectivityResult>[ConnectivityResult.wifi]);
    when(() => repository.readCache())
        .thenAnswer((_) async => <OpsNotification>[]);
    when(() => repository.list()).thenAnswer(
      (_) async =>
          const Result<List<OpsNotification>>.success(<OpsNotification>[]),
    );
    when(() => repository.unreadCounts()).thenAnswer(
      (_) async => const OpsNotificationCounts(unread: 0, criticalUnread: 0),
    );
    when(() => repository.markRead(any())).thenAnswer((_) async {});
    when(() => repository.markAllRead()).thenAnswer((_) async {});
    when(() => repository.retryPendingReads()).thenAnswer((_) async {});
    when(() => repository.clearCache()).thenAnswer((_) async {});
  });

  tearDown(() {
    authController.close();
    connectivityController.close();
  });

  NotificationCubit buildCubit() =>
      NotificationCubit(repository, authCubit, connectivity: connectivity);

  group('démarrage', () {
    blocTest<NotificationCubit, NotificationState>(
      'restaure le cache local avant même que le réseau ait répondu '
      '(critère "restaurer les alertes non acquittées après redémarrage")',
      setUp: () {
        when(() => repository.readCache())
            .thenAnswer((_) async => <OpsNotification>[notification()]);
        // Le réseau ne répond jamais dans ce test : seul l'état issu du
        // cache doit apparaître.
        when(() => repository.list()).thenAnswer(
          (_) => Completer<Result<List<OpsNotification>>>().future,
        );
      },
      build: buildCubit,
      act: (cubit) => unawaited(cubit.bootstrap()),
      wait: const Duration(milliseconds: 10),
      expect: () => <dynamic>[
        predicate<NotificationState>((s) =>
            s.notifications.length == 1 &&
            s.notifications.first.uuid == 'notif-1' &&
            s.unreadCount == 1),
        predicate<NotificationState>(
            (s) => s.status == NotificationStatus.loading),
      ],
    );

    blocTest<NotificationCubit, NotificationState>(
      'récupère les compteurs non lus/critiques une fois le réseau résolu',
      setUp: () {
        when(() => repository.list()).thenAnswer(
          (_) async => Result<List<OpsNotification>>.success(
              <OpsNotification>[notification()]),
        );
        when(() => repository.unreadCounts()).thenAnswer(
          (_) async =>
              const OpsNotificationCounts(unread: 1, criticalUnread: 0),
        );
      },
      build: buildCubit,
      act: (cubit) => cubit.bootstrap(),
      expect: () => <dynamic>[
        predicate<NotificationState>(
            (s) => s.status == NotificationStatus.loading),
        predicate<NotificationState>((s) =>
            s.status == NotificationStatus.success &&
            s.notifications.length == 1 &&
            s.unreadCount == 1),
      ],
    );

    blocTest<NotificationCubit, NotificationState>(
      'se déclenche automatiquement sur AuthAuthenticated, sans appel '
      'manuel à bootstrap()',
      build: buildCubit,
      act: (cubit) async {
        authController.add(const AuthAuthenticated(user));
        await Future<void>.delayed(const Duration(milliseconds: 10));
      },
      verify: (_) {
        verify(() => repository.list()).called(1);
      },
    );

    blocTest<NotificationCubit, NotificationState>(
      'est idempotent : un second AuthAuthenticated (ex. checkAuthStatus() '
      're-émis au retour au premier plan) ne relance pas un second fetch',
      build: buildCubit,
      act: (cubit) async {
        authController.add(const AuthAuthenticated(user));
        await Future<void>.delayed(const Duration(milliseconds: 10));
        authController.add(const AuthAuthenticated(user));
        await Future<void>.delayed(const Duration(milliseconds: 10));
      },
      verify: (_) {
        verify(() => repository.list()).called(1);
      },
    );

    blocTest<NotificationCubit, NotificationState>(
      'jamais appelé avant authentification (AuthUnauthenticated ne '
      'déclenche aucun fetch)',
      build: buildCubit,
      act: (cubit) async {
        authController.add(const AuthUnauthenticated());
        await Future<void>.delayed(const Duration(milliseconds: 10));
      },
      verify: (_) {
        verifyNever(() => repository.list());
      },
    );
  });

  group('reconnexion', () {
    blocTest<NotificationCubit, NotificationState>(
      'le retour du réseau après une coupure relance un fetch et réémet '
      'les marquages "lu" faits hors ligne',
      build: buildCubit,
      act: (cubit) async {
        await cubit.bootstrap();
        connectivityController
            .add(<ConnectivityResult>[ConnectivityResult.none]);
        await Future<void>.delayed(const Duration(milliseconds: 10));
        connectivityController
            .add(<ConnectivityResult>[ConnectivityResult.wifi]);
        await Future<void>.delayed(const Duration(milliseconds: 10));
      },
      verify: (_) {
        // Un appel au bootstrap + un au retour du réseau.
        verify(() => repository.list()).called(2);
        verify(() => repository.retryPendingReads()).called(1);
      },
    );

    blocTest<NotificationCubit, NotificationState>(
      'un simple maintien en ligne (pas de coupure préalable) ne relance '
      'rien de plus',
      build: buildCubit,
      act: (cubit) async {
        await cubit.bootstrap();
        connectivityController
            .add(<ConnectivityResult>[ConnectivityResult.wifi]);
        await Future<void>.delayed(const Duration(milliseconds: 10));
      },
      verify: (_) {
        verify(() => repository.list()).called(1);
        verifyNever(() => repository.retryPendingReads());
      },
    );
  });

  group('lecture', () {
    blocTest<NotificationCubit, NotificationState>(
      'markAsRead() met à jour l\'état localement (optimiste) et notifie '
      'le repository',
      seed: () => NotificationState(
        notifications: <OpsNotification>[notification()],
        unreadCount: 1,
      ),
      build: buildCubit,
      act: (cubit) => cubit.markAsRead('notif-1'),
      expect: () => <dynamic>[
        predicate<NotificationState>(
            (s) => s.notifications.single.isRead == true && s.unreadCount == 0),
      ],
      verify: (_) {
        verify(() => repository.markRead('notif-1')).called(1);
      },
    );

    blocTest<NotificationCubit, NotificationState>(
      'markAllAsRead() met toutes les notifications à lu et remet les '
      'compteurs à zéro',
      seed: () => NotificationState(
        notifications: <OpsNotification>[
          notification(uuid: 'a', severity: OpsNotificationSeverity.critical),
          notification(uuid: 'b'),
        ],
        unreadCount: 2,
        criticalUnreadCount: 1,
      ),
      build: buildCubit,
      act: (cubit) => cubit.markAllAsRead(),
      expect: () => <dynamic>[
        predicate<NotificationState>((s) =>
            s.notifications.every((n) => n.isRead) &&
            s.unreadCount == 0 &&
            s.criticalUnreadCount == 0),
      ],
      verify: (_) {
        verify(() => repository.markAllRead()).called(1);
      },
    );
  });

  group('déconnexion (terminal partagé)', () {
    blocTest<NotificationCubit, NotificationState>(
      'AuthUnauthenticated réinitialise l\'état et vide le cache local — '
      'le prochain convoyeur ne doit jamais voir les alertes précédentes',
      seed: () => NotificationState(
        notifications: <OpsNotification>[notification()],
        unreadCount: 1,
      ),
      build: buildCubit,
      act: (cubit) async {
        authController.add(const AuthUnauthenticated());
        await Future<void>.delayed(const Duration(milliseconds: 10));
      },
      expect: () => <dynamic>[const NotificationState()],
      verify: (_) {
        verify(() => repository.clearCache()).called(1);
      },
    );
  });
}
