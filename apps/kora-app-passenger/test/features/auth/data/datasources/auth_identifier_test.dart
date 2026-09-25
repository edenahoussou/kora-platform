// La connexion par téléphone, côté réseau (téléphone + PIN).
//
// L'API a deux champs pour désigner un compte : `identifier`, qui accepte
// le téléphone comme l'e-mail, et `email`, son alias d'avant qui ne prend
// que le second. Les envoyer tous les deux fait répondre 422 — ces tests
// tiennent la frontière.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/auth/data/datasources/auth_remote_data_source.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_device.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/device_platform.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_challenge.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_purpose.dart';
import 'package:mocktail/mocktail.dart';

class _MockDio extends Mock implements Dio {}

const _device = DeviceRegistration(
  name: 'Infinix HOT 12i',
  platform: DevicePlatform.android,
  fingerprint: 'android-empreinte-de-test',
);

Response<dynamic> _response(Map<String, dynamic> data, {int status = 200}) =>
    Response<dynamic>(
      requestOptions: RequestOptions(path: '/api/v1/auth'),
      statusCode: status,
      data: {'data': data},
    );

void main() {
  late _MockDio dio;
  late AuthRemoteDataSourceImpl dataSource;

  setUpAll(() => registerFallbackValue(Options()));

  setUp(() {
    dio = _MockDio();
    dataSource = AuthRemoteDataSourceImpl(dio);
  });

  /// Le corps du dernier POST.
  Map<String, dynamic> lastBody() {
    final captured = verify(
      () => dio.post<dynamic>(
        any(),
        data: captureAny(named: 'data'),
        options: any(named: 'options'),
      ),
    ).captured.last;

    return Map<String, dynamic>.from(captured as Map);
  }

  test('la connexion envoie le téléphone dans `identifier`', () async {
    when(
      () => dio.post<dynamic>(
        any(),
        data: any(named: 'data'),
        options: any(named: 'options'),
      ),
    ).thenAnswer(
      (_) async => _response({
        'token': 'jeton',
        'expires_at': '2026-12-31T23:59:59+00:00',
        'user': {
          'uuid': 'user-1',
          'name': 'Aïcha',
          'email': 'aicha@example.com',
          'status': 'active',
        },
      }),
    );

    await dataSource.login(
      identifier: '+2290197421860',
      pin: '1234',
      device: _device,
    );

    final body = lastBody();
    expect(body['identifier'], '+2290197421860');
    // `email` n'accepterait pas un numéro, et l'API refuse les deux
    // champs ensemble.
    expect(body.containsKey('email'), isFalse);
    expect(body['pin'], '1234');
  });

  test('la demande de code part sur le même champ', () async {
    when(
      () => dio.post<dynamic>(
        any(),
        data: any(named: 'data'),
        options: any(named: 'options'),
      ),
    ).thenAnswer(
      (_) async => _response({
        'otp_challenge_uuid': 'challenge-1',
        'purpose': 'pin_reset',
        'email': 'aicha@example.com',
        'expires_at': '2026-09-25T13:10:00+00:00',
      }, status: 202),
    );

    final challenge = await dataSource.requestOtp(
      identifier: '+2290197421860',
      purpose: OtpPurpose.pinReset,
    );

    final body = lastBody();
    expect(body['identifier'], '+2290197421860');
    expect(body.containsKey('email'), isFalse);

    // Le serveur renvoie l'identifiant qui a reçu le code.
    expect(challenge.identifier, '+2290197421860');
  });

  test('le défi retient l’identifiant normalisé par le serveur', () async {
    when(
      () => dio.post<dynamic>(
        any(),
        data: any(named: 'data'),
        options: any(named: 'options'),
      ),
    ).thenAnswer(
      (_) async => _response({
        'otp_challenge_uuid': 'challenge-2',
        'purpose': 'registration',
        // Un téléphone : le serveur n'a pas d'e-mail à rendre.
        'email': null,
        'identifier': '+2290197421860',
        'expires_at': '2026-09-26T13:10:00+00:00',
      }, status: 202),
    );

    // Saisi à la locale, sans indicatif recollé par l'écran.
    final challenge = await dataSource.requestOtp(
      identifier: '0197421860',
      purpose: OtpPurpose.registration,
    );

    // C'est la valeur du serveur qui fait foi : elle est normalisée, et
    // c'est elle que l'écran OTP affiche masquée.
    expect(challenge.identifier, '+2290197421860');
  });

  test('l’inscription par téléphone emporte le nom', () async {
    when(
      () => dio.post<dynamic>(
        any(),
        data: any(named: 'data'),
        options: any(named: 'options'),
      ),
    ).thenAnswer(
      (_) async => _response({
        'token': 'jeton',
        'expires_at': '2026-12-31T23:59:59+00:00',
        'user': {
          'uuid': 'user-1',
          'name': 'Aïcha HOUNKPE',
          'email': 'aicha@example.com',
          'status': 'active',
        },
      }),
    );

    await dataSource.completeRegistration(
      verification: const OtpVerification(
        challengeUuid: 'challenge-2',
        purpose: OtpPurpose.registration,
        verificationToken: 'jeton-de-verification',
      ),
      pin: '1234',
      device: _device,
      name: 'Aïcha HOUNKPE',
    );

    final body = lastBody();
    // Sans lui, le serveur répond `422 name_required` : il n'a pas
    // d'adresse d'où dériver un nom.
    expect(body['name'], 'Aïcha HOUNKPE');
    expect(body['pin_confirmation'], '1234');
    // Ce que l'écran n'a pas recueilli ne part pas : une clé nulle
    // vaudrait effacement côté serveur.
    expect(body.containsKey('email'), isFalse);
    expect(body.containsKey('phone'), isFalse);
  });
}
