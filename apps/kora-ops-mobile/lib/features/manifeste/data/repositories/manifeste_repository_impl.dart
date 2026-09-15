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
import '../../../ops/domain/entities/passenger.dart';
import '../../domain/repositories/manifeste_repository.dart';
import '../datasources/manifeste_local_datasource.dart';
import '../datasources/manifeste_remote_datasource.dart';

class ManifesteRepositoryImpl implements ManifesteRepository {
  ManifesteRepositoryImpl(this._remote, this._local);

  final ManifesteRemoteDataSource _remote;
  final ManifesteLocalDataSource _local;

  @override
  Future<Result<List<Passenger>>> load(String departureUuid) async {
    try {
      final ManifesteFetchResult fetched = await _remote.fetch(departureUuid);
      await _local.replaceCache(
        departureUuid,
        fetched.passengers,
        manifestVersion: fetched.manifestVersion,
      );
      return Result<List<Passenger>>.success(fetched.passengers);
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error)) {
        final List<Passenger> cached = await _local.readCache(departureUuid);
        if (cached.isNotEmpty) {
          return Result<List<Passenger>>.success(cached);
        }
        return const Result<List<Passenger>>.failure(
          NetworkFailure(
            'Pas de connexion et aucun manifeste en cache pour ce départ.',
          ),
        );
      }
      return Result<List<Passenger>>.failure(_failureOf(error));
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
        AuthenticationFailure(message ?? 'Accès refusé à ce départ.'),
      ServerApiException() =>
        ServerFailure(message ?? 'Le serveur est momentanément indisponible.'),
      _ => ServerFailure(message ?? 'Impossible de charger le manifeste.'),
    };
  }
}
