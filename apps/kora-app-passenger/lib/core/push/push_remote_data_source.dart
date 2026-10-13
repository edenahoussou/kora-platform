import 'package:dio/dio.dart';

import '../constants/api_constants.dart';
import '../network/api_json.dart';
import 'firebase_configuration.dart';

/// Couche DATA des notifications push : les deux appels que l'app fait
/// au serveur, et rien d'autre.
///
/// Comme les autres sources distantes, celle-ci laisse remonter les
/// `DioException` : c'est [PushMessaging] qui décide quoi en faire.
class PushRemoteDataSource {
  const PushRemoteDataSource(this._dio);

  final Dio _dio;

  /// La configuration Firebase de la plateforme courante.
  ///
  /// Authentifié : à appeler une fois la session ouverte.
  Future<FirebaseConfiguration> configuration(String platform) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.passengerFirebaseConfiguration(platform),
    );

    return FirebaseConfiguration.fromJson(ApiJson.map(_payload(response.data)));
  }

  /// Inscrit le jeton FCM de cet appareil, ou l'efface.
  ///
  /// `token` nul vaut effacement — c'est ce qu'on envoie à la
  /// déconnexion, pour que le serveur cesse d'écrire à un appareil qui
  /// n'est plus le nôtre.
  ///
  /// Le serveur refuse l'inscription (422) dans deux cas qu'il faut lire
  /// plutôt que subir : la préférence « notifications push » du compte
  /// est désactivée, ou son intégration Firebase n'est pas configurée.
  /// Il répond 404 si l'appareil n'est plus actif.
  ///
  /// Rend l'état retenu par le serveur, qui fait foi.
  Future<bool> savePushToken({
    required String deviceUuid,
    required String? token,
  }) async {
    final enabled = token != null && token.isNotEmpty;

    final response = await _dio.put<dynamic>(
      ApiConstants.devicePushToken(deviceUuid),
      data: {'enabled': enabled, if (enabled) 'push_token': token},
    );

    return ApiJson.map(_payload(response.data))['enabled'] == true;
  }
}

/// Déballe l'enveloppe `{ "data": … }` quand elle est là.
Object? _payload(Object? body) {
  if (body is Map && body['data'] != null) return body['data'];
  return body;
}
