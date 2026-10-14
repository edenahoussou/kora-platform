import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/support_chat_configuration.dart';
import '../models/support_chat_configuration_model.dart';

/// Couche DATA : l'unique appel du chat support.
///
/// Comme les autres sources distantes, elle laisse remonter les
/// `DioException` : c'est le dépôt qui les traduit en échecs métier.
class SupportChatRemoteDataSource {
  const SupportChatRemoteDataSource(this._dio);

  final Dio _dio;

  Future<SupportChatConfiguration> configuration() async {
    final response = await _dio.get<dynamic>(
      ApiConstants.passengerSupportChatConfiguration,
    );

    return SupportChatConfigurationModel.fromJson(
      ApiJson.map(_payload(response.data)),
    );
  }
}

/// Déballe l'enveloppe `{ "data": … }` quand elle est là.
Object? _payload(Object? body) {
  if (body is Map && body['data'] != null) return body['data'];
  return body;
}
