import '../../../../core/network/api_json.dart';
import '../../domain/entities/support_chat_configuration.dart';

/// Couche DATA : la configuration du chat telle que le serveur la rend.
///
/// Quand l'intégration est éteinte, la réponse se réduit à
/// `{"enabled": false}` — tous les autres champs manquent, et c'est
/// normal. D'où les lectures tolérantes : une configuration incomplète
/// doit se traduire par « pas de chat », jamais par une erreur.
abstract final class SupportChatConfigurationModel {
  static SupportChatConfiguration fromJson(Map<String, dynamic> json) =>
      SupportChatConfiguration(
        enabled: json['enabled'] == true,
        propertyId: ApiJson.string(json['property_id']),
        widgetId: ApiJson.string(json['widget_id']),
        directChatUrl: ApiJson.string(json['direct_chat_url']),
        secureHash: ApiJson.stringOrNull(json['secure_hash']),
      );
}
