import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show
        ApiException,
        NetworkApiException,
        ServerApiException,
        TimeoutApiException,
        UnauthorizedApiException;
import 'package:uuid/uuid.dart';

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../../boarding/domain/entities/boarding_outcome_status.dart';
import '../../../boarding/domain/entities/pending_boarding_operation.dart';
import '../../../boarding/domain/repositories/boarding_repository.dart';
import '../../domain/entities/sync_summary.dart';
import '../../domain/repositories/sync_repository.dart';
import '../datasources/sync_remote_datasource.dart';

class SyncRepositoryImpl implements SyncRepository {
  SyncRepositoryImpl(
    this._remote,
    this._boardingRepository, {
    Uuid? uuid,
  }) : _uuid = uuid ?? const Uuid();

  final SyncRemoteDataSource _remote;
  final BoardingRepository _boardingRepository;
  final Uuid _uuid;

  @override
  Future<Result<SyncSummary>> syncPendingBoardingOperations() async {
    final List<PendingBoardingOperation> pending =
        await _boardingRepository.listPending();
    if (pending.isEmpty) {
      return const Result<SyncSummary>.success(SyncSummary());
    }

    try {
      final SyncBatchFetchResult batch = await _remote.submitBatch(
        batchUuid: _uuid.v4(),
        operations: pending,
      );

      int accepted = 0;
      int pendingResultCount = 0;
      int conflict = 0;
      int rejected = 0;
      for (final SyncOperationResult result in batch.results) {
        await _boardingRepository.applySyncResult(
          operationUuid: result.operationUuid,
          outcome: result.outcome,
          serverRecordedAt: batch.processedAt,
        );
        switch (result.outcome.status) {
          case BoardingOutcomeStatus.valid:
            accepted++;
          case BoardingOutcomeStatus.pendingSync:
            pendingResultCount++;
          case BoardingOutcomeStatus.conflict:
            conflict++;
          default:
            rejected++;
        }
      }

      return Result<SyncSummary>.success(SyncSummary(
        accepted: accepted,
        pending: pendingResultCount,
        conflict: conflict,
        rejected: rejected,
      ));
    } on DioException catch (error) {
      // Une coupure réseau pendant l'envoi du lot n'est pas un échec à
      // afficher : les opérations restent `pending` localement (rien n'a
      // été marqué synchronisé), un prochain `syncNow()` — automatique au
      // retour du réseau — les reprendra telles quelles, sans doublon
      // (idempotence par `operation_uuid`/`batch_uuid`). Toute autre
      // erreur (auth, serveur, permissions) reste un échec visible.
      if (_isNetworkUnreachable(error)) {
        return const Result<SyncSummary>.success(SyncSummary());
      }
      return Result<SyncSummary>.failure(_failureOf(error));
    }
  }

  bool _isNetworkUnreachable(DioException error) {
    final apiError = error.error;
    return apiError is NetworkApiException ||
        apiError is TimeoutApiException ||
        error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.sendTimeout;
  }

  Failure _failureOf(DioException error) {
    final apiError = error.error;
    final message = apiError is ApiException ? apiError.message : null;

    return switch (apiError) {
      UnauthorizedApiException() =>
        AuthenticationFailure(message ?? 'Authentification requise.'),
      ServerApiException() =>
        ServerFailure(message ?? 'Le serveur est momentanément indisponible.'),
      _ => const NetworkFailure(),
    };
  }
}
