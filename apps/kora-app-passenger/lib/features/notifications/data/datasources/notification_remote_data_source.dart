import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/notification_feed.dart';
import '../models/notification_model.dart';

/// Couche DATA : les trois appels du centre de notifications.
///
/// Comme les autres sources distantes, celle-ci laisse remonter les
/// `DioException` : c'est le dépôt qui les traduit en échecs métier.
class NotificationRemoteDataSource {
  const NotificationRemoteDataSource(this._dio);

  final Dio _dio;

  Future<NotificationFeed> fetch({int page = 1, int perPage = 20}) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.passengerNotifications(page: page, perPage: perPage),
    );

    final body = response.data;
    final meta = ApiJson.map(body is Map ? body['meta'] : null);

    return NotificationFeed(
      notifications: [
        for (final json in ApiJson.list(body)) NotificationModel.fromJson(json),
      ],
      unreadCount: ApiJson.intOrNull(meta['unread_count']) ?? 0,
      page: ApiJson.intOrNull(meta['current_page']) ?? page,
      lastPage: ApiJson.intOrNull(meta['last_page']) ?? page,
    );
  }

  Future<void> markRead(String uuid) =>
      _dio.post<dynamic>(ApiConstants.passengerNotificationRead(uuid));

  Future<void> markAllRead() =>
      _dio.post<dynamic>(ApiConstants.passengerNotificationsReadAll);
}
