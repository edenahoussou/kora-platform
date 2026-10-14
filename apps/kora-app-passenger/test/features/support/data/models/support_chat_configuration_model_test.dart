// La lecture de la configuration tawk.to.
//
// Le serveur rend deux formes très différentes selon que l'intégration
// est allumée ou non. La seconde — `{"enabled": false}` toute seule — est
// la plus importante à tenir : c'est celle qui arrive quand on coupe le
// chat depuis OPS, et elle ne doit surtout pas ressembler à une panne.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/support/data/models/support_chat_configuration_model.dart';

void main() {
  test('lit une intégration allumée', () {
    final configuration = SupportChatConfigurationModel.fromJson({
      'enabled': true,
      'property_id': 'abc123def456',
      'widget_id': 'default',
      'direct_chat_url': 'https://tawk.to/chat/abc123def456/default',
      'secure_hash': 'hmac-sha256-de-l-email',
    });

    expect(configuration.enabled, isTrue);
    expect(configuration.propertyId, 'abc123def456');
    expect(configuration.widgetId, 'default');
    expect(
      configuration.directChatUrl,
      'https://tawk.to/chat/abc123def456/default',
    );
    expect(configuration.secureHash, 'hmac-sha256-de-l-email');
    expect(configuration.isUsable, isTrue);
  });

  test('une intégration éteinte se réduit à son drapeau', () {
    final configuration = SupportChatConfigurationModel.fromJson({
      'enabled': false,
    });

    expect(configuration.enabled, isFalse);
    expect(configuration.isUsable, isFalse);
    // Les champs absents ne valent pas `null` au hasard : ils sont vides,
    // et l'écran n'a rien à en faire de toute façon.
    expect(configuration.directChatUrl, '');
    expect(configuration.secureHash, isNull);
  });

  test('allumée mais sans adresse, elle reste inutilisable', () {
    // Ouvrir une WebView sur une adresse vide donnerait une page blanche,
    // ce qui est pire qu'une entrée absente.
    final configuration = SupportChatConfigurationModel.fromJson({
      'enabled': true,
      'property_id': 'abc123def456',
    });

    expect(configuration.enabled, isTrue);
    expect(configuration.isUsable, isFalse);
  });

  test('sans mode sécurisé, le chat reste utilisable', () {
    // Le mode sécurisé est un réglage tawk.to : sans lui, la conversation
    // s'ouvre, l'identité n'est simplement pas vérifiée.
    final configuration = SupportChatConfigurationModel.fromJson({
      'enabled': true,
      'direct_chat_url': 'https://tawk.to/chat/abc123def456/default',
      'secure_hash': '',
    });

    expect(configuration.secureHash, isNull);
    expect(configuration.isUsable, isTrue);
  });

  test('une réponse vide ne donne pas de chat', () {
    final configuration = SupportChatConfigurationModel.fromJson(const {});

    expect(configuration.enabled, isFalse);
    expect(configuration.isUsable, isFalse);
  });
}
