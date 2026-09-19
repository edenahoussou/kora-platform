import 'dart:async';

import 'package:bloc_test/bloc_test.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/boarding/domain/repositories/boarding_repository.dart';
import 'package:kora_ops_mobile/features/seat_release/domain/repositories/seat_release_repository.dart';
import 'package:kora_ops_mobile/features/synchro/domain/entities/pending_operation.dart';
import 'package:kora_ops_mobile/features/synchro/domain/entities/sync_summary.dart';
import 'package:kora_ops_mobile/features/synchro/domain/repositories/sync_repository.dart';
import 'package:kora_ops_mobile/features/synchro/presentation/cubit/sync_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockSyncRepository extends Mock implements SyncRepository {}

class MockBoardingRepository extends Mock implements BoardingRepository {}

class MockSeatReleaseRepository extends Mock implements SeatReleaseRepository {}

class MockConnectivity extends Mock implements Connectivity {}

void main() {
  late MockSyncRepository syncRepository;
  late MockBoardingRepository boardingRepository;
  late MockSeatReleaseRepository seatReleaseRepository;
  late MockConnectivity connectivity;
  late StreamController<List<ConnectivityResult>> connectivityController;

  setUp(() {
    syncRepository = MockSyncRepository();
    boardingRepository = MockBoardingRepository();
    seatReleaseRepository = MockSeatReleaseRepository();
    connectivity = MockConnectivity();
    connectivityController =
        StreamController<List<ConnectivityResult>>.broadcast();

    when(() => connectivity.onConnectivityChanged)
        .thenAnswer((_) => connectivityController.stream);
    when(() => connectivity.checkConnectivity())
        .thenAnswer((_) async => <ConnectivityResult>[ConnectivityResult.wifi]);
    when(() => boardingRepository.totalPendingCount())
        .thenAnswer((_) async => 0);
    when(() => seatReleaseRepository.totalPendingCount())
        .thenAnswer((_) async => 0);
  });

  tearDown(() => connectivityController.close());

  SyncCubit buildCubit() => SyncCubit(
        syncRepository,
        boardingRepository,
        seatReleaseRepository,
        connectivity: connectivity,
      );

  blocTest<SyncCubit, SyncState>(
    'bootstrap() additionne le compteur en attente des deux files '
    '(embarquements + libérations de siège), sans déclencher de synchro',
    setUp: () {
      when(() => boardingRepository.totalPendingCount())
          .thenAnswer((_) async => 3);
      when(() => seatReleaseRepository.totalPendingCount())
          .thenAnswer((_) async => 2);
    },
    build: buildCubit,
    act: (cubit) => cubit.bootstrap(),
    expect: () => <dynamic>[
      predicate<SyncState>(
          (s) => s.pendingOperationsCount == 5 && s.isOnline == true),
    ],
    verify: (_) {
      verifyNever(() => syncRepository.syncPending());
    },
  );

  blocTest<SyncCubit, SyncState>(
    'goOffline() passe isOnline à false',
    build: buildCubit,
    act: (cubit) => cubit.goOffline(),
    expect: () => <dynamic>[predicate<SyncState>((s) => s.isOnline == false)],
  );

  blocTest<SyncCubit, SyncState>(
    'enqueue() ajoute une opération mock en tête de file (flux encore '
    'mockés, hors périmètre)',
    build: buildCubit,
    act: (cubit) => cubit.enqueue(
      const PendingOperation(
          id: '1', label: 'Refus — Siège 12A', createdAtLabel: 'À l\'instant'),
    ),
    expect: () => <dynamic>[predicate<SyncState>((s) => s.pending.length == 1)],
  );

  group('syncNow', () {
    blocTest<SyncCubit, SyncState>(
      'succès : résumé affiché, compteur (des deux files) mis à jour',
      setUp: () {
        when(() => syncRepository.syncPending()).thenAnswer(
          (_) async => const Result<SyncSummary>.success(
            SyncSummary(accepted: 2, rejected: 1),
          ),
        );
        when(() => boardingRepository.totalPendingCount())
            .thenAnswer((_) async => 0);
        when(() => seatReleaseRepository.totalPendingCount())
            .thenAnswer((_) async => 0);
      },
      build: buildCubit,
      act: (cubit) => cubit.syncNow(),
      expect: () => <dynamic>[
        predicate<SyncState>((s) => s.isSyncing == true),
        predicate<SyncState>((s) =>
            s.isSyncing == false &&
            s.lastSyncSummary?.accepted == 2 &&
            s.lastSyncError == null),
      ],
    );

    blocTest<SyncCubit, SyncState>(
      'ne fait rien si hors ligne',
      build: () {
        final cubit = buildCubit();
        cubit.goOffline();
        return cubit;
      },
      act: (cubit) => cubit.syncNow(),
      expect: () => <dynamic>[],
      verify: (_) {
        verifyNever(() => syncRepository.syncPending());
      },
    );

    blocTest<SyncCubit, SyncState>(
      'échec technique : message affiché, ancien résumé conservé',
      setUp: () {
        when(() => syncRepository.syncPending()).thenAnswer(
          (_) async => const Result<SyncSummary>.failure(
            ServerFailure('Le serveur est momentanément indisponible.'),
          ),
        );
      },
      build: buildCubit,
      act: (cubit) => cubit.syncNow(),
      expect: () => <dynamic>[
        predicate<SyncState>((s) => s.isSyncing == true),
        predicate<SyncState>((s) =>
            s.isSyncing == false &&
            s.lastSyncError == 'Le serveur est momentanément indisponible.'),
      ],
    );
  });

  blocTest<SyncCubit, SyncState>(
    'retour de connectivité après une coupure déclenche une synchro '
    'automatique',
    setUp: () {
      when(() => syncRepository.syncPending()).thenAnswer(
        (_) async =>
            const Result<SyncSummary>.success(SyncSummary(accepted: 1)),
      );
    },
    build: buildCubit,
    act: (cubit) async {
      await cubit.bootstrap();
      cubit.goOffline();
      connectivityController.add(<ConnectivityResult>[ConnectivityResult.wifi]);
      await Future<void>.delayed(const Duration(milliseconds: 20));
    },
    verify: (_) {
      verify(() => syncRepository.syncPending()).called(1);
    },
  );
}
