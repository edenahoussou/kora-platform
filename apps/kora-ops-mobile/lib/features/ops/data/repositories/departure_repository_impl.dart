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
import '../../domain/entities/depart.dart';
import '../../domain/repositories/departure_repository.dart';
import '../datasources/departure_remote_datasource.dart';

class DepartureRepositoryImpl implements DepartureRepository {
  DepartureRepositoryImpl(this._remote);

  final DepartureRemoteDataSource _remote;

  @override
  Future<Result<List<Depart>>> listAssigned({
    String? date,
    String? status,
  }) async {
    try {
      final List<Depart> departs =
          await _remote.list(date: date, status: status);
      return Result<List<Depart>>.success(departs);
    } on DioException catch (error) {
      return Result<List<Depart>>.failure(_failureOf(error));
    }
  }

  @override
  Future<Result<Depart>> show(String departureUuid) async {
    try {
      final Depart depart = await _remote.show(departureUuid);
      return Result<Depart>.success(depart);
    } on DioException catch (error) {
      return Result<Depart>.failure(_failureOf(error));
    }
  }

  @override
  Future<Result<String>> updateStatus(
    String departureUuid,
    String status, {
    DateTime? occurredAt,
  }) async {
    try {
      final String updated = await _remote.updateStatus(
        departureUuid,
        status,
        occurredAt: occurredAt,
      );
      return Result<String>.success(updated);
    } on DioException catch (error) {
      return Result<String>.failure(_failureOf(error));
    }
  }

  @override
  Future<Result<void>> recordDelay(
    String departureUuid,
    int delayMinutes,
    String delayReason,
  ) async {
    try {
      await _remote.recordDelay(departureUuid, delayMinutes, delayReason);
      return const Result<void>.success(null);
    } on DioException catch (error) {
      return Result<void>.failure(_failureOf(error));
    }
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
      _ => ServerFailure(message ?? 'Impossible de charger les départs.'),
    };
  }
}
