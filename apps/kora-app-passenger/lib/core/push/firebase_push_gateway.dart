import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

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
  }

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

  @override
  Future<String?> token() async => _ready ? _instance.getToken() : null;

  @override
  Stream<String> get tokenRefreshes =>
      _ready ? _instance.onTokenRefresh : const Stream.empty();

  @override
  Stream<PushMessage> get messages => _ready
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
