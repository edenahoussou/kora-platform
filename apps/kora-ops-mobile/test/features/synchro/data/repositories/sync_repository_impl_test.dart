import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show NetworkApiException, ServerApiException;
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/boarding_outcome.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/boarding_outcome_status.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/pending_boarding_operation.dart';
import 'package:kora_ops_mobile/features/boarding/domain/repositories/boarding_repository.dart';
import 'package:kora_ops_mobile/features/seat_release/domain/entities/pending_seat_release_operation.dart';
import 'package:kora_ops_mobile/features/seat_release/domain/entities/seat_release_outcome.dart';
import 'package:kora_ops_mobile/features/seat_release/domain/entities/seat_release_outcome_status.dart';
import 'package:kora_ops_mobile/features/seat_release/domain/repositories/seat_release_repository.dart';
import 'package:kora_ops_mobile/features/synchro/data/datasources/sync_remote_datasource.dart';
import 'package:kora_ops_mobile/features/synchro/data/repositories/sync_repository_impl.dart';
import 'package:mocktail/mocktail.dart';

class MockSyncRemoteDataSource extends Mock implements SyncRemoteDataSource {}

class MockBoardingRepository extends Mock implements BoardingRepository {}

class MockSeatReleaseRepository extends Mock implements SeatReleaseRepository {}

DioException _networkError() => DioException(
      requestOptions: RequestOptions(path: '/api/v1/ops/sync/batches'),
      error: const NetworkApiException(),
      type: DioExceptionType.connectionError,
    );

