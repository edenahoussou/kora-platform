// Ce qui arrive quand une notification tombe pendant que l'application
// est à l'écran.
//
// C'est le seul cas où Firebase ne dessine rien et laisse la main : au
// lieu d'un message qui s'efface tout seul, le voyageur doit recevoir une
// vraie notification, sur le canal des voyages, qui reste dans son volet.
//
// Le greffon natif n'existe pas dans `flutter test` : on écoute donc son
// canal de méthodes et on lit ce que l'application lui demande.

import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/push/local_notification_presenter.dart';
import 'package:kora_app_passenger/core/push/push_gateway.dart';

const _channel = MethodChannel('dexterous.com/flutter/local_notifications');

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late List<MethodCall> calls;

  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    // Hors application, personne n'a enregistré le greffon : c'est le
    // registrant natif qui le fait d'ordinaire, au démarrage.
    AndroidFlutterLocalNotificationsPlugin.registerWith();
    calls = [];

    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(_channel, (call) async {
          calls.add(call);
          return call.method == 'initialize' ? true : null;
        });
  });

  tearDown(() {
    debugDefaultTargetPlatformOverride = null;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(_channel, null);
  });

  MethodCall shown() => calls.firstWhere((call) => call.method == 'show');

  Map<String, Object?> androidOf(MethodCall call) {
    final arguments = (call.arguments as Map).cast<String, Object?>();
    return (arguments['platformSpecifics'] as Map).cast<String, Object?>();
  }

  test('un message reçu à l’écran devient une vraie notification', () async {
    await LocalNotificationPresenter().show(
      const PushMessage(
        title: 'Réservation confirmée',
        body: 'Votre trajet Cotonou → Bohicon est confirmé.',
      ),
    );

    final arguments = (shown().arguments as Map).cast<String, Object?>();

    expect(arguments['title'], 'Réservation confirmée');
    expect(arguments['body'], 'Votre trajet Cotonou → Bohicon est confirmé.');
  });

  test('elle part sur le canal des voyages, et s’annonce', () async {
    await LocalNotificationPresenter().show(
      const PushMessage(title: 'Départ retardé', body: '30 minutes.'),
    );

    final android = androidOf(shown());

    // Le même canal que celui créé par MainActivity et désigné à Firebase
    // dans le manifeste : sans quoi le voyageur verrait deux entrées dans
    // les réglages de son téléphone, et pourrait n'en couper qu'une.
    expect(android['channelId'], koraNotificationChannelId);

    // Importance haute : une notification silencieuse, reléguée en bas du
    // volet, ne vaut pas mieux que pas de notification du tout.
    expect(android['importance'], 4);
    expect(android['priority'], 1);
    expect(android['enableVibration'], isTrue);
  });

  test('deux messages qui se suivent ne s’écrasent pas', () async {
    final presenter = LocalNotificationPresenter();

    await presenter.show(const PushMessage(title: 'Billet émis'));
    await Future<void>.delayed(const Duration(milliseconds: 2));
    await presenter.show(const PushMessage(title: 'Départ retardé'));

    final ids = calls
        .where((call) => call.method == 'show')
        .map((call) => (call.arguments as Map)['id'])
        .toList();

    expect(ids, hasLength(2));
    expect(ids.first, isNot(ids.last));
  });

  test('un message sans titre ni texte n’affiche rien', () async {
    await LocalNotificationPresenter().show(
      const PushMessage(data: {'type': 'ticket.issued'}),
    );

    expect(calls.where((call) => call.method == 'show'), isEmpty);
  });

  test('le sac de clés du serveur voyage avec la notification', () async {
    // Vide aujourd'hui, mais c'est par là que la destination arrivera le
    // jour où le back joindra un bloc `data`.
    await LocalNotificationPresenter().show(
      const PushMessage(title: 'Billet émis', data: {'route': '/voyages'}),
    );

    final arguments = (shown().arguments as Map).cast<String, Object?>();

    expect(jsonDecode(arguments['payload']! as String), {'route': '/voyages'});
  });

  test('le greffon ne démarre qu’une fois', () async {
    final presenter = LocalNotificationPresenter();

    await presenter.show(const PushMessage(title: 'Billet émis'));
    await presenter.show(const PushMessage(title: 'Départ retardé'));

    expect(calls.where((call) => call.method == 'initialize'), hasLength(1));
  });

  test('toucher la notification remonte ce qu’elle portait', () async {
    final presenter = LocalNotificationPresenter();
    final opened = presenter.openings.first;

    await presenter.show(
      const PushMessage(title: 'Billet émis', data: {'route': '/voyages'}),
    );

    // Ce que le greffon renvoie quand le voyageur touche la notification.
    await TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .handlePlatformMessage(
          _channel.name,
          _channel.codec.encodeMethodCall(
            MethodCall('didReceiveNotificationResponse', <String, Object?>{
              'notificationResponseType': 0,
              'notificationId': 1,
              'payload': jsonEncode({'route': '/voyages'}),
            }),
          ),
          (_) {},
        );

    expect((await opened).route, '/voyages');
  });

  test('une initialisation ratée ne condamne pas la session', () async {
    // Le greffon refuse une fois, puis accepte. Mémoriser le futur raté
    // priverait le voyageur de toutes ses notifications jusqu'à ce qu'il
    // relance l'application.
    var initializations = 0;
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(_channel, (call) async {
          calls.add(call);
          if (call.method != 'initialize') return null;
          if (++initializations == 1) {
            throw PlatformException(code: 'error', message: 'greffon absent');
          }
          return true;
        });

    final presenter = LocalNotificationPresenter();

    await presenter.show(const PushMessage(title: 'Billet émis'));
    expect(calls.where((call) => call.method == 'show'), isEmpty);

    // Le message suivant retente, et aboutit.
    await presenter.show(const PushMessage(title: 'Départ retardé'));

    expect(initializations, 2);
    expect(calls.where((call) => call.method == 'show'), hasLength(1));
  });
}
