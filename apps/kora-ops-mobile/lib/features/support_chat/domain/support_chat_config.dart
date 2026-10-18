import 'package:equatable/equatable.dart';

/// Configuration publique du chat support tawk.to, telle que servie par
/// `GET /api/v1/auth/passenger/support-chat-configuration` (même contrat
/// que l'app passager — aucun secret, le `secure_hash` est un HMAC de
/// l'email calculé côté serveur).
class SupportChatConfig extends Equatable {
  const SupportChatConfig({
    required this.propertyId,
    required this.widgetId,
    required this.directChatUrl,
    this.secureHash,
    this.visitorName,
    this.visitorEmail,
  });

  final String propertyId;
  final String widgetId;
  final String directChatUrl;
  final String? secureHash;
  final String? visitorName;
  final String? visitorEmail;

  @override
  List<Object?> get props => <Object?>[
        propertyId,
        widgetId,
        directChatUrl,
        secureHash,
        visitorName,
        visitorEmail,
      ];
}
