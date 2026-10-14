// L'inscription du téléphone aux notifications.
//
// Tout ce qui se décide ici se décide sans Firebase : les greffons natifs
// ne tournent pas dans `flutter test`. C'est la raison d'être de
// `PushGateway` — la logique est d'un côté, le SDK de l'autre.

import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/push/firebase_configuration.dart';
import 'package:kora_app_passenger/core/push/push_configuration_store.dart';
import 'package:kora_app_passenger/core/push/push_gateway.dart';
import 'package:kora_app_passenger/core/push/push_messaging.dart';
import 'package:kora_app_passenger/core/push/push_remote_data_source.dart';
import 'package:mocktail/mocktail.dart';
import 'package:shared_preferences/shared_preferences.dart';

class MockRemote extends Mock implements PushRemoteDataSource {}

/// Un Firebase de pacotille : il note ce qu'on lui demande, et rend ce
/// qu'on lui a dit de rendre.
class FakeGateway implements PushGateway {
  FakeGateway({
    this.permission = PushPermission.granted,
    this.deviceToken = 'jeton-fcm',
  });

  PushPermission permission;
  String? deviceToken;

  var initialized = 0;
  var permissionsAsked = 0;
  var tokensDeleted = 0;

  final _refreshes = StreamController<String>.broadcast();

  void refreshToken(String token) => _refreshes.add(token);

  @override
  Future<void> initialize(FirebaseConfiguration configuration) async {
    initialized++;
  }

  @override
  Future<PushPermission> requestPermission() async {
    permissionsAsked++;
    return permission;
  }

  @override
  Future<String?> token() async => deviceToken;

  @override
  Stream<String> get tokenRefreshes => _refreshes.stream;

  @override
  Stream<PushMessage> get messages => const Stream.empty();

  @override
  Stream<PushMessage> get openings => const Stream.empty();

  @override
  Future<PushMessage?> initialMessage() async => null;

  @override
  Future<void> deleteToken() async {
    tokensDeleted++;
  }
}

void main() {
  late MockRemote remote;
  late FakeGateway gateway;
  late PushMessaging push;

  const active = FirebaseConfiguration(
    apiKey: 'cle',
    projectId: 'kora',
    messagingSenderId: '1234',
    appId: '1:1234:android:abcd',
  );

  DioException refusal(int statusCode) {
    final options = RequestOptions(path: '/api/v1/auth/devices/x/push-token');
    return DioException(
      requestOptions: options,
      type: DioExceptionType.badResponse,
      response: Response<dynamic>(
        requestOptions: options,
        statusCode: statusCode,
      ),
    );
  }

  void serverAnswers({
    FirebaseConfiguration configuration = active,
    bool enabled = true,
  }) {
    when(
      () => remote.configuration(any()),
    ).thenAnswer((_) async => configuration);
    when(
      () => remote.savePushToken(
        deviceUuid: any(named: 'deviceUuid'),
        token: any(named: 'token'),
      ),
    ).thenAnswer((_) async => enabled);
  }

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    remote = MockRemote();
    gateway = FakeGateway();
    push = PushMessaging(
      gateway,
      remote,
      const PushConfigurationStore(),
      platform: 'android',
    );
  });

  test('inscrit le jeton et garde la configuration pour l’isolat', () async {
    serverAnswers();

    final outcome = await push.register('appareil-1');

    expect(outcome, PushRegistration.registered);
    expect(gateway.initialized, 1);
    verify(
      () => remote.savePushToken(deviceUuid: 'appareil-1', token: 'jeton-fcm'),
    ).called(1);

    // Sans ce cache, une notification reçue app fermée ne pourrait pas
    // démarrer Firebase : l'isolat réveillé n'a ni session ni réseau.
    final cached = await const PushConfigurationStore().read();
    expect(cached?.projectId, 'kora');
  });

  test('sans Firebase configuré, ne demande rien au voyageur', () async {
    serverAnswers(configuration: FirebaseConfiguration.disabled);

    final outcome = await push.register('appareil-1');

    expect(outcome, PushRegistration.notConfigured);
    // Surtout pas de demande de permission pour un service inexistant.
    expect(gateway.permissionsAsked, 0);
    expect(gateway.initialized, 0);
    verifyNever(
      () => remote.savePushToken(
        deviceUuid: any(named: 'deviceUuid'),
        token: any(named: 'token'),
      ),
    );
  });

  test('une configuration retirée efface le cache précédent', () async {
    serverAnswers();
    await push.register('appareil-1');
    expect(await const PushConfigurationStore().read(), isNotNull);

    serverAnswers(configuration: FirebaseConfiguration.disabled);
    await push.register('appareil-1');

    // Garder l'ancienne ferait tourner l'isolat de fond contre un projet
    // Firebase que KORA n'utilise plus.
    expect(await const PushConfigurationStore().read(), isNull);
  });

  test('un refus de permission n’envoie aucun jeton', () async {
    serverAnswers();
    gateway.permission = PushPermission.denied;

    final outcome = await push.register('appareil-1');

    expect(outcome, PushRegistration.permissionDenied);
    verifyNever(
      () => remote.savePushToken(
        deviceUuid: any(named: 'deviceUuid'),
        token: any(named: 'token'),
      ),
    );
  });

  test('la préférence du compte coupée se distingue d’une panne', () async {
    when(() => remote.configuration(any())).thenAnswer((_) async => active);
    when(
      () => remote.savePushToken(
        deviceUuid: any(named: 'deviceUuid'),
        token: any(named: 'token'),
      ),
    ).thenThrow(refusal(422));

    // 422 : le serveur nomme son refus, et celui-ci se corrige depuis
    // l'écran Préférences. Un 500 ne se corrige pas, d'où deux valeurs.
    expect(
      await push.register('appareil-1'),
      PushRegistration.preferenceDisabled,
    );
  });

  test('une panne réseau reste une panne, à réessayer', () async {
    when(() => remote.configuration(any())).thenAnswer((_) async => active);
    when(
      () => remote.savePushToken(
        deviceUuid: any(named: 'deviceUuid'),
        token: any(named: 'token'),
      ),
    ).thenThrow(refusal(500));

    expect(await push.register('appareil-1'), PushRegistration.failed);
  });

  test('un jeton renouvelé par Firebase est réinscrit tout seul', () async {
    serverAnswers();
    await push.register('appareil-1');

    gateway.refreshToken('jeton-neuf');
    await Future<void>.delayed(Duration.zero);

    // Sans cette réinscription, l'appareil cesserait de recevoir en
    // silence après une réinstallation ou une purge des données.
    verify(
      () => remote.savePushToken(deviceUuid: 'appareil-1', token: 'jeton-neuf'),
    ).called(1);
  });

  test('la déconnexion efface le jeton au serveur avant l’appareil', () async {
    serverAnswers();
    await push.register('appareil-1');

    await push.unregister();

    // `null` vaut effacement côté serveur ; on ne supprime le jeton local
    // qu'ensuite, sinon KORA pourrait encore écrire à un téléphone qui a
    // changé de main.
    verify(
      () => remote.savePushToken(deviceUuid: 'appareil-1', token: null),
    ).called(1);
    expect(gateway.tokensDeleted, 1);
  });

  test('un jeton renouvelé après déconnexion n’est plus envoyé', () async {
    serverAnswers();
    await push.register('appareil-1');
    await push.unregister();

    gateway.refreshToken('jeton-apres-logout');
    await Future<void>.delayed(Duration.zero);

    verifyNever(
      () => remote.savePushToken(
        deviceUuid: any(named: 'deviceUuid'),
        token: 'jeton-apres-logout',
      ),
    );
  });
}
