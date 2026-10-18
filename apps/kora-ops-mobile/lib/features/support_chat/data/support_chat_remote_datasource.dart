import 'package:dio/dio.dart';

import '../../../core/constants/api_constants.dart';
import '../domain/support_chat_config.dart';

abstract class SupportChatRemoteDataSource {
  /// `null` si le chat est désactivé ou non configuré côté serveur.
  /// Lève une [DioException] en cas d'erreur réseau/serveur.
  Future<SupportChatConfig?> fetchConfig();
}

class SupportChatRemoteDataSourceImpl implements SupportChatRemoteDataSource {
  SupportChatRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<SupportChatConfig?> fetchConfig() async {
    final Response<dynamic> response =
        await _dio.get<dynamic>(ApiConstants.supportChatConfiguration);

    final Object? body = response.data;
    final Object? raw = body is Map ? body['data'] : null;
    if (raw is! Map || raw['enabled'] != true) return null;

    final String? propertyId = raw['property_id'] as String?;
    final String? widgetId = raw['widget_id'] as String?;
    if (propertyId == null || propertyId.isEmpty) return null;
    if (widgetId == null || widgetId.isEmpty) return null;

    final Object? visitor = raw['visitor'];

    return SupportChatConfig(
      propertyId: propertyId,
      widgetId: widgetId,
      directChatUrl: raw['direct_chat_url'] as String? ??
          'https://tawk.to/chat/$propertyId/$widgetId',
      secureHash: raw['secure_hash'] as String?,
      visitorName: visitor is Map ? visitor['name'] as String? : null,
      visitorEmail: visitor is Map ? visitor['email'] as String? : null,
    );
  }
}
