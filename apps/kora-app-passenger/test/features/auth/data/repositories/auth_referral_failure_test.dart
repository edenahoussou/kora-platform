// Un 422 qui ne nomme qu'un champ doit remonter ce champ-là.
//
// C'est ce qui permet à l'écran d'inscription de poser le refus sous le
// code de parrainage, là où il se corrige, au lieu de l'afficher au
// milieu du formulaire. La doc de `register/complete` le demande
// explicitement : « afficher le message dans errors.referral_code[0] ».

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/auth/data/datasources/auth_local_data_source.dart';
import 'package:kora_app_passenger/features/auth/data/datasources/auth_remote_data_source.dart';
import 'package:kora_app_passenger/features/auth/data/datasources/device_info_data_source.dart';
import 'package:kora_app_passenger/features/auth/data/repositories/auth_repository_impl.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_device.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/device_platform.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_challenge.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_purpose.dart';
import 'package:mocktail/mocktail.dart';

class _MockRemote extends Mock implements AuthRemoteDataSource {}

class _MockLocal extends Mock implements AuthLocalDataSource {}

class _StubDeviceInfo implements DeviceInfoDataSource {
  @override
  Future<DeviceRegistration> currentDevice({String? pushToken}) async =>
      const DeviceRegistration(
        name: 'Infinix HOT 12i',
        platform: DevicePlatform.android,
        fingerprint: 'android-empreinte-de-test',
      );
}

const _verification = OtpVerification(
  challengeUuid: 'challenge-1',
  purpose: OtpPurpose.registration,
  verificationToken: 'jeton-de-verification',
);

DioException _unprocessable(Map<String, dynamic> body) => DioException(
  requestOptions: RequestOptions(path: '/api/v1/auth/register/complete'),
  response: Response<dynamic>(
    requestOptions: RequestOptions(path: '/api/v1/auth/register/complete'),
    statusCode: 422,
    data: body,
  ),
);

void main() {
  late _MockRemote remote;
  late AuthRepositoryImpl repository;

  setUpAll(() {
    // mocktail a besoin d'une valeur de repli par type non nullable
    // avant d'accepter `any(named:)` dessus.
    registerFallbackValue(_verification);
    registerFallbackValue(
      const DeviceRegistration(
        name: 'Infinix HOT 12i',
        platform: DevicePlatform.android,
        fingerprint: 'android-empreinte-de-test',
      ),
    );
  });

  setUp(() {
    remote = _MockRemote();
    repository = AuthRepositoryImpl(remote, _MockLocal(), _StubDeviceInfo());
  });

  /// Fait échouer `register/complete` avec [body] et rend l'erreur
  /// traduite.
  Future<Failure> failureFor(Map<String, dynamic> body) async {
    when(
      () => remote.completeRegistration(
        verification: any(named: 'verification'),
        pin: any(named: 'pin'),
        device: any(named: 'device'),
        name: any(named: 'name'),
        email: any(named: 'email'),
        phone: any(named: 'phone'),
        countryCode: any(named: 'countryCode'),
        referralCode: any(named: 'referralCode'),
      ),
    ).thenThrow(_unprocessable(body));

    final result = await repository.completeRegistration(
      verification: _verification,
      pin: '1234',
      referralCode: 'KA1B2C3D',
    );

    return result.fold((failure) => failure, (_) => fail('succès inattendu'));
  }

  test('un code de parrainage refusé nomme son champ', () async {
    final failure = await failureFor({
      'message': 'Ce code de parrainage est inconnu.',
      'errors': {
        'referral_code': ['Ce code de parrainage est inconnu.'],
      },
    });

    expect(failure, isA<ValidationFailure>());
    expect((failure as ValidationFailure).field, 'referral_code');
    expect(failure.message, 'Ce code de parrainage est inconnu.');
  });

  test('le message lu est celui du champ, pas celui de l’enveloppe', () async {
    // Laravel résume parfois autrement dans `message` ; c'est la raison
    // écrite pour le champ qu'il faut montrer sous le champ.
    final failure = await failureFor({
      'message': 'The given data was invalid.',
      'errors': {
        'referral_code': [
          'Le code de parrainage est réservé à la création d’un nouveau '
              'compte.',
        ],
      },
    });

    expect(
      failure.message,
      'Le code de parrainage est réservé à la création d’un nouveau compte.',
    );
  });

  test('plusieurs champs en cause : aucun ne porte le message', () async {
    // Un message posé sous un champ au hasard désignerait le mauvais
    // coupable : l'écran l'affiche alors en entier.
    final failure = await failureFor({
      'message': 'The given data was invalid.',
      'errors': {
        'referral_code': ['Ce code de parrainage est inconnu.'],
        'pin': ['Le PIN doit contenir 4 chiffres.'],
      },
    });

    expect(failure, isA<ValidationFailure>());
    expect((failure as ValidationFailure).field, isNull);
    expect(failure.message, 'The given data was invalid.');
  });

  test('un 422 sans détail reste un refus de saisie', () async {
    final failure = await failureFor({'message': 'Requête invalide.'});

    expect(failure, isA<ValidationFailure>());
    expect((failure as ValidationFailure).field, isNull);
    expect(failure.message, 'Requête invalide.');
  });
}
