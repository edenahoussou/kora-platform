import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import 'firebase_configuration.dart';
import 'push_gateway.dart';

/// L'implémentation réelle de [PushGateway], posée sur Firebase.
///
/// Elle ne décide rien : elle traduit. Les règles — quand inscrire, que
/// faire d'un refus, quand effacer — vivent dans `PushMessaging`, qui se
/// teste sans Firebase grâce à cette séparation.
class FirebasePushGateway implements PushGateway {
  FirebasePushGateway();

  FirebaseMessaging? _messaging;

  /// Vrai seulement si Firebase a démarré.
  ///
  /// Tout ce qui suit passe par là. Sans ce garde-fou, le moindre appel —
  /// lire le message d'ouverture, s'abonner aux messages — lève
  /// `[core/no-app] No Firebase App '[DEFAULT]' has been created`, une
  /// exception asynchrone que rien n'attrape et qui remonte en erreur non
  /// gérée. C'est exactement ce qui se produisait quand la configuration
  /// n'arrivait pas : l'app plantait pour une fonctionnalité absente.
  bool get _ready => Firebase.apps.isNotEmpty;

  FirebaseMessaging get _instance => _messaging ??= FirebaseMessaging.instance;

  @override
  Future<void> initialize(FirebaseConfiguration configuration) async {
    // `Firebase.initializeApp` relancé sur une app déjà démarrée lève une
    // erreur : on ne le fait qu'une fois. Le cas se présente à chaque
    // reconnexion, et au réveil de l'isolat de fond.
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp(options: configuration.toOptions());
    }

    // Sur iOS, c'est le système qui affiche la notification quand l'app
    // est à l'écran : firebase_messaging 16 avale les notifications
    // locales qu'on poserait nous-mêmes (corrigé en 17, que la connexion
    // Google empêche encore d'adopter). Voir [messages].
    if (_isIos) {
      await _instance.setForegroundNotificationPresentationOptions(
        alert: true,
        badge: true,
        sound: true,
      );
    }
  }

  bool get _isIos => defaultTargetPlatform == TargetPlatform.iOS;

  @override
  Future<PushPermission> requestPermission() async {
    if (!_ready) return PushPermission.denied;

    final settings = await _instance.requestPermission();

    return switch (settings.authorizationStatus) {
      AuthorizationStatus.authorized => PushPermission.granted,
      AuthorizationStatus.provisional => PushPermission.provisional,
      _ => PushPermission.denied,
    };
  }

  // `getToken`, `onTokenRefresh` et `deleteToken` sont dépréciés depuis
  // firebase_messaging 17 au profit de `register`/`onRegistered`. Ceux-là
  // rendent un Firebase Installation ID, cible de l'envoi « direct-send »,
  // alors que le serveur KORA envoie au jeton d'inscription FCM. Migrer
  // suppose de changer l'envoi côté serveur en même temps ; d'ici là, on
  // garde l'API à jeton.

  @override
  // ignore: deprecated_member_use
  Future<String?> token() async => _ready ? _instance.getToken() : null;

  @override
  Stream<String> get tokenRefreshes =>
      // ignore: deprecated_member_use
      _ready ? _instance.onTokenRefresh : const Stream.empty();

  /// Vide sur iOS : le système y affiche déjà la notification au premier
  /// plan (voir [initialize]). L'afficher une seconde fois donnerait deux
  /// bandeaux pour un seul message.
  @override
  Stream<PushMessage> get messages => _ready && !_isIos
      ? FirebaseMessaging.onMessage.map(_toMessage)
      : const Stream.empty();

  @override
  Stream<PushMessage> get openings => _ready
      ? FirebaseMessaging.onMessageOpenedApp.map(_toMessage)
      : const Stream.empty();

  @override
  Future<PushMessage?> initialMessage() async {
    if (!_ready) return null;

    final message = await _instance.getInitialMessage();

    return message == null ? null : _toMessage(message);
  }

  @override
  Future<void> deleteToken() async {
    // ignore: deprecated_member_use
    if (_ready) await _instance.deleteToken();
  }
}

/// Firebase rend les valeurs de `data` en `Object?` : elles arrivent
/// toujours en chaînes sur le réseau, mais le type ne le dit pas.
PushMessage _toMessage(RemoteMessage message) => PushMessage(
  title: message.notification?.title ?? '',
  body: message.notification?.body ?? '',
  data: {for (final entry in message.data.entries) entry.key: '${entry.value}'},
);
