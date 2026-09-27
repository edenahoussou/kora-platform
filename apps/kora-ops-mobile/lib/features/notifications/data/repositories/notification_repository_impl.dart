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
import '../../domain/entities/ops_notification.dart';
import '../../domain/repositories/notification_repository.dart';
import '../datasources/notification_local_datasource.dart';
import '../datasources/notification_remote_datasource.dart';

class NotificationRepositoryImpl implements NotificationRepository {
  NotificationRepositoryImpl(this._remote, this._local);

  final NotificationRemoteDataSource _remote;
  final NotificationLocalDataSource _local;

  @override
  Future<Result<List<OpsNotification>>> list() async {
    try {
      final List<OpsNotification> fetched = await _remote.list();
      await _local.upsertAll(fetched);
      return Result<List<OpsNotification>>.success(await _local.readCache());
    } on DioException catch (error) {
      // Hors ligne (ou serveur momentanément indisponible) : on retombe
      // sur le cache plutôt que d'afficher une erreur — voir critère
      // MEZ-97 "restaurer les alertes non acquittées après redémarrage",
      // qui vaut aussi pour une simple coupure en cours d'usage.
      final List<OpsNotification> cached = await _local.readCache();
      if (cached.isNotEmpty || _isConnectivityFailure(error)) {
        return Result<List<OpsNotification>>.success(cached);
      }
      return Result<List<OpsNotification>>.failure(_failureOf(error));
    }
  }

  @override
  Future<List<OpsNotification>> readCache() => _local.readCache();

  @override
  Future<Result<List<OpsNotification>>> listSent() async {
    try {
      return Result<List<OpsNotification>>.success(await _remote.listSent());
    } on DioException catch (error) {
      return Result<List<OpsNotification>>.failure(_failureOf(error));
    }
  }

  @override
  Future<OpsNotificationCounts> unreadCounts() async {
    try {
      return await _remote.unreadCount();
    } on DioException {
      return OpsNotificationCounts(
        unread: await _local.unreadCountLocal(),
        criticalUnread: await _local.criticalUnreadCountLocal(),
      );
    }
  }

  @override
  Future<void> markRead(String uuid) async {
    final DateTime now = DateTime.now();
    await _local.markReadLocally(uuid, now);
    try {
      await _remote.markRead(uuid);
      await _local.confirmSynced(uuid);
    } on DioException {
      // Reste marqué "lu" localement avec `mark_read_pending=1` — réémis
      // par `retryPendingReads()` au prochain retour réseau (voir critère
      // MEZ-97 "conserver une trace des actions locales").
    }
  }

  @override
  Future<void> markAllRead() async {
    final DateTime now = DateTime.now();
    final List<String> uuidsBefore = (await _local.readCache())
        .where((OpsNotification n) => !n.isRead)
        .map((OpsNotification n) => n.uuid)
        .toList();
    await _local.markAllReadLocally(now);
    try {
      await _remote.markAllRead();
      for (final String uuid in uuidsBefore) {
        await _local.confirmSynced(uuid);
      }
    } on DioException {
      // Idem markRead : reste en attente, réémis par retryPendingReads().
    }
  }

  @override
  Future<void> retryPendingReads() async {
    final List<String> pending = await _local.pendingMarkReadUuids();
    for (final String uuid in pending) {
      try {
        await _remote.markRead(uuid);
        await _local.confirmSynced(uuid);
      } on DioException {
        // Toujours hors ligne ou échec serveur : on retentera au prochain
        // retour réseau, ce marquage reste "en attente".
      }
    }
  }

  @override
  Future<void> clearCache() => _local.clearAll();

  bool _isConnectivityFailure(DioException error) =>
      error.error is NetworkApiException ||
      error.error is TimeoutApiException ||
      error.type == DioExceptionType.connectionError ||
      error.type == DioExceptionType.connectionTimeout;

  Failure _failureOf(DioException error) {
    final apiError = error.error;
    final message = apiError is ApiException ? apiError.message : null;

    return switch (apiError) {
      UnauthorizedApiException() =>
        AuthenticationFailure(message ?? 'Authentification requise.'),
      ForbiddenApiException() =>
        AuthenticationFailure(message ?? 'Accès refusé.'),
      NetworkApiException() => const NetworkFailure(),
      TimeoutApiException() =>
        const NetworkFailure('Le serveur met trop de temps à répondre.'),
      ServerApiException() =>
        ServerFailure(message ?? 'Le serveur est momentanément indisponible.'),
      _ => ServerFailure(message ?? 'Impossible de charger les notifications.'),
    };
  }
}
