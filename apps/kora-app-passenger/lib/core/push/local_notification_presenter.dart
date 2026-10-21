import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import 'notification_presenter.dart';
import 'push_gateway.dart';

/// Identifiant du canal sur lequel arrivent les notifications KORA.
///
/// Trois endroits doivent dire la même chose, sans quoi le voyageur verrait
/// deux entrées dans les réglages de son téléphone : `MainActivity.kt`, qui
/// crée le canal au démarrage, la méta-donnée `AndroidManifest.xml`, que
/// Firebase lit pour ses propres notifications, et cette constante.
const koraNotificationChannelId = 'kora_voyages';

/// Nom de secours du canal.
///
/// `MainActivity` le crée déjà, avec le nom traduit tiré des ressources
/// Android ; Android ignore alors celui-ci. Il ne sert que si la
/// notification précédait l'activité, ce qui ne devrait pas arriver.
const _channelFallbackName = 'Voyages KORA';

/// [NotificationPresenter] posé sur le greffon de notifications locales.
class LocalNotificationPresenter implements NotificationPresenter {
  LocalNotificationPresenter({
    FlutterLocalNotificationsPlugin? plugin,
    this.logs = false,
  }) : _plugin = plugin ?? FlutterLocalNotificationsPlugin();

  final FlutterLocalNotificationsPlugin _plugin;

  /// Journalise ce qu'il advient de chaque message reçu à l'écran. Vrai en
  /// dev et en staging, faux en production et dans les tests.
  ///
  /// Sans ça, « je ne vois pas la notification » ne se distingue pas de
  /// « le message n'est jamais arrivé » : ce sont deux pannes différentes,
  /// dans deux couches différentes.
  final bool logs;
  final _openings = StreamController<PushMessage>.broadcast();

  Future<void>? _started;

  @override
  Stream<PushMessage> get openings => _openings.stream;

  @override
  Future<void> show(PushMessage message) async {
    if (!message.hasText) {
      _log('message sans texte, rien à afficher');
      return;
    }

    _log('message reçu à l’écran');

    try {
      await _start();
      await _post(message);
      _log('notification posée');
    } on Exception catch (error) {
      // Un échec ici ne doit pas remonter : le flux qui nous appelle n'a
      // pas de quoi le traiter, et le voyageur verra de toute façon la
      // confirmation à l'écran.
      _log('notification refusée : $error');
    }
  }

  void _log(String outcome) {
    if (logs) debugPrint('[push] $outcome');
  }

  Future<void> _post(PushMessage message) {
    return _plugin.show(
      // Un identifiant par notification : deux messages qui se suivent —
      // le billet émis, puis le départ retardé — doivent coexister dans le
      // volet plutôt que de s'écraser l'un l'autre.
      id: DateTime.now().millisecondsSinceEpoch.remainder(1 << 31),
      title: message.title.isEmpty ? null : message.title,
      body: message.body.isEmpty ? null : message.body,
      payload: jsonEncode(message.data),
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          koraNotificationChannelId,
          _channelFallbackName,
          importance: Importance.high,
          priority: Priority.high,
          enableVibration: true,
          // Déplié, le texte entier tient à l'écran.
          //
          // Sans ce style, Android coupe le message à une ligne et
          // n'affiche aucune flèche : « Votre trajet Cotonou → Parakou
          // est confirmé. Votre… » — il faut ouvrir l'app pour savoir
          // quelle place, à quelle heure. Le voyageur déroule la
          // notification et lit tout, sans la toucher.
          styleInformation: BigTextStyleInformation(
            message.body,
            contentTitle: message.title.isEmpty ? null : message.title,
          ),
        ),
        iOS: const DarwinNotificationDetails(),
      ),
    );
  }

  /// Démarre le greffon, une fois, et seulement quand il y a vraiment
  /// quelque chose à afficher.
  ///
  /// Le faire au lancement coûterait un aller-retour natif à chaque
  /// ouverture de l'application, pour un service dont la plupart des
  /// sessions n'ont pas l'usage.
  ///
  /// **Un échec ne se mémorise pas.** Garder le futur raté condamnerait
  /// toutes les notifications de la session : chaque message suivant
  /// attendrait ce même échec au lieu de retenter. On oublie donc la
  /// tentative, et le message d'après recommence.
  Future<void> _start() {
    final started = _started;
    if (started != null) return started;

    final attempt = _plugin
        .initialize(
          settings: const InitializationSettings(
            // L'icône de l'application : c'est aussi celle que Firebase
            // emploie par défaut, donc les notifications se ressemblent
            // qu'elles viennent du système ou d'ici.
            android: AndroidInitializationSettings('@mipmap/ic_launcher'),
            iOS: DarwinInitializationSettings(),
          ),
          onDidReceiveNotificationResponse: _opened,
        )
        .then((_) {});

    _started = attempt.catchError((Object error) {
      _started = null;
      throw error;
    });

    return _started!;
  }

  void _opened(NotificationResponse response) {
    _openings.add(PushMessage(data: _dataOf(response.payload)));
  }

  /// Relit le sac de clés joint à la notification.
  ///
  /// Il est vide aujourd'hui — le serveur n'envoie encore aucun bloc
  /// `data` —, et le parcours retombe alors sur la liste des voyages. Le
  /// jour où il en enverra un, la destination arrivera jusqu'ici sans
  /// autre changement.
  Map<String, String> _dataOf(String? payload) {
    if (payload == null || payload.isEmpty) return const {};

    try {
      final decoded = jsonDecode(payload);
      if (decoded is! Map) return const {};

      return {
        for (final entry in decoded.entries)
          entry.key.toString(): entry.value.toString(),
      };
    } on FormatException {
      return const {};
    }
  }
}
