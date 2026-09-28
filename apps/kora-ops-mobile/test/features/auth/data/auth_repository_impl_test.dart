import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show
        LockedApiException,
        ForbiddenApiException,
        UnauthorizedApiException,
        ValidationApiException;
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/features/auth/data/datasources/auth_local_datasource.dart';
import 'package:kora_ops_mobile/features/auth/data/datasources/auth_remote_datasource.dart';
import 'package:kora_ops_mobile/features/auth/data/datasources/device_info_datasource.dart';
import 'package:kora_ops_mobile/features/auth/data/repositories/auth_repository_impl.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/auth_device.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/auth_session.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/auth_user.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/device_platform.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/otp_challenge.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/otp_purpose.dart';
import 'package:mocktail/mocktail.dart';

class MockAuthRemoteDataSource extends Mock implements AuthRemoteDataSource {}

class MockAuthLocalDataSource extends Mock implements AuthLocalDataSource {}

class MockDeviceInfoDataSource extends Mock implements DeviceInfoDataSource {}

/// Fabrique un [DioException] avec une [ApiException] déjà attachée à
/// `.error`, comme le ferait l'`ErrorInterceptor` du package réseau — voir
/// AuthRepositoryImpl._failureOf, qui lit ce champ plutôt que le code HTTP
/// brut.
DioException _dioError(Object apiException) {
  final requestOptions = RequestOptions(path: '/api/v1/auth/login');
  return DioException(
    requestOptions: requestOptions,
    error: apiException,
    type: DioExceptionType.badResponse,
  );
}

