import 'package:dartz/dartz.dart';
import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart' show ApiException;

import '../../../../core/error/failures.dart';
import '../../domain/entities/auth_device.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/auth_user.dart';
import '../../domain/entities/otp_challenge.dart';
import '../../domain/entities/otp_purpose.dart';
import '../../domain/entities/user_preferences.dart';
import '../../domain/repositories/auth_repository.dart';
import '../datasources/auth_local_data_source.dart';
import '../datasources/auth_remote_data_source.dart';
import '../datasources/device_info_data_source.dart';

/// Couche DATA : assemble le réseau, le stockage sécurisé et l'identité de
/// l'appareil derrière le contrat du domaine.
///
/// C'est ici que les exceptions deviennent des [Failure] : au-dessus,
/// personne ne connaît Dio ni les codes HTTP.
class AuthRepositoryImpl implements AuthRepository {
  /// Positionnel comme les autres repositories du projet : un nom de
  /// paramètre ne peut pas commencer par un souligné, et ce sont bien des
  /// champs privés qu'on initialise.
  AuthRepositoryImpl(
    this._remote,
    this._local,
    this._deviceInfo, {
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now;

  final AuthRemoteDataSource _remote;
  final AuthLocalDataSource _local;
  final DeviceInfoDataSource _deviceInfo;

  /// Horloge injectable : c'est elle qui décide si une session est
  /// périmée, et les tests doivent pouvoir la figer.
  final DateTime Function() _now;

  @override
  Future<Either<Failure, OtpChallenge>> requestOtp({
    required String identifier,
    required OtpPurpose purpose,
  }) => _attempt(
    () => _remote.requestOtp(identifier: identifier, purpose: purpose),
  );

  @override
  Future<Either<Failure, OtpVerification>> verifyOtp({
    required String challengeUuid,
    required OtpPurpose purpose,
    required String code,
  }) => _attempt(
    () => _remote.verifyOtp(
      challengeUuid: challengeUuid,
      purpose: purpose,
      code: code,
    ),
  );

  @override
  Future<Either<Failure, AuthSession>> completeRegistration({
    required OtpVerification verification,
    required String pin,
    String? name,
    String? email,
    String? phone,
    String? countryCode,
  }) => _attempt(() async {
    final session = await _remote.completeRegistration(
      verification: verification,
      pin: pin,
      device: await _deviceInfo.currentDevice(),
      name: name,
      email: email,
      phone: phone,
      countryCode: countryCode,
    );

    // Enregistrée tout de suite : l'écran suivant part du principe que la
    // session est utilisable, et `DioClient` relit le token depuis le
    // stockage à chaque requête.
    await _local.saveSession(session);
    return session;
  });

  @override
  Future<Either<Failure, LoginOutcome>> login({
    required String identifier,
    required String pin,
    OtpVerification? verification,
  }) => _attempt(() async {
    final outcome = await _remote.login(
      identifier: identifier,
      pin: pin,
      device: await _deviceInfo.currentDevice(),
      verification: verification,
    );

    // Un appareil inconnu ne rend pas de session : rien à stocker, l'app
    // enchaîne sur la vérification du code.
    if (outcome is LoginSucceeded) await _local.saveSession(outcome.session);

    return outcome;
  });

  @override
  Future<Either<Failure, AuthSession>> loginWithProvider({
    required String provider,
    required String providerToken,
  }) => _attempt(() async {
    final session = await _remote.loginWithProvider(
      provider: provider,
      providerToken: providerToken,
      device: await _deviceInfo.currentDevice(),
    );

    await _local.saveSession(session);
    return session;
  });

  @override
  Future<Either<Failure, AuthSession?>> currentSession() => _attempt(() async {
    final session = await _local.readSession();
    if (session == null) return null;

    // Le token vaut ~7 jours et rien ne le renouvelle : une session
    // périmée est purgée ici plutôt que de laisser l'app enchaîner les
    // 401 jusqu'à ce que l'utilisateur comprenne.
    if (session.isExpiredAt(_now())) {
      await _local.clear();
      return null;
    }

    return session;
  });

  @override
  Future<String?> lastIdentifier() => _local.readLastIdentifier();

  @override
  Future<DeviceRegistration> currentDevice() => _deviceInfo.currentDevice();

  @override
  Future<Either<Failure, AuthUser>> refreshUser() => _attempt(() async {
    final user = await _remote.me();
    await _local.saveUser(user);
    return user;
  });

  @override
  Future<Either<Failure, AuthUser>> updateProfile({
    required String name,
    String? email,
    String? phone,
    String? countryCode,
    DateTime? birthDate,
  }) => _attempt(() async {
    // L'API réclame l'e-mail à chaque mise à jour, y compris quand
    // l'écran appelant ne le modifie pas. On le reprend alors de la
    // session ouverte plutôt que de le faire remonter jusqu'à la
    // présentation.
    final session = await _local.readSession();

    final user = await _remote.updateProfile(
      name: name,
      email: email ?? session?.user.email ?? '',
      phone: phone,
      countryCode: countryCode,
      birthDate: birthDate,
    );
    await _local.saveUser(user);
    return user;
  });

  @override
  Future<Either<Failure, UserPreferences>> preferences() =>
      _attempt(_remote.preferences);

  @override
  Future<Either<Failure, UserPreferences>> savePreferences(
    UserPreferences preferences,
  ) => _attempt(() => _remote.updatePreferences(preferences));

  @override
  Future<Either<Failure, void>> logout() async {
    // La purge locale a lieu quoi qu'il arrive : quelqu'un qui demande à
    // se déconnecter ne doit pas rester connecté sur son téléphone parce
    // que le serveur était injoignable. Le token restera valide côté
    // serveur jusqu'à son expiration — c'est le compromis assumé.
    try {
      await _remote.logout();
      return const Right(null);
    } on DioException catch (error) {
      return Left(_failureOf(error));
    } on AuthApiException catch (error) {
      return Left(_failureOfCode(error));
    } finally {
      await _local.clear();
    }
  }

  @override
  Future<Either<Failure, void>> resetPin({
    required OtpVerification verification,
    required String pin,
  }) => _attempt(() => _remote.resetPin(verification: verification, pin: pin));

  @override
  Future<Either<Failure, void>> changePin({
    required String currentPin,
    required String newPin,
  }) =>
      _attempt(() => _remote.changePin(currentPin: currentPin, newPin: newPin));

  @override
  Future<Either<Failure, void>> unlockAccount(OtpVerification verification) =>
      _attempt(() => _remote.unlockAccount(verification));

  @override
  Future<Either<Failure, List<AuthDevice>>> devices() =>
      _attempt(_remote.devices);

  @override
  Future<Either<Failure, void>> revokeDevice(String uuid) =>
      _attempt(() => _remote.revokeDevice(uuid));

  /// Exécute [call] et traduit tout ce qui peut en sortir en [Failure].
  Future<Either<Failure, T>> _attempt<T>(Future<T> Function() call) async {
    try {
      return Right(await call());
    } on AuthApiException catch (error) {
      return Left(_failureOfCode(error));
    } on DioException catch (error) {
      return Left(_failureOf(error));
    } on ApiException catch (error) {
      return Left(AuthFailure(error.message));
    } catch (error) {
      // Le stockage sécurisé ou la lecture des infos de l'appareil peuvent
      // échouer sur un téléphone verrouillé par une politique d'entreprise.
      return Left(UnexpectedFailure('$error'));
    }
  }

  Failure _failureOf(DioException error) {
    final status = error.response?.statusCode;
    final message = _messageOf(error);

    return switch (status) {
      401 => AuthFailure(message ?? 'Identifiants incorrects.'),
      403 => AuthFailure(message ?? 'Accès refusé.'),
      422 => ValidationFailure(
        message ?? 'Les données saisies sont invalides.',
      ),
      // 409 : l'identifiant porte déjà un compte avec PIN. Un type à
      // part lui aussi : l'écran d'inscription mène alors à la
      // connexion, au lieu de laisser retaper la même adresse.
      409 => AccountExistsFailure(
        message ?? 'Un compte existe déjà pour cet identifiant.',
      ),
      // 423 : compte bloqué. Un type à part, parce que l'écran n'a pas à
      // proposer la même chose que pour un PIN erroné.
      423 => AccountLockedFailure(
        message ?? 'Compte temporairement verrouillé après trop d’essais.',
      ),
      null => const NetworkFailure(),
      _ when status >= 500 => ServerFailure(
        message ?? 'Le serveur est momentanément indisponible.',
      ),
      _ => ServerFailure(message ?? 'Une erreur est survenue.'),
    };
  }

  /// Traduit un code métier de l'API en `Failure`.
  Failure _failureOfCode(AuthApiException error) => switch (error.code) {
    AuthApiException.accountLocked => AccountLockedFailure(error.message),
    AuthApiException.emailRequired ||
    AuthApiException.invalidOAuthToken ||
    // Ce que l'écran doit corriger avant de réessayer : un nom manquant,
    // une adresse ou un numéro déjà pris ailleurs.
    AuthApiException.nameRequired ||
    AuthApiException.emailTaken ||
    AuthApiException.phoneTaken => ValidationFailure(error.message),
    // Rien à corriger dans le formulaire : l'écran mène à la connexion.
    AuthApiException.accountExists => AccountExistsFailure(error.message),
    // Aucun canal n'a pu porter le code : il n'y a qu'à réessayer.
    AuthApiException.otpDeliveryUnavailable => AuthFailure(error.message),
    _ => AuthFailure(error.message),
  };

  static String? _messageOf(DioException error) {
    final data = error.response?.data;
    if (data is! Map) return null;

    final nested = data['error'];
    if (nested is Map && nested['message'] is String) {
      return nested['message'] as String;
    }

    final message = data['message'];
    return message is String ? message : null;
  }
}
