import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show NetworkApiException, TimeoutApiException;
import 'package:uuid/uuid.dart';

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/pending_seat_release_operation.dart';
import '../../domain/entities/seat_release_outcome.dart';
import '../../domain/entities/seat_release_outcome_status.dart';
import '../../domain/repositories/seat_release_repository.dart';
import '../datasources/seat_release_local_datasource.dart';
import '../datasources/seat_release_remote_datasource.dart';

/// Couche DATA : anti-doublon local, appel réseau, file hors ligne — voir
/// docstring de `SeatReleaseRepository`.
class SeatReleaseRepositoryImpl implements SeatReleaseRepository {
  SeatReleaseRepositoryImpl(
    this._remote,
    this._local, {
    Uuid? uuid,
  }) : _uuid = uuid ?? const Uuid();

  final SeatReleaseRemoteDataSource _remote;
  final SeatReleaseLocalDataSource _local;
  final Uuid _uuid;

  @override
  Future<Result<SeatReleaseOutcome>> release({
    required String departureUuid,
    required String ticketUuid,
    required String actualStopUuid,
    required String operationUuid,
    required DateTime localRecordedAt,
  }) async {
    final SeatReleaseOutcome? cached = await _local.findConclusiveOutcome(
      departureUuid: departureUuid,
      ticketUuid: ticketUuid,
      type: SeatReleaseType.earlyDropOff,
    );
    if (cached != null) return Result<SeatReleaseOutcome>.success(cached);

    try {
      final SeatReleaseOutcome outcome = await _remote.release(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        actualStopUuid: actualStopUuid,
        operationUuid: operationUuid,
        localRecordedAt: localRecordedAt,
      );
      await _local.recordOutcome(
        operationUuid: operationUuid,
        type: SeatReleaseType.earlyDropOff,
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        actualStopUuid: actualStopUuid,
        outcome: outcome,
        localRecordedAt: localRecordedAt,
        synced: true,
      );
      return Result<SeatReleaseOutcome>.success(outcome);
    } on SeatReleaseApiException catch (error) {
      // ticket_unknown (404) : le seul cas non persisté côté serveur —
      // on l'enregistre quand même localement pour bloquer un re-essai
      // immédiat du même billet invalide (même principe que
      // BoardingRepositoryImpl pour qr_unknown).
      final SeatReleaseOutcome outcome = SeatReleaseOutcome(
        status: SeatReleaseOutcomeStatus.rejected,
        message: error.message,
        recommendedAction: error.recommendedAction,
      );
      await _local.recordOutcome(
        operationUuid: operationUuid,
        type: SeatReleaseType.earlyDropOff,
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        actualStopUuid: actualStopUuid,
        outcome: outcome,
        localRecordedAt: localRecordedAt,
        synced: true,
      );
      return Result<SeatReleaseOutcome>.success(outcome);
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) {
        return Result<SeatReleaseOutcome>.success(await _queueRelease(
          operationUuid: operationUuid,
          departureUuid: departureUuid,
          ticketUuid: ticketUuid,
          actualStopUuid: actualStopUuid,
          localRecordedAt: localRecordedAt,
        ));
      }
      return const Result<SeatReleaseOutcome>.failure(
        ServerFailure('Le serveur est momentanément indisponible.'),
      );
    }
  }

  @override
  Future<Result<List<SeatReleaseOutcome>>> closeNoShows({
    required String departureUuid,
    required List<String> uncontrolledTicketUuids,
    required String operationUuid,
    required DateTime localRecordedAt,
  }) async {
    if (uncontrolledTicketUuids.isEmpty) {
      return const Result<List<SeatReleaseOutcome>>.success(
          <SeatReleaseOutcome>[]);
    }

    try {
      final List<SeatReleaseOutcome> outcomes = await _remote.closeNoShows(
        departureUuid: departureUuid,
        operationUuid: operationUuid,
        localRecordedAt: localRecordedAt,
      );
      for (final SeatReleaseOutcome outcome in outcomes) {
        if (outcome.ticketUuid == null) continue;
        await _local.recordOutcome(
          // Le serveur ne renvoie pas d'operation_uuid par billet pour la
          // clôture groupée (un seul operation_uuid pour toute
          // l'opération) — on en dérive un stable localement, pour que
          // deux appels de cette méthode sur le même billet ne créent
          // jamais deux lignes distinctes.
          operationUuid: '$operationUuid:${outcome.ticketUuid}',
          type: SeatReleaseType.noShow,
          departureUuid: departureUuid,
          ticketUuid: outcome.ticketUuid!,
          outcome: outcome,
          localRecordedAt: localRecordedAt,
          synced: true,
        );
      }
      return Result<List<SeatReleaseOutcome>>.success(outcomes);
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) {
        return Result<List<SeatReleaseOutcome>>.success(
          await _queueNoShows(
            departureUuid: departureUuid,
            uncontrolledTicketUuids: uncontrolledTicketUuids,
            localRecordedAt: localRecordedAt,
          ),
        );
      }
      return const Result<List<SeatReleaseOutcome>>.failure(
        ServerFailure('Le serveur est momentanément indisponible.'),
      );
    }
  }

  @override
  Future<int> totalPendingCount() => _local.totalPendingCount();

  @override
  Future<List<PendingSeatReleaseOperation>> listPending() =>
      _local.listPending();

  @override
  Future<void> applySyncResult({
    required String operationUuid,
    required SeatReleaseOutcome outcome,
    required DateTime serverRecordedAt,
  }) =>
      _local.markSynced(
        operationUuid: operationUuid,
        outcome: outcome,
        serverRecordedAt: serverRecordedAt,
      );

  Future<SeatReleaseOutcome> _queueRelease({
    required String operationUuid,
    required String departureUuid,
    required String ticketUuid,
    required String actualStopUuid,
    required DateTime localRecordedAt,
  }) async {
    final SeatReleaseOutcome outcome = SeatReleaseOutcome(
      status: SeatReleaseOutcomeStatus.pendingSync,
      message: SeatReleaseOutcomeStatus.pendingSync.defaultMessage,
      operationUuid: operationUuid,
    );
    await _local.recordOutcome(
      operationUuid: operationUuid,
      type: SeatReleaseType.earlyDropOff,
      departureUuid: departureUuid,
      ticketUuid: ticketUuid,
      actualStopUuid: actualStopUuid,
      outcome: outcome,
      localRecordedAt: localRecordedAt,
      synced: false,
    );
    return outcome;
  }

  /// Hors ligne : chaque billet devient sa propre opération `boarding.
  /// no_show` en file — voir docstring `SeatReleaseRepository.
  /// closeNoShows`.
  Future<List<SeatReleaseOutcome>> _queueNoShows({
    required String departureUuid,
    required List<String> uncontrolledTicketUuids,
    required DateTime localRecordedAt,
  }) async {
    final List<SeatReleaseOutcome> outcomes = <SeatReleaseOutcome>[];
    for (final String ticketUuid in uncontrolledTicketUuids) {
      final SeatReleaseOutcome? cached = await _local.findConclusiveOutcome(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        type: SeatReleaseType.noShow,
      );
      if (cached != null) {
        outcomes.add(cached);
        continue;
      }

      final String perTicketOperationUuid = _uuid.v4();
      final SeatReleaseOutcome outcome = SeatReleaseOutcome(
        status: SeatReleaseOutcomeStatus.pendingSync,
        message: SeatReleaseOutcomeStatus.pendingSync.defaultMessage,
        ticketUuid: ticketUuid,
        operationUuid: perTicketOperationUuid,
      );
      await _local.recordOutcome(
        operationUuid: perTicketOperationUuid,
        type: SeatReleaseType.noShow,
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        outcome: outcome,
        localRecordedAt: localRecordedAt,
        synced: false,
      );
      outcomes.add(outcome);
    }
    return outcomes;
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
