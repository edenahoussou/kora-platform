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

    // Le serveur répond avec l'e-mail canonique du compte, même quand le
    // code part sur WhatsApp : l'écran OTP doit montrer là où le
    // voyageur attend son code, donc ce qu'il a saisi.
    expect(challenge.identifier, '+2290197421860');
  });
}
