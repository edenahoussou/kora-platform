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
import '../../domain/entities/manifest_snapshot.dart';
import '../../domain/repositories/manifeste_repository.dart';
import '../datasources/manifeste_local_datasource.dart';
import '../datasources/manifeste_remote_datasource.dart';

class ManifesteRepositoryImpl implements ManifesteRepository {
  ManifesteRepositoryImpl(
    this._remote,
    this._local, {
    Future<bool> Function()? isOnline,
  }) : _isOnline = isOnline ?? (() async => true);

  final ManifesteRemoteDataSource _remote;
  final ManifesteLocalDataSource _local;

  /// Vérifié avant toute tentative réseau plutôt que de laisser l'appel
  /// échouer puis interpréter l'exception — évite un aller-retour raté
  /// (et son délai) quand on sait déjà qu'il n'y a pas de réseau. Par
  /// défaut toujours "en ligne" : c'est `ManifesteRepositoryImpl`
  /// construit par l'injection (voir injection.dart) qui branche l'état
  /// réel via `SyncCubit`.
  final Future<bool> Function() _isOnline;

  @override
  Future<Result<ManifestSnapshot>> load(String departureUuid) async {
    final CachedManifest? cached = await _local.readCache(departureUuid);

    if (!await _isOnline()) {
      if (cached != null) {
        return Result<ManifestSnapshot>.success(ManifestSnapshot(
          passengers: cached.passengers,
          version: cached.version,
          fetchedAt: cached.fetchedAt,
          isStale: true,
          isFromCache: true,
        ));
      }
      return const Result<ManifestSnapshot>.failure(
        NetworkFailure(
          'Pas de connexion et aucun manifeste en cache pour ce départ.',
        ),
      );
    }

    try {
      if (cached == null) {
        return Result<ManifestSnapshot>.success(
            await _fetchFresh(departureUuid));
      }

      final ManifestVersionCheck check =
          await _remote.checkVersion(departureUuid, cached.version);

      if (!check.accessible) {
        return const Result<ManifestSnapshot>.failure(
          AuthenticationFailure('Ce départ ne vous est plus affecté.'),
        );
      }

      if (check.stale) {
        return Result<ManifestSnapshot>.success(
            await _fetchFresh(departureUuid));
      }

      // Vérifié à jour : on sert le cache tel quel, sans le
      // retélécharger — voir docstring `ManifesteRepository.load`.
      return Result<ManifestSnapshot>.success(ManifestSnapshot(
        passengers: cached.passengers,
        version: check.currentVersion ?? cached.version,
        fetchedAt: cached.fetchedAt,
        isStale: false,
        isFromCache: true,
      ));
    } on DioException catch (error) {
      if (_isNetworkUnreachable(error) && cached != null) {
        // La vérification de version elle-même a échoué (réseau
        // instable) alors qu'on se croyait en ligne — on retombe sur le
        // cache plutôt que de bloquer l'agent, marqué non vérifié.
        return Result<ManifestSnapshot>.success(ManifestSnapshot(
          passengers: cached.passengers,
          version: cached.version,
          fetchedAt: cached.fetchedAt,
          isStale: true,
          isFromCache: true,
        ));
      }
      return Result<ManifestSnapshot>.failure(_failureOf(error));
    }
  }

  @override
  Future<void> persistStatusUpdate({
    required String departureUuid,
    required String ticketUuid,
    required BoardingStatus statut,
  }) =>
      _local.updateCachedStatus(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        statut: statut,
      );

  Future<ManifestSnapshot> _fetchFresh(String departureUuid) async {
    final ManifesteFetchResult fetched = await _remote.fetch(departureUuid);
    await _local.replaceCache(
      departureUuid,
      fetched.passengers,
      manifestVersion: fetched.manifestVersion,
    );
    return ManifestSnapshot(
      passengers: fetched.passengers,
      version: fetched.manifestVersion,
      fetchedAt: DateTime.now(),
      isStale: false,
      isFromCache: false,
    );
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
