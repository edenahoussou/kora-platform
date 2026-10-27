import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show
        ApiException,
        ForbiddenApiException,
        NetworkApiException,
        ServerApiException,
        TimeoutApiException,
        UnauthorizedApiException;
import 'package:uuid/uuid.dart';

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/trip_log.dart';
import '../../domain/repositories/trip_log_repository.dart';
import '../datasources/trip_log_local_datasource.dart';
import '../datasources/trip_log_remote_datasource.dart';
import '../models/trip_log_dtos.dart';

class TripLogRepositoryImpl implements TripLogRepository {
  TripLogRepositoryImpl(this._remote, this._local);

  final TripLogRemoteDataSource _remote;
  final TripLogLocalDataSource _local;

  @override
  Future<TripLog> cached(String departureUuid) async {
    final Map<String, dynamic>? json = await _local.readCache(departureUuid);
    final TripLog base =
        json == null ? TripLog.empty(departureUuid) : TripLogDtos.tripLog(json);
    return _withPending(base, departureUuid);
  }

  @override
  Future<Result<TripLog>> load(String departureUuid) async {
    try {
      final Map<String, dynamic> json = await _remote.fetch(departureUuid);
      await _local.writeCache(departureUuid, json);
      return Result<TripLog>.success(
        await _withPending(TripLogDtos.tripLog(json), departureUuid),
      );
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) {
        return Result<TripLog>.success(await cached(departureUuid));
      }
      return Result<TripLog>.failure(
        _failureOf(error, 'Impossible de charger le carnet de bord.'),
      );
    }
  }

  @override
  Future<Result<TripLog>> recordOdometer({
    required String departureUuid,
    required OdometerKind kind,
    required int valueKm,
    DateTime? recordedAt,
  }) async {
    final DateTime at = recordedAt ?? DateTime.now();
    final String operationUuid = const Uuid().v4();

    // Contrôle local (le serveur reste l'arbitre) : arrivée < départ.
    final TripLog current = await cached(departureUuid);
    final int? startKm = kind == OdometerKind.end
        ? current.start?.valueKm
        : valueKm;
    final int? endKm = kind == OdometerKind.start
        ? current.end?.valueKm
        : valueKm;
    if (startKm != null && endKm != null && endKm < startKm) {
      return Result<TripLog>.failure(ValidationFailure(
        'Le kilométrage d\'arrivée ($endKm km) ne peut pas être inférieur '
        'à celui du départ ($startKm km).',
      ));
    }

    try {
      final Map<String, dynamic> json = await _remote.recordOdometer(
        departureUuid: departureUuid,
        kind: kind,
        valueKm: valueKm,
        recordedAt: at,
        clientUuid: operationUuid,
      );
      await _local.writeCache(departureUuid, json);
      return Result<TripLog>.success(
        await _withPending(TripLogDtos.tripLog(json), departureUuid),
      );
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) {
        await _local.enqueueOdometer(
          operationUuid: operationUuid,
          departureUuid: departureUuid,
          kind: kind,
          valueKm: valueKm,
          recordedAt: at,
        );
        return Result<TripLog>.success(await cached(departureUuid));
      }
      return Result<TripLog>.failure(
        _failureOf(error, 'Impossible d\'enregistrer le kilométrage.'),
      );
    }
  }

  @override
  Future<Result<TripExpense>> submitExpense(TripExpenseDraft draft) async {
    try {
      final Map<String, dynamic> json = await _remote.submitExpense(draft);
      await _local.deletePhoto(draft.photoPath);
      final TripExpense expense = TripLogDtos.expense(json);
      await _refreshCacheQuietly(draft.departureUuid);
      return Result<TripExpense>.success(expense);
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) {
        await _local.enqueueExpense(draft);
        return Result<TripExpense>.success(draft.toPendingExpense());
      }
      return Result<TripExpense>.failure(
        _failureOf(error, 'Impossible d\'enregistrer la dépense.'),
      );
    }
  }

  @override
  Future<String> persistPhoto(String sourcePath, String clientUuid) =>
      _local.persistPhoto(sourcePath, clientUuid);

  @override
  Future<int> totalPendingCount() => _local.totalPendingCount();

  @override
  Future<void> retryPending() async {
    final List<PendingTripLogOperation> pending = await _local.listPending();
    final Set<String> touched = <String>{};

    for (final PendingTripLogOperation op in pending) {
      try {
        if (op.isOdometer) {
          final Map<String, dynamic> json = await _remote.recordOdometer(
            departureUuid: op.departureUuid,
            kind: OdometerKind.fromValue(op.payload['kind'] as String?),
            valueKm: (op.payload['value_km'] as num?)?.toInt() ?? 0,
            recordedAt:
                DateTime.tryParse(op.payload['recorded_at'] as String? ?? '') ??
                    op.createdAt,
            clientUuid: op.operationUuid,
          );
          await _local.writeCache(op.departureUuid, json);
        } else {
          final TripExpenseDraft draft = TripLogDtos.draftFromJson(
            op.payload,
            photoPath: op.photoPath,
          );
          await _remote.submitExpense(draft);
          await _local.deletePhoto(op.photoPath);
          touched.add(op.departureUuid);
        }
        await _local.markSynced(op.operationUuid);
      } on DioException catch (error) {
        if (_isNetworkUnreachable(error) || _isRetryable(error)) {
          // Toujours hors ligne ou serveur indisponible : on retentera.
          continue;
        }
        // Refus définitif (validation, départ plus affecté…) : sortie de
        // la file pour ne pas bloquer les suivantes.
        final Object? apiError = error.error;
        final String? apiMessage =
            apiError is ApiException ? apiError.message : null;
        await _local.markFailed(
          op.operationUuid,
          apiMessage ?? error.message ?? 'Refusé par le serveur.',
        );
      } on Object {
        // Photo introuvable, payload illisible… : on n'insiste pas.
        await _local.markFailed(op.operationUuid, 'Saisie locale illisible.');
      }
    }

    for (final String departureUuid in touched) {
      await _refreshCacheQuietly(departureUuid);
    }
  }

  /// Fusionne les saisies encore en attente d'envoi dans [base] : un
  /// relevé en attente remplace celui du serveur, une dépense en attente
  /// est ajoutée en tête (sauf si le serveur la connaît déjà).
  Future<TripLog> _withPending(TripLog base, String departureUuid) async {
    final List<PendingTripLogOperation> pending =
        await _local.listPending(departureUuid: departureUuid);
    if (pending.isEmpty) return base;

    OdometerReading? start = base.start;
    OdometerReading? end = base.end;
    final List<TripExpense> pendingExpenses = <TripExpense>[];
    final Set<String> known =
        base.expenses.map((TripExpense e) => e.clientUuid).toSet();

    for (final PendingTripLogOperation op in pending) {
      if (op.isOdometer) {
        final OdometerKind kind =
            OdometerKind.fromValue(op.payload['kind'] as String?);
        final OdometerReading reading = OdometerReading(
          kind: kind,
          valueKm: (op.payload['value_km'] as num?)?.toInt() ?? 0,
          recordedAt:
              DateTime.tryParse(op.payload['recorded_at'] as String? ?? '') ??
                  op.createdAt,
          pendingSync: true,
        );
        if (kind == OdometerKind.start) {
          start = reading;
        } else {
          end = reading;
        }
      } else if (!known.contains(op.operationUuid)) {
        pendingExpenses.add(
          TripLogDtos.draftFromJson(op.payload, photoPath: op.photoPath)
              .toPendingExpense(),
        );
      }
    }

    return TripLog(
      departureUuid: base.departureUuid.isEmpty
          ? departureUuid
          : base.departureUuid,
      busRegistration: base.busRegistration,
      lastKnownOdometerKm: base.lastKnownOdometerKm,
      start: start,
      end: end,
      expenses: <TripExpense>[...pendingExpenses.reversed, ...base.expenses],
    );
  }

  Future<void> _refreshCacheQuietly(String departureUuid) async {
    try {
      await _local.writeCache(
          departureUuid, await _remote.fetch(departureUuid));
    } on Object {
      // Le cache sera rafraîchi au prochain affichage.
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

  /// 5xx, 401 (session à rouvrir), 408/429 : réessayable plus tard.
  bool _isRetryable(DioException error) {
    final Object? apiError = error.error;
    if (apiError is ServerApiException ||
        apiError is UnauthorizedApiException) {
      return true;
    }
    final int? status = error.response?.statusCode;
    return status == null ||
        status >= 500 ||
        status == 401 ||
        status == 408 ||
        status == 419 ||
        status == 429;
  }

  Failure _failureOf(DioException error, String fallback) {
    final Object? apiError = error.error;
    final String? message = apiError is ApiException ? apiError.message : null;
    final int? status = error.response?.statusCode;

    return switch (apiError) {
      UnauthorizedApiException() =>
        AuthenticationFailure(message ?? 'Authentification requise.'),
      ForbiddenApiException() =>
        AuthenticationFailure(message ?? 'Ce départ ne vous est pas affecté.'),
      NetworkApiException() => const NetworkFailure(),
      TimeoutApiException() =>
        const NetworkFailure('Le serveur met trop de temps à répondre.'),
      ServerApiException() =>
        ServerFailure(message ?? 'Le serveur est momentanément indisponible.'),
      _ when status == 422 || status == 409 =>
        ValidationFailure(message ?? fallback),
      _ => ServerFailure(message ?? fallback),
    };
  }
}
