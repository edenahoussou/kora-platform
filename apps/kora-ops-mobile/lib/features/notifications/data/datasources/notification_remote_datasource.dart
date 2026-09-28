import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/ops_notification.dart';
import '../../domain/repositories/notification_repository.dart';
import '../models/notification_dtos.dart';

abstract class NotificationRemoteDataSource {
  /// `GET /notifications` — page la plus récente (`per_page=50`, largement
  /// suffisant : le badge/la liste ne montrent jamais un historique
  /// profond côté terrain).
  Future<List<OpsNotification>> list();

  /// `GET /notifications/sent` — événements déclenchés par l'utilisateur
  /// lui-même.
  Future<List<OpsNotification>> listSent();

  /// `GET /notifications/unread-count`.
  Future<OpsNotificationCounts> unreadCount();

  /// `POST /notifications/{uuid}/read` — idempotent côté serveur.
  Future<void> markRead(String uuid);

  /// `POST /notifications/read-all`.
  Future<void> markAllRead();
}

class NotificationRemoteDataSourceImpl implements NotificationRemoteDataSource {
  NotificationRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<List<OpsNotification>> list() async {
    final response = await _dio.get<dynamic>(
      ApiConstants.notificationsIndex,
      queryParameters: const <String, dynamic>{'per_page': 50},
    );
    return NotificationDtos.list(response.data);
  }

  @override
  Future<List<OpsNotification>> listSent() async {
    final response = await _dio.get<dynamic>(
      ApiConstants.notificationsSentIndex,
      queryParameters: const <String, dynamic>{'per_page': 50},
    );
    return NotificationDtos.list(response.data);
  }

  @override
  Future<OpsNotificationCounts> unreadCount() async {
    final response = await _dio.get<dynamic>(
      ApiConstants.notificationsUnreadCount,
    );
    final counts = NotificationDtos.counts(response.data);
    return OpsNotificationCounts(
      unread: counts.unread,
      criticalUnread: counts.criticalUnread,
    );
  }

  @override
  Future<void> markRead(String uuid) async {
    await _dio.post<dynamic>(ApiConstants.notificationMarkRead(uuid));
  }

  @override
  Future<void> markAllRead() async {
    await _dio.post<dynamic>(ApiConstants.notificationsMarkAllRead);
  }
}
