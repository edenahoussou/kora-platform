import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show NetworkApiException, TimeoutApiException;
import 'package:uuid/uuid.dart';

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../domain/entities/passenger_correction.dart';
import '../../domain/repositories/passenger_correction_repository.dart';
import '../datasources/manifeste_local_datasource.dart';
import '../datasources/passenger_correction_local_datasource.dart';
import '../datasources/passenger_correction_remote_datasource.dart';

/// Couche DATA : envoi immédiat de la correction ; si le réseau est
/// injoignable, file locale rejouée plus tard (`flushPending`). Dans les
/// deux cas, le cache local du manifeste est mis à jour tout de suite pour
/// que la correction survive à un redémarrage à froid.
class PassengerCorrectionRepositoryImpl
    implements PassengerCorrectionRepository {
  PassengerCorrectionRepositoryImpl(
    this._remote,
    this._queue,
    this._manifestCache, {
    Uuid? uuid,
  }) : _uuid = uuid ?? const Uuid();

  final PassengerCorrectionRemoteDataSource _remote;
  final PassengerCorrectionLocalDataSource _queue;
  final ManifesteLocalDataSource _manifestCache;
  final Uuid _uuid;

  @override
  Future<Result<PassengerCorrectionOutcome>> correct({
    required String departureUuid,
    required String ticketUuid,
    required PassengerCorrection correction,
    String? reason,
  }) async {
    if (correction.isEmpty) {
      return const Result<PassengerCorrectionOutcome>.success(
          PassengerCorrectionOutcome.saved);
    }

    try {
      await _remote.send(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        changes: correction.changes,
        reason: reason,
      );
      await _applyToCache(departureUuid, ticketUuid, correction);
      return const Result<PassengerCorrectionOutcome>.success(
          PassengerCorrectionOutcome.saved);
    } on PassengerCorrectionRejectedException catch (error) {
      return Result<PassengerCorrectionOutcome>.failure(
          ValidationFailure(error.message));
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) {
        await _queue.enqueue(
          operationUuid: _uuid.v4(),
          departureUuid: departureUuid,
          ticketUuid: ticketUuid,
          changes: correction.changes,
          reason: reason,
        );
        await _applyToCache(departureUuid, ticketUuid, correction);
        return const Result<PassengerCorrectionOutcome>.success(
            PassengerCorrectionOutcome.pendingSync);
      }
      return const Result<PassengerCorrectionOutcome>.failure(
        ServerFailure('Le serveur est momentanément indisponible.'),
      );
    }
  }

  @override
  Future<int> flushPending(String departureUuid) async {
    final List<PendingPassengerCorrection> pending =
        await _queue.pending(departureUuid);
    int sent = 0;

    for (final PendingPassengerCorrection op in pending) {
      try {
        await _remote.send(
          departureUuid: op.departureUuid,
          ticketUuid: op.ticketUuid,
          changes: op.changes,
          reason: op.reason,
        );
        await _queue.remove(op.operationUuid);
        sent++;
      } on PassengerCorrectionRejectedException catch (error) {
        await _queue.markRejected(op.operationUuid, error.message);
      } on DioException {
        // Réseau toujours indisponible ou serveur en panne : on conserve
        // l'ordre des corrections et on réessaiera au prochain passage.
        break;
      }
    }
    return sent;
  }

  @override
  Future<Set<String>> pendingTicketUuids(String departureUuid) async =>
      (await _queue.pending(departureUuid))
          .map((PendingPassengerCorrection op) => op.ticketUuid)
          .toSet();

  @override
  Future<List<Passenger>> overlayPending(
      String departureUuid, List<Passenger> passengers) async {
    final List<PendingPassengerCorrection> pending =
        await _queue.pending(departureUuid);
    if (pending.isEmpty) return passengers;

    final Map<String, Passenger> byId = <String, Passenger>{
      for (final Passenger p in passengers) p.id: p,
    };
    for (final PendingPassengerCorrection op in pending) {
      final Passenger? current = byId[op.ticketUuid];
      if (current == null) continue;
      byId[op.ticketUuid] = current.withCorrection(
        PassengerCorrection(op.changes),
      );
    }
    return passengers.map((Passenger p) => byId[p.id] ?? p).toList();
  }

  Future<void> _applyToCache(String departureUuid, String ticketUuid,
      PassengerCorrection correction) async {
    final CachedManifest? cached =
        await _manifestCache.readCache(departureUuid);
    if (cached == null) return;
    for (final Passenger p in cached.passengers) {
      if (p.id != ticketUuid) continue;
      await _manifestCache.updateCachedPassenger(
        departureUuid: departureUuid,
        passenger: p.withCorrection(correction),
      );
      return;
    }
  }

  bool _isNetworkUnreachable(DioException error) {
    final Object? apiError = error.error;
    return apiError is NetworkApiException ||
        apiError is TimeoutApiException ||
        error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.sendTimeout;
  }
}