void main() {
  setUpAll(() {
    registerFallbackValue(
      const BoardingOutcome(
          status: BoardingOutcomeStatus.valid, message: 'fallback'),
    );
    registerFallbackValue(
      const SeatReleaseOutcome(
          status: SeatReleaseOutcomeStatus.accepted, message: 'fallback'),
    );
  });

  late MockSyncRemoteDataSource remote;
  late MockBoardingRepository boardingRepository;
  late MockSeatReleaseRepository seatReleaseRepository;
  late SyncRepositoryImpl repository;

  final DateTime recordedAt = DateTime(2026, 9, 15, 8);

  setUp(() {
    remote = MockSyncRemoteDataSource();
    boardingRepository = MockBoardingRepository();
    seatReleaseRepository = MockSeatReleaseRepository();
    repository =
        SyncRepositoryImpl(remote, boardingRepository, seatReleaseRepository);
    when(() => boardingRepository.applySyncResult(
          operationUuid: any(named: 'operationUuid'),
          outcome: any(named: 'outcome'),
          serverRecordedAt: any(named: 'serverRecordedAt'),
        )).thenAnswer((_) async {});
    when(() => seatReleaseRepository.applySyncResult(
          operationUuid: any(named: 'operationUuid'),
          outcome: any(named: 'outcome'),
          serverRecordedAt: any(named: 'serverRecordedAt'),
        )).thenAnswer((_) async {});
  });

  test('les deux files vides : aucun appel réseau, résumé vide', () async {
    when(() => boardingRepository.listPending())
        .thenAnswer((_) async => const <PendingBoardingOperation>[]);
    when(() => seatReleaseRepository.listPending())
        .thenAnswer((_) async => const <PendingSeatReleaseOperation>[]);

    final result = await repository.syncPending();

    result.when(
      onSuccess: (summary) => expect(summary.total, 0),
      onFailure: (_) => fail('devait réussir'),
    );
    verifyNever(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: any(named: 'operations'),
        ));
  });

  test('lot mixte (embarquement + descente anticipée) : chaque résultat '
      'appliqué au bon repository', () async {
    when(() => boardingRepository.listPending()).thenAnswer(
      (_) async => <PendingBoardingOperation>[
        PendingBoardingOperation(
          operationUuid: 'op-boarding-1',
          departureUuid: 'dep-1',
          qrPayload: 'QR-1',
          localRecordedAt: recordedAt,
        ),
      ],
    );
    when(() => seatReleaseRepository.listPending()).thenAnswer(
      (_) async => <PendingSeatReleaseOperation>[
        PendingSeatReleaseOperation(
          operationUuid: 'op-release-1',
          type: SeatReleaseType.earlyDropOff,
          departureUuid: 'dep-1',
          ticketUuid: 'ticket-1',
          actualStopUuid: 'stop-1',
          localRecordedAt: recordedAt,
        ),
      ],
    );
    when(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: any(named: 'operations'),
        )).thenAnswer((_) async => SyncBatchFetchResult(
          recordedAt,
          <SyncOperationRawResult>[
            const SyncOperationRawResult(
                operationUuid: 'op-boarding-1', status: 'accepted'),
            const SyncOperationRawResult(
                operationUuid: 'op-release-1', status: 'accepted'),
          ],
        ));

    final result = await repository.syncPending();

    result.when(
      onSuccess: (summary) => expect(summary.accepted, 2),
      onFailure: (_) => fail('devait réussir'),
    );
    verify(() => boardingRepository.applySyncResult(
          operationUuid: 'op-boarding-1',
          outcome: any(named: 'outcome'),
          serverRecordedAt: recordedAt,
        )).called(1);
    verify(() => seatReleaseRepository.applySyncResult(
          operationUuid: 'op-release-1',
          outcome: any(named: 'outcome'),
          serverRecordedAt: recordedAt,
        )).called(1);

    // Vérifie le contenu du lot envoyé : bons types d'opération.
    final captured = verify(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: captureAny(named: 'operations'),
        )).captured.single as List<SyncWireOperation>;
    expect(captured.map((o) => o.operationType),
        containsAll(<String>['boarding.scan', 'seat_release']));
  });

  test('conflit sur une libération de siège comptabilisé', () async {
    when(() => boardingRepository.listPending())
        .thenAnswer((_) async => const <PendingBoardingOperation>[]);
    when(() => seatReleaseRepository.listPending()).thenAnswer(
      (_) async => <PendingSeatReleaseOperation>[
        PendingSeatReleaseOperation(
          operationUuid: 'op-release-2',
          type: SeatReleaseType.noShow,
          departureUuid: 'dep-1',
          ticketUuid: 'ticket-2',
          localRecordedAt: recordedAt,
        ),
      ],
    );
    when(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: any(named: 'operations'),
        )).thenAnswer((_) async => SyncBatchFetchResult(
          recordedAt,
          <SyncOperationRawResult>[
            const SyncOperationRawResult(
              operationUuid: 'op-release-2',
              status: 'conflict',
              code: 'seat_release_conflict',
              message: 'Ce trajet a déjà été libéré depuis un autre terminal.',
              recommendedAction: 'Vérifiez auprès de l’autre terminal.',
            ),
          ],
        ));

    final result = await repository.syncPending();

    result.when(
      onSuccess: (summary) => expect(summary.conflict, 1),
      onFailure: (_) => fail('devait réussir'),
    );
  });

  test('résultat pour une opération inconnue (autre lot/terminal) : '
      'ignoré sans erreur', () async {
    when(() => boardingRepository.listPending()).thenAnswer(
      (_) async => <PendingBoardingOperation>[
        PendingBoardingOperation(
          operationUuid: 'op-boarding-1',
          departureUuid: 'dep-1',
          qrPayload: 'QR-1',
          localRecordedAt: recordedAt,
        ),
      ],
    );
    when(() => seatReleaseRepository.listPending())
        .thenAnswer((_) async => const <PendingSeatReleaseOperation>[]);
    when(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: any(named: 'operations'),
        )).thenAnswer((_) async => SyncBatchFetchResult(
          recordedAt,
          <SyncOperationRawResult>[
            const SyncOperationRawResult(
                operationUuid: 'op-boarding-1', status: 'accepted'),
            const SyncOperationRawResult(
                operationUuid: 'op-inconnue', status: 'accepted'),
          ],
        ));

    final result = await repository.syncPending();

    result.when(
      onSuccess: (summary) => expect(summary.accepted, 1),
      onFailure: (_) => fail('devait réussir'),
    );
    verifyNever(() => seatReleaseRepository.applySyncResult(
          operationUuid: any(named: 'operationUuid'),
          outcome: any(named: 'outcome'),
          serverRecordedAt: any(named: 'serverRecordedAt'),
        ));
  });

  test('coupure réseau pendant l\'envoi : silencieux, résumé vide', () async {
    when(() => boardingRepository.listPending()).thenAnswer(
      (_) async => <PendingBoardingOperation>[
        PendingBoardingOperation(
          operationUuid: 'op-1',
          departureUuid: 'dep-1',
          qrPayload: 'QR-1',
          localRecordedAt: recordedAt,
        ),
      ],
    );
    when(() => seatReleaseRepository.listPending())
        .thenAnswer((_) async => const <PendingSeatReleaseOperation>[]);
    when(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: any(named: 'operations'),
        )).thenThrow(_networkError());

    final result = await repository.syncPending();

    result.when(
      onSuccess: (summary) => expect(summary.total, 0),
      onFailure: (_) => fail('l\'absence de réseau n\'est pas un échec'),
    );
  });

  test('erreur serveur (5xx) : Failure', () async {
    when(() => boardingRepository.listPending()).thenAnswer(
      (_) async => <PendingBoardingOperation>[
        PendingBoardingOperation(
          operationUuid: 'op-1',
          departureUuid: 'dep-1',
          qrPayload: 'QR-1',
          localRecordedAt: recordedAt,
        ),
      ],
    );
    when(() => seatReleaseRepository.listPending())
        .thenAnswer((_) async => const <PendingSeatReleaseOperation>[]);
    when(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: any(named: 'operations'),
        )).thenThrow(DioException(
      requestOptions: RequestOptions(path: '/api/v1/ops/sync/batches'),
      error: const ServerApiException(),
      type: DioExceptionType.badResponse,
    ));

    final result = await repository.syncPending();

    result.when(
      onSuccess: (_) => fail('devait échouer'),
      onFailure: (failure) => expect(failure, isA<ServerFailure>()),
    );
  });
}
