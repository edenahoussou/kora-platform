import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show NetworkApiException, TimeoutApiException;

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/boarding_outcome.dart';
import '../../domain/entities/boarding_outcome_status.dart';
import '../../domain/entities/pending_boarding_operation.dart';
import '../../domain/repositories/boarding_repository.dart';
import '../datasources/boarding_local_datasource.dart';
import '../datasources/boarding_remote_datasource.dart';

/// Couche DATA : assemble l'anti-double-scan local, l'appel réseau et la
/// file hors ligne derrière le contrat du domaine — voir docstring de
/// `BoardingRepository`.
class BoardingRepositoryImpl implements BoardingRepository {
  BoardingRepositoryImpl(
    this._remote,
    this._local, {
    DateTime Function()? now,
  });

  final BoardingRemoteDataSource _remote;
  final BoardingLocalDataSource _local;

  @override
  Future<Result<BoardingOutcome>> scan({
    required String departureUuid,
    String? qrPayload,
    String? shortCode,
    required String operationUuid,
    required DateTime localRecordedAt,
  }) async {
    assert(
      (qrPayload == null) != (shortCode == null),
      'scan() attend qrPayload XOR shortCode',
    );

    final BoardingOutcome? cached = await _local.findConclusiveOutcome(
      departureUuid: departureUuid,
      qrPayload: qrPayload,
      shortCode: shortCode,
    );
    if (cached != null) return Result<BoardingOutcome>.success(cached);

    try {
      final BoardingOutcome outcome = await _remote.scan(
        departureUuid: departureUuid,
        qrPayload: qrPayload,
        shortCode: shortCode,
        operationUuid: operationUuid,
        localRecordedAt: localRecordedAt,
      );
      await _local.recordOutcome(
        operationUuid: operationUuid,
        departureUuid: departureUuid,
        qrPayload: qrPayload,
        shortCode: shortCode,
        outcome: outcome,
        localRecordedAt: localRecordedAt,
        synced: true,
      );
      return Result<BoardingOutcome>.success(outcome);
    } on BoardingApiException catch (error) {
      if (error.code != BoardingApiException.qrUnknown) {
        return Result<BoardingOutcome>.failure(ServerFailure(error.message));
      }
      final BoardingOutcome outcome = BoardingOutcome(
        status: BoardingOutcomeStatus.ticketUnknown,
        message: error.message,
      );
      // Un même QR illisible/inconnu re-scanné doit rester bloqué (voir
      // critère d'acceptation MEZ-91) même si aucun billet n'a pu être
      // résolu côté serveur.
      await _local.recordOutcome(
        operationUuid: operationUuid,
        departureUuid: departureUuid,
        qrPayload: qrPayload,
        shortCode: shortCode,
        outcome: outcome,
        localRecordedAt: localRecordedAt,
        synced: true,
      );
      return Result<BoardingOutcome>.success(outcome);
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) {
        return Result<BoardingOutcome>.success(
          await _queueOffline(
            operationUuid: operationUuid,
            departureUuid: departureUuid,
            qrPayload: qrPayload,
            shortCode: shortCode,
            localRecordedAt: localRecordedAt,
          ),
        );
      }
      return const Result<BoardingOutcome>.failure(
        ServerFailure('Le serveur est momentanément indisponible.'),
      );
    }
  }

  @override
  Future<int> pendingCount(String departureUuid) =>
      _local.pendingCount(departureUuid);

  @override
  Future<List<PendingBoardingOperation>> listPending() => _local.listPending();

  @override
  Future<int> totalPendingCount() => _local.totalPendingCount();

  @override
  Future<void> applySyncResult({
    required String operationUuid,
    required BoardingOutcome outcome,
    required DateTime serverRecordedAt,
  }) =>
      _local.markSynced(
        operationUuid: operationUuid,
        outcome: outcome,
        serverRecordedAt: serverRecordedAt,
      );

  @override
  Future<Result<Null>> reverse({
    required String boardingEventUuid,
    required String reason,
  }) async {
    try {
      await _remote.reverse(
          boardingEventUuid: boardingEventUuid, reason: reason);
      return const Result<Null>.success(null);
    } on BoardingApiException catch (error) {
      return Result<Null>.failure(ValidationFailure(error.message));
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) {
        return const Result<Null>.failure(
          NetworkFailure(
            'Aucun réseau — réessayez l’annulation une fois en ligne.',
          ),
        );
      }
      return const Result<Null>.failure(
        ServerFailure('Le serveur est momentanément indisponible.'),
      );
    }
  }

  Future<BoardingOutcome> _queueOffline({
    required String operationUuid,
    required String departureUuid,
    String? qrPayload,
    String? shortCode,
    required DateTime localRecordedAt,
  }) async {
    final BoardingOutcome outcome = BoardingOutcome(
      status: BoardingOutcomeStatus.pendingSync,
      message: BoardingOutcomeStatus.pendingSync.defaultMessage,
      operationUuid: operationUuid,
    );
    await _local.recordOutcome(
      operationUuid: operationUuid,
      departureUuid: departureUuid,
      qrPayload: qrPayload,
      shortCode: shortCode,
      outcome: outcome,
      localRecordedAt: localRecordedAt,
      synced: false,
    );
    return outcome;
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
}