void main() {
  // Fallback values requis par mocktail pour tout type non primitif utilisé
  // avec `any()`/`captureAny()` dans les `when()`/`verify()` ci-dessous.
  // Sans ça, le premier appel qui en a besoin plante et pollue la pile de
  // matchers pour tous les tests suivants (symptômes en cascade sans
  // rapport apparent : stubs qui renvoient null, expect() qui échouent).
  setUpAll(() {
    registerFallbackValue(const DeviceRegistration(
      name: 'Fallback Device',
      platform: DevicePlatform.android,
      fingerprint: 'fallback-fp',
    ));
    registerFallbackValue(const OtpVerification(
      challengeUuid: 'fallback-chal',
      purpose: OtpPurpose.loginNewDevice,
      verificationToken: 'fallback-token',
    ));
    registerFallbackValue(AuthSession(
      token: 'fallback-token',
      expiresAt: DateTime(2030),
      user: const AuthUser(
        uuid: 'fallback-u',
        name: 'Fallback User',
        email: 'fallback@kora-express.com',
      ),
    ));
  });

  late MockAuthRemoteDataSource remote;
  late MockAuthLocalDataSource local;
  late MockDeviceInfoDataSource deviceInfo;
  late AuthRepositoryImpl repository;

  const DeviceRegistration device = DeviceRegistration(
    name: 'Test Device',
    platform: DevicePlatform.android,
    fingerprint: 'fp-123',
  );

  const AuthUser user = AuthUser(
    uuid: 'u-1',
    name: 'Aïcha Hounkpè',
    email: 'aicha.hounkpe@kora-express.com',
    permissions: <String>['boarding.scan'],
  );

  setUp(() {
    remote = MockAuthRemoteDataSource();
    local = MockAuthLocalDataSource();
    deviceInfo = MockDeviceInfoDataSource();
    repository = AuthRepositoryImpl(remote, local, deviceInfo);

    when(() => deviceInfo.currentDevice()).thenAnswer((_) async => device);
    // Toujours stubbée : `login()` l'appelle systématiquement après un
    // succès direct (voir AuthRepositoryImpl.login), identifiant email ou
    // téléphone confondus.
    when(() => local.saveLastIdentifier(any())).thenAnswer((_) async {});
  });

  group('login', () {
    test(
        'sauvegarde la session et l’identifiant localement en cas de succès direct',
        () async {
      final session = AuthSession(
        token: 'tok',
        expiresAt: DateTime.now().add(const Duration(days: 7)),
        user: user,
      );

      when(() => remote.login(
            identifier: any(named: 'identifier'),
            pin: any(named: 'pin'),
            device: any(named: 'device'),
            verification: any(named: 'verification'),
          )).thenAnswer((_) async => LoginSucceeded(session));
      when(() => local.saveSession(any())).thenAnswer((_) async {});

      final result = await repository.login(
          identifier: 'aicha.hounkpe@kora-express.com', pin: '1234');

      expect(result.isSuccess, isTrue);
      verify(() => local.saveSession(session)).called(1);
      // Pas forcément le même que `session.user.email` : un compte peut se
      // connecter par téléphone même s'il a aussi un email enregistré —
      // c'est bien l'identifiant SAISI qui doit être mémorisé.
      verify(() => local.saveLastIdentifier('aicha.hounkpe@kora-express.com'))
          .called(1);
    });

    test('ne sauvegarde rien quand l’appareil est inconnu (202)', () async {
      when(() => remote.login(
            identifier: any(named: 'identifier'),
            pin: any(named: 'pin'),
            device: any(named: 'device'),
            verification: any(named: 'verification'),
          )).thenAnswer((_) async => LoginNeedsOtp(
            OtpChallengeRef(
              uuid: 'chal-1',
              expiresAt: DateTime.now().add(const Duration(minutes: 15)),
            ),
          ));

      final result = await repository.login(
          identifier: 'aicha.hounkpe@kora-express.com', pin: '1234');

      expect(result.isSuccess, isTrue);
      verifyNever(() => local.saveSession(any()));
      verifyNever(() => local.saveLastIdentifier(any()));
    });

    test('un 403 sur login est mappé en AccountSuspendedFailure', () async {
      when(() => remote.login(
                identifier: any(named: 'identifier'),
                pin: any(named: 'pin'),
                device: any(named: 'device'),
                verification: any(named: 'verification'),
              ))
          .thenThrow(_dioError(const ForbiddenApiException(
              'Compte suspendu. Contactez le support.')));

      final result = await repository.login(
          identifier: 'aicha.hounkpe@kora-express.com', pin: '1234');

      expect(result.isFailure, isTrue);
      result.when(
        onSuccess: (_) => fail('devrait échouer'),
        onFailure: (failure) => expect(failure, isA<AccountSuspendedFailure>()),
      );
    });

    test('un 423 est mappé en AccountLockedFailure', () async {
      when(() => remote.login(
                identifier: any(named: 'identifier'),
                pin: any(named: 'pin'),
                device: any(named: 'device'),
                verification: any(named: 'verification'),
              ))
          .thenThrow(_dioError(
              const LockedApiException('Compte temporairement verrouillé.')));

      final result = await repository.login(
          identifier: 'aicha.hounkpe@kora-express.com', pin: '1234');

      result.when(
        onSuccess: (_) => fail('devrait échouer'),
        onFailure: (failure) => expect(failure, isA<AccountLockedFailure>()),
      );
    });

    test('un 401 est mappé en AuthenticationFailure (PIN incorrect)', () async {
      when(() => remote.login(
                identifier: any(named: 'identifier'),
                pin: any(named: 'pin'),
                device: any(named: 'device'),
                verification: any(named: 'verification'),
              ))
          .thenThrow(_dioError(
              const UnauthorizedApiException('Identifiants incorrects.')));

      final result = await repository.login(
          identifier: 'aicha.hounkpe@kora-express.com', pin: '0000');

      result.when(
        onSuccess: (_) => fail('devrait échouer'),
        onFailure: (failure) => expect(failure, isA<AuthenticationFailure>()),
      );
    });

    test('un 422 est mappé en ValidationFailure', () async {
      when(() => remote.login(
                identifier: any(named: 'identifier'),
                pin: any(named: 'pin'),
                device: any(named: 'device'),
                verification: any(named: 'verification'),
              ))
          .thenThrow(_dioError(const ValidationApiException(
              'Les données envoyées sont invalides.')));

      final result =
          await repository.login(identifier: 'not-an-email', pin: '1234');

      result.when(
        onSuccess: (_) => fail('devrait échouer'),
        onFailure: (failure) => expect(failure, isA<ValidationFailure>()),
      );
    });

    test(
        'un code métier account_suspended (hors 403 Dio direct) est mappé pareil',
        () async {
      when(() => remote.login(
            identifier: any(named: 'identifier'),
            pin: any(named: 'pin'),
            device: any(named: 'device'),
            verification: any(named: 'verification'),
          )).thenThrow(
        const AuthApiException(
          AuthApiException.accountSuspended,
          'Le compte est suspendu. Contactez le support.',
        ),
      );

      final result = await repository.login(
          identifier: 'aicha.hounkpe@kora-express.com', pin: '1234');

      result.when(
        onSuccess: (_) => fail('devrait échouer'),
        onFailure: (failure) => expect(failure, isA<AccountSuspendedFailure>()),
      );
    });
  });

  group('currentSession', () {
    test('purge et rend null si la session stockée est expirée', () async {
      final expired = AuthSession(
        token: 'old',
        expiresAt: DateTime.now().subtract(const Duration(days: 1)),
        user: user,
      );

      when(() => local.readSession()).thenAnswer((_) async => expired);
      when(() => local.clear()).thenAnswer((_) async {});

      final result = await repository.currentSession();

      expect(
        result.when(onSuccess: (s) => s, onFailure: (_) => 'error'),
        isNull,
      );
      verify(() => local.clear()).called(1);
    });

    test('rend la session telle quelle si elle est encore valide', () async {
      final valid = AuthSession(
        token: 'still-good',
        expiresAt: DateTime.now().add(const Duration(days: 3)),
        user: user,
      );

      when(() => local.readSession()).thenAnswer((_) async => valid);

      final result = await repository.currentSession();

      expect(
        result.when(onSuccess: (s) => s, onFailure: (_) => null),
        valid,
      );
      verifyNever(() => local.clear());
    });
  });

  group('lastIdentifier', () {
    test('délègue directement à AuthLocalDataSource.readLastIdentifier',
        () async {
      when(() => local.readLastIdentifier())
          .thenAnswer((_) async => 'aicha.hounkpe@kora-express.com');

      final result = await repository.lastIdentifier();

      expect(result, 'aicha.hounkpe@kora-express.com');
    });
  });

  group('logout', () {
    test('purge le stockage local même si l’appel réseau échoue', () async {
      when(() => remote.logout())
          .thenThrow(_dioError(const UnauthorizedApiException()));
      when(() => local.clear()).thenAnswer((_) async {});

      await repository.logout();

      verify(() => local.clear()).called(1);
    });

    test('purge le stockage local en cas de succès aussi', () async {
      when(() => remote.logout()).thenAnswer((_) async {});
      when(() => local.clear()).thenAnswer((_) async {});

      final result = await repository.logout();

      expect(result.isSuccess, isTrue);
      verify(() => local.clear()).called(1);
    });
  });

  group('requestOtp / verifyOtp', () {
    test('propage le motif pin_reset au data source', () async {
      when(() => remote.requestOtp(
            identifier: any(named: 'identifier'),
            purpose: OtpPurpose.pinReset,
          )).thenAnswer((_) async => OtpChallenge(
            uuid: 'chal-2',
            purpose: OtpPurpose.pinReset,
            identifier: 'aicha.hounkpe@kora-express.com',
            expiresAt: DateTime.now().add(const Duration(minutes: 15)),
          ));

      final result = await repository.requestOtp(
        identifier: 'aicha.hounkpe@kora-express.com',
        purpose: OtpPurpose.pinReset,
      );

      expect(result.isSuccess, isTrue);
      verify(() => remote.requestOtp(
            identifier: 'aicha.hounkpe@kora-express.com',
            purpose: OtpPurpose.pinReset,
          )).called(1);
    });
  });
}
