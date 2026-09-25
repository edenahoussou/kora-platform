import 'package:dartz/dartz.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_device.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_session.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_user.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/device_platform.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_challenge.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_purpose.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/user_preferences.dart';
import 'package:kora_app_passenger/features/auth/domain/repositories/auth_repository.dart';

/// Dépôt d'authentification bouchonné, pour les tests d'écran.
///
/// Les écrans d'authentification ont besoin d'un `AuthFlowBloc` dans leur
/// arbre, donc d'un dépôt. Ceux qui vérifient la **saisie** (pavé, format
/// d'e-mail, décompte) n'ont rien à dire du réseau : ce bouchon répond
/// toujours succès, et enregistre ce qu'on lui a demandé pour les rares
/// tests qui veulent le vérifier.
class StubAuthRepository implements AuthRepository {
  StubAuthRepository({this.failure});

  /// Quand il est renseigné, tous les appels échouent avec cette erreur —
  /// de quoi tester l'affichage d'un message venu du serveur.
  final Failure? failure;

  final List<String> calls = [];

  /// Chaque demande produit un défi distinct, comme le ferait l'API : les
  /// écrans s'appuient sur ce changement d'identifiant pour repartir (le
  /// décompte de l'OTP, notamment).
  int _challenges = 0;

  static final session = AuthSession(
    token: 'token-de-test',
    expiresAt: DateTime(2099),
    user: const AuthUser(
      uuid: 'user-1',
      name: 'Aïcha HOUNKPÈ',
      email: 'aicha@example.com',
    ),
  );

  /// La même session, une fois le téléphone renseigné.
  ///
  /// [session] décrit un compte **au sortir de l'inscription** : l'écran
  /// des autorisations n'a pas encore recueilli le numéro, et c'est
  /// précisément ce que vérifient ses tests. Les écrans du tunnel, eux,
  /// ne sont atteints qu'après — d'où cette seconde fixture.
  static final sessionWithPhone = AuthSession(
    token: session.token,
    expiresAt: session.expiresAt,
    user: const AuthUser(
      uuid: 'user-1',
      name: 'Aïcha HOUNKPÈ',
      email: 'aicha@example.com',
      phone: '+229 0197421860',
      countryCode: '+229',
    ),
  );

  Either<Failure, T> _answer<T>(String call, T value) {
    calls.add(call);
    final error = failure;
    return error == null ? Right(value) : Left(error);
  }

  @override
  Future<Either<Failure, OtpChallenge>> requestOtp({
    required String identifier,
    required OtpPurpose purpose,
  }) async => _answer(
    'requestOtp:${purpose.value}',
    OtpChallenge(
      uuid: 'challenge-${++_challenges}',
      purpose: purpose,
      identifier: identifier,
      expiresAt: DateTime.now().add(const Duration(minutes: 15)),
    ),
  );

  @override
  Future<Either<Failure, OtpVerification>> verifyOtp({
    required String challengeUuid,
    required OtpPurpose purpose,
    required String code,
  }) async => _answer(
    'verifyOtp:$code',
    OtpVerification(
      challengeUuid: challengeUuid,
      purpose: purpose,
      verificationToken: 'verification-1',
    ),
  );

  @override
  Future<Either<Failure, AuthSession>> completeRegistration({
    required OtpVerification verification,
    required String pin,
  }) async => _answer('completeRegistration:$pin', session);

  @override
  Future<Either<Failure, LoginOutcome>> login({
    required String identifier,
    required String pin,
    OtpVerification? verification,
  }) async => _answer('login:$identifier', LoginSucceeded(session));

  @override
  Future<Either<Failure, AuthSession>> loginWithProvider({
    required String provider,
    required String providerToken,
  }) async => _answer('loginWithProvider:$provider', session);

  @override
  Future<Either<Failure, AuthSession?>> currentSession() async =>
      _answer('currentSession', null);

  @override
  Future<String?> lastIdentifier() async => null;

  @override
  Future<DeviceRegistration> currentDevice() async => const DeviceRegistration(
    name: 'Infinix HOT 12i',
    platform: DevicePlatform.android,
    fingerprint: 'android-empreinte-de-test',
  );

  /// Profil rendu par `auth/me`. Les écrans qui relisent le profil à
  /// l'ouverture doivent pouvoir retrouver celui de leur session.
  AuthUser? refreshedUser;

  @override
  Future<Either<Failure, AuthUser>> refreshUser() async =>
      _answer('refreshUser', refreshedUser ?? session.user);

  @override
  Future<Either<Failure, AuthUser>> updateProfile({
    required String name,
    String? email,
    String? phone,
    String? countryCode,
    DateTime? birthDate,
  }) async => _answer(
    'updateProfile:$name/${countryCode ?? ''}${phone ?? ''}',
    AuthUser(
      uuid: session.user.uuid,
      name: name,
      email: session.user.email,
      phone: phone,
      countryCode: countryCode,
      birthDate: birthDate,
    ),
  );

  /// Réglages rendus par le bouchon, que les tests ajustent.
  UserPreferences storedPreferences = const UserPreferences();

  @override
  Future<Either<Failure, UserPreferences>> preferences() async =>
      _answer('preferences', storedPreferences);

  @override
  Future<Either<Failure, UserPreferences>> savePreferences(
    UserPreferences preferences,
  ) async {
    final result = _answer('savePreferences', preferences);
    if (result.isRight()) storedPreferences = preferences;

    return result;
  }

  @override
  Future<Either<Failure, void>> logout() async => _answer('logout', null);

  @override
  Future<Either<Failure, void>> resetPin({
    required OtpVerification verification,
    required String pin,
  }) async => _answer('resetPin:$pin', null);

  @override
  Future<Either<Failure, void>> changePin({
    required String currentPin,
    required String newPin,
  }) async => _answer('changePin', null);

  @override
  Future<Either<Failure, void>> unlockAccount(
    OtpVerification verification,
  ) async => _answer('unlockAccount', null);

  @override
  Future<Either<Failure, List<AuthDevice>>> devices() async =>
      _answer('devices', const <AuthDevice>[]);

  @override
  Future<Either<Failure, void>> revokeDevice(String uuid) async =>
      _answer('revokeDevice:$uuid', null);
}
