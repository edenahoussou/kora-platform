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
import '../../../boarding/domain/entities/boarding_outcome.dart';
import '../../../boarding/domain/entities/boarding_outcome_status.dart';
import '../../../boarding/domain/entities/pending_boarding_operation.dart';
import '../../../boarding/domain/repositories/boarding_repository.dart';
import '../../../seat_release/domain/entities/pending_seat_release_operation.dart';
import '../../../seat_release/domain/entities/seat_release_outcome.dart';
import '../../../seat_release/domain/entities/seat_release_outcome_status.dart';
import '../../../seat_release/domain/repositories/seat_release_repository.dart';
import '../../domain/entities/sync_summary.dart';
import '../../domain/repositories/sync_repository.dart';
import '../datasources/sync_remote_datasource.dart';

/// Provenance d'une opération dans le lot — décide quel repository local
/// applique le résultat une fois le lot traité (voir `_dispatchResult`).
enum _Origin { boarding, seatRelease }

class SyncRepositoryImpl implements SyncRepository {
  SyncRepositoryImpl(
    this._remote,
    this._boardingRepository,
    this._seatReleaseRepository, {
    Uuid? uuid,
  }) : _uuid = uuid ?? const Uuid();

  final SyncRemoteDataSource _remote;
  final BoardingRepository _boardingRepository;
  final SeatReleaseRepository _seatReleaseRepository;
  final Uuid _uuid;

  @override
  Future<Result<SyncSummary>> syncPending() async {
    final List<PendingBoardingOperation> pendingBoarding =
        await _boardingRepository.listPending();
    final List<PendingSeatReleaseOperation> pendingSeatReleases =
        await _seatReleaseRepository.listPending();

    if (pendingBoarding.isEmpty && pendingSeatReleases.isEmpty) {
      return const Result<SyncSummary>.success(SyncSummary());
    }

    final Map<String, _Origin> originByOperationUuid = <String, _Origin>{
      for (final op in pendingBoarding) op.operationUuid: _Origin.boarding,
      for (final op in pendingSeatReleases)
        op.operationUuid: _Origin.seatRelease,
    };

    final List<SyncWireOperation> operations = <SyncWireOperation>[
      ...pendingBoarding.map((op) => SyncWireOperation(
            operationUuid: op.operationUuid,
            operationType: 'boarding.scan',
            localRecordedAt: op.localRecordedAt,
            payload: <String, dynamic>{
              'departure_uuid': op.departureUuid,
              if (op.qrPayload != null) 'qr_payload': op.qrPayload,
              if (op.shortCode != null) 'short_code': op.shortCode,
            },
          )),
      ...pendingSeatReleases.map((op) => SyncWireOperation(
            operationUuid: op.operationUuid,
            operationType: op.type.syncOperationType,
            localRecordedAt: op.localRecordedAt,
            payload: <String, dynamic>{
              'departure_uuid': op.departureUuid,
              'ticket_uuid': op.ticketUuid,
              if (op.actualStopUuid != null)
                'actual_stop_uuid': op.actualStopUuid,
            },
          )),
    ];

    try {
      final SyncBatchFetchResult batch = await _remote.submitBatch(
        batchUuid: _uuid.v4(),
        operations: operations,
      );

      int accepted = 0;
      int pendingResultCount = 0;
      int conflict = 0;
      int rejected = 0;

      for (final SyncOperationRawResult result in batch.results) {
        final _Origin? origin = originByOperationUuid[result.operationUuid];
        if (origin == null) continue; // opération d'un autre lot/appareil

        final _Tally tally = await _dispatchResult(
          origin: origin,
          result: result,
          processedAt: batch.processedAt,
        );
        switch (tally) {
          case _Tally.accepted:
            accepted++;
          case _Tally.pending:
            pendingResultCount++;
          case _Tally.conflict:
            conflict++;
          case _Tally.rejected:
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

  Future<_Tally> _dispatchResult({
    required _Origin origin,
    required SyncOperationRawResult result,
    required DateTime processedAt,
  }) async {
    if (origin == _Origin.boarding) {
      final BoardingOutcomeStatus status = _boardingStatusOf(result);
      await _boardingRepository.applySyncResult(
        operationUuid: result.operationUuid,
        outcome: BoardingOutcome(
          status: status,
          message: result.message ?? status.defaultMessage,
          operationUuid: result.operationUuid,
        ),
        serverRecordedAt: processedAt,
      );
      return _tallyOfBoarding(status);
    }

    final SeatReleaseOutcomeStatus status = _seatReleaseStatusOf(result);
    await _seatReleaseRepository.applySyncResult(
      operationUuid: result.operationUuid,
      outcome: SeatReleaseOutcome(
        status: status,
        message: result.message ?? status.defaultMessage,
        recommendedAction: result.recommendedAction,
        operationUuid: result.operationUuid,
      ),
      serverRecordedAt: processedAt,
    );
    return _tallyOfSeatRelease(status);
  }

  BoardingOutcomeStatus _boardingStatusOf(SyncOperationRawResult result) {
    if (result.status == 'accepted') return BoardingOutcomeStatus.valid;
    if (result.status == 'conflict') return BoardingOutcomeStatus.conflict;
    return switch (result.code) {
      'ticket_cancelled' => BoardingOutcomeStatus.cancelled,
      'wrong_departure' ||
      'leg_not_active' =>
        BoardingOutcomeStatus.wrongDeparture,
      'ticket_already_boarded' => BoardingOutcomeStatus.alreadyBoarded,
      _ => BoardingOutcomeStatus.error,
    };
  }

  SeatReleaseOutcomeStatus _seatReleaseStatusOf(
          SyncOperationRawResult result) =>
      switch (result.status) {
        'accepted' => SeatReleaseOutcomeStatus.accepted,
        'conflict' => SeatReleaseOutcomeStatus.conflict,
        'pending' => SeatReleaseOutcomeStatus.pendingSync,
        'rejected' => SeatReleaseOutcomeStatus.rejected,
        _ => SeatReleaseOutcomeStatus.error,
      };

  _Tally _tallyOfBoarding(BoardingOutcomeStatus status) => switch (status) {
        BoardingOutcomeStatus.valid => _Tally.accepted,
        BoardingOutcomeStatus.pendingSync => _Tally.pending,
        BoardingOutcomeStatus.conflict => _Tally.conflict,
        _ => _Tally.rejected,
      };

  _Tally _tallyOfSeatRelease(SeatReleaseOutcomeStatus status) =>
      switch (status) {
        SeatReleaseOutcomeStatus.accepted => _Tally.accepted,
        SeatReleaseOutcomeStatus.pendingSync => _Tally.pending,
        SeatReleaseOutcomeStatus.conflict => _Tally.conflict,
        _ => _Tally.rejected,
      };

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

enum _Tally { accepted, pending, conflict, rejected }
