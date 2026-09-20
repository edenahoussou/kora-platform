import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show
        ApiException,
        ForbiddenApiException,
        NetworkApiException,
        ServerApiException,
        TimeoutApiException,
        UnauthorizedApiException;

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/incident.dart';
import '../../domain/repositories/incident_repository.dart';
import '../datasources/incident_local_datasource.dart';
import '../datasources/incident_remote_datasource.dart';

class IncidentRepositoryImpl implements IncidentRepository {
  IncidentRepositoryImpl(this._remote, this._local);

  final IncidentRemoteDataSource _remote;
  final IncidentLocalDataSource _local;

  @override
  Future<Result<Incident>> report({
    required String operationUuid,
    required String departureUuid,
    required IncidentType type,
    required String description,
    required DateTime localRecordedAt,
    double? latitude,
    double? longitude,
  }) async {
    try {
      await _remote.report(
        operationUuid: operationUuid,
        departureUuid: departureUuid,
        type: type,
        description: description,
        occurredAt: localRecordedAt,
        latitude: latitude,
        longitude: longitude,
      );
      final Incident incident = Incident(
        operationUuid: operationUuid,
        departureUuid: departureUuid,
        type: type,
        description: description,
        occurredAt: localRecordedAt,
        latitude: latitude,
        longitude: longitude,
        pendingSync: false,
      );
      await _local.record(incident, synced: true);
      return Result<Incident>.success(incident);
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) {
        final Incident incident = Incident(
          operationUuid: operationUuid,
          departureUuid: departureUuid,
          type: type,
          description: description,
          occurredAt: localRecordedAt,
          latitude: latitude,
          longitude: longitude,
          pendingSync: true,
        );
        await _local.record(incident, synced: false);
        return Result<Incident>.success(incident);
      }
      return Result<Incident>.failure(_failureOf(error));
    }
  }

  @override
  Future<int> totalPendingCount() => _local.totalPendingCount();

  @override
  Future<void> retryPending() async {
    final List<Incident> pending = await _local.listPending();
    for (final Incident incident in pending) {
      try {
        await _remote.report(
          operationUuid: incident.operationUuid,
          departureUuid: incident.departureUuid,
          type: incident.type,
          description: incident.description,
          occurredAt: incident.occurredAt,
          latitude: incident.latitude,
          longitude: incident.longitude,
        );
        await _local.markSynced(incident.operationUuid);
      } on DioException {
        // Toujours hors ligne ou échec serveur : reste en attente, on
        // retentera au prochain retour réseau.
      }
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
      ForbiddenApiException() =>
        AuthenticationFailure(message ?? 'Ce départ ne vous est pas affecté.'),
      NetworkApiException() => const NetworkFailure(),
      TimeoutApiException() =>
        const NetworkFailure('Le serveur met trop de temps à répondre.'),
      ServerApiException() =>
        ServerFailure(message ?? 'Le serveur est momentanément indisponible.'),
      _ => ServerFailure(message ?? 'Impossible de signaler l\'incident.'),
    };
  }
}
