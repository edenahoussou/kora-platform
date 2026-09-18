import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show NetworkApiException, ServerApiException;
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/boarding_outcome.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/boarding_outcome_status.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/pending_boarding_operation.dart';
import 'package:kora_ops_mobile/features/boarding/domain/repositories/boarding_repository.dart';
import 'package:kora_ops_mobile/features/synchro/data/datasources/sync_remote_datasource.dart';
import 'package:kora_ops_mobile/features/synchro/data/repositories/sync_repository_impl.dart';
import 'package:mocktail/mocktail.dart';

class MockSyncRemoteDataSource extends Mock implements SyncRemoteDataSource {}

class MockBoardingRepository extends Mock implements BoardingRepository {}

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
    registerFallbackValue(DateTime(2020));
  });

  late MockSyncRemoteDataSource remote;
  late MockBoardingRepository boardingRepository;
  late SyncRepositoryImpl repository;

  final DateTime recordedAt = DateTime(2026, 9, 15, 8);

  setUp(() {
    remote = MockSyncRemoteDataSource();
    boardingRepository = MockBoardingRepository();
    repository = SyncRepositoryImpl(remote, boardingRepository);
    when(() => boardingRepository.applySyncResult(
          operationUuid: any(named: 'operationUuid'),
          outcome: any(named: 'outcome'),
          serverRecordedAt: any(named: 'serverRecordedAt'),
        )).thenAnswer((_) async {});
  });

  test('file vide : aucun appel réseau, résumé vide', () async {
    when(() => boardingRepository.listPending())
        .thenAnswer((_) async => const <PendingBoardingOperation>[]);

    final result = await repository.syncPendingBoardingOperations();

    result.when(
      onSuccess: (summary) => expect(summary.total, 0),
      onFailure: (_) => fail('devait réussir'),
    );
    verifyNever(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: any(named: 'operations'),
        ));
  });

  test(
      'lot avec résultats mixtes : résumé correct, chaque opération '
      'appliquée localement', () async {
    final List<PendingBoardingOperation> pending = <PendingBoardingOperation>[
      PendingBoardingOperation(
        operationUuid: 'op-1',
        departureUuid: 'dep-1',
        qrPayload: 'QR-1',
        localRecordedAt: recordedAt,
      ),
      PendingBoardingOperation(
        operationUuid: 'op-2',
        departureUuid: 'dep-1',
        shortCode: 'AB12CD',
        localRecordedAt: recordedAt,
      ),
    ];
    when(() => boardingRepository.listPending())
        .thenAnswer((_) async => pending);
    when(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: pending,
        )).thenAnswer((_) async => SyncBatchFetchResult(
          recordedAt,
          <SyncOperationResult>[
            const SyncOperationResult(
              'op-1',
              BoardingOutcome(
                status: BoardingOutcomeStatus.valid,
                message: 'Billet valide.',
              ),
            ),
            const SyncOperationResult(
              'op-2',
              BoardingOutcome(
                status: BoardingOutcomeStatus.cancelled,
                message: 'Ce billet a été annulé.',
              ),
            ),
          ],
        ));

    final result = await repository.syncPendingBoardingOperations();

    result.when(
      onSuccess: (summary) {
        expect(summary.accepted, 1);
        expect(summary.rejected, 1);
        expect(summary.conflict, 0);
      },
      onFailure: (_) => fail('devait réussir'),
    );
    verify(() => boardingRepository.applySyncResult(
          operationUuid: 'op-1',
          outcome: any(named: 'outcome'),
          serverRecordedAt: recordedAt,
        )).called(1);
    verify(() => boardingRepository.applySyncResult(
          operationUuid: 'op-2',
          outcome: any(named: 'outcome'),
          serverRecordedAt: recordedAt,
        )).called(1);
  });

  test(
      'coupure réseau pendant l\'envoi : silencieux, résumé vide, rien '
      'de marqué synchronisé', () async {
    final List<PendingBoardingOperation> pending = <PendingBoardingOperation>[
      PendingBoardingOperation(
        operationUuid: 'op-1',
        departureUuid: 'dep-1',
        qrPayload: 'QR-1',
        localRecordedAt: recordedAt,
      ),
    ];
    when(() => boardingRepository.listPending())
        .thenAnswer((_) async => pending);
    when(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: pending,
        )).thenThrow(_networkError());

    final result = await repository.syncPendingBoardingOperations();

    result.when(
      onSuccess: (summary) => expect(summary.total, 0),
      onFailure: (_) => fail('l\'absence de réseau n\'est pas un échec'),
    );
    verifyNever(() => boardingRepository.applySyncResult(
          operationUuid: any(named: 'operationUuid'),
          outcome: any(named: 'outcome'),
          serverRecordedAt: any(named: 'serverRecordedAt'),
        ));
  });

  test('erreur serveur (5xx) : Failure exposée', () async {
    final List<PendingBoardingOperation> pending = <PendingBoardingOperation>[
      PendingBoardingOperation(
        operationUuid: 'op-1',
        departureUuid: 'dep-1',
        qrPayload: 'QR-1',
        localRecordedAt: recordedAt,
      ),
    ];
    when(() => boardingRepository.listPending())
        .thenAnswer((_) async => pending);
    when(() => remote.submitBatch(
          batchUuid: any(named: 'batchUuid'),
          operations: pending,
        )).thenThrow(DioException(
      requestOptions: RequestOptions(path: '/api/v1/ops/sync/batches'),
      error: const ServerApiException(),
      type: DioExceptionType.badResponse,
    ));

    final result = await repository.syncPendingBoardingOperations();

    result.when(
      onSuccess: (_) => fail('devait échouer'),
      onFailure: (failure) => expect(failure, isA<ServerFailure>()),
    );
  });
}
