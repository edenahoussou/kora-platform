import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show
        ApiException,
        LockedApiException,
        ForbiddenApiException,
        ValidationApiException,
        UnauthorizedApiException,
        NetworkApiException,
        TimeoutApiException,
        ServerApiException;

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/auth_device.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/auth_user.dart';
import '../../domain/entities/otp_challenge.dart';
import '../../domain/entities/otp_purpose.dart';
import '../../domain/repositories/auth_repository.dart';
import '../datasources/auth_local_datasource.dart';
import '../datasources/auth_remote_datasource.dart';
import '../datasources/device_info_datasource.dart';

/// Couche DATA : assemble le réseau, le stockage sécurisé et l'identité de
/// l'appareil derrière le contrat du domaine. C'est ici que les
/// exceptions deviennent des [Failure] : au-dessus, personne ne connaît
/// Dio ni les codes HTTP.
///
/// Porté de kora-app-passenger — voir docstring de [AuthRepository].
class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl(
    this._remote,
    this._local,
    this._deviceInfo, {
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now;

  final AuthRemoteDataSource _remote;
  final AuthLocalDataSource _local;
  final DeviceInfoDataSource _deviceInfo;

  /// Horloge injectable : décide si une session est périmée ; les tests
  /// doivent pouvoir la figer.
  final DateTime Function() _now;

  @override
  Future<Result<OtpChallenge>> requestOtp({
    required String email,
    required OtpPurpose purpose,
  }) =>
      _attempt(() => _remote.requestOtp(email: email, purpose: purpose));

  @override
  Future<Result<OtpVerification>> verifyOtp({
    required String challengeUuid,
    required OtpPurpose purpose,
    required String code,
  }) =>
      _attempt(() => _remote.verifyOtp(
            challengeUuid: challengeUuid,
            purpose: purpose,
            code: code,
          ));

  @override
  Future<Result<LoginOutcome>> login({
    required String email,
    required String pin,
    OtpVerification? verification,
  }) =>
      _attempt(() async {
        final outcome = await _remote.login(
          email: email,
          pin: pin,
          device: await _deviceInfo.currentDevice(),
          verification: verification,
        );

        // Un appareil inconnu ne rend pas de session : rien à stocker,
        // l'app enchaîne sur la vérification du code.
        if (outcome is LoginSucceeded) {
          await _local.saveSession(outcome.session);
        }

        return outcome;
      }, loginAttempt: true);

  @override
  Future<Result<AuthSession?>> currentSession() => _attempt(() async {
        final session = await _local.readSession();
        if (session == null) return null;

        // Le token vaut ~7 jours et rien ne le renouvelle : une session
        // périmée est purgée ici plutôt que de laisser l'app enchaîner
        // les 401 jusqu'à ce que l'utilisateur comprenne.
        if (session.isExpiredAt(_now())) {
          await _local.clear();
          return null;
        }

        return session;
      });

  @override
  Future<String?> lastEmail() => _local.readLastEmail();

  @override
  Future<DeviceRegistration> currentDevice() => _deviceInfo.currentDevice();

  @override
  Future<Result<AuthUser>> refreshUser() => _attempt(() async {
        final user = await _remote.me();
        await _local.saveUser(user);
        return user;
      });

  @override
  Future<Result<void>> logout() async {
    // La purge locale a lieu quoi qu'il arrive : quelqu'un qui demande à
    // se déconnecter ne doit pas rester connecté sur son téléphone parce
    // que le serveur était injoignable. Le token restera valide côté
    // serveur jusqu'à son expiration — compromis assumé, comme documenté
    // dans le contrat du domaine.
    try {
      await _remote.logout();
      return const Result.success(null);
    } on DioException catch (error) {
      return Result.failure(_failureOf(error));
    } on AuthApiException catch (error) {
      return Result.failure(_failureOfCode(error));
    } finally {
      await _local.clear();
    }
  }

  @override
  Future<void> purgeLocalSession() => _local.clear();

  @override
  Future<Result<void>> resetPin({
    required OtpVerification verification,
    required String pin,
  }) =>
      _attempt(() => _remote.resetPin(verification: verification, pin: pin));

  @override
  Future<Result<void>> unlockAccount(OtpVerification verification) =>
      _attempt(() => _remote.unlockAccount(verification));

  /// Exécute [call] et traduit tout ce qui peut en sortir en [Failure].
  ///
  /// [loginAttempt] : un 403 sur `login` spécifiquement veut dire "compte
  /// suspendu" côté API (voir AuthSessionController) — ailleurs, un 403
  /// est un refus d'accès ordinaire.
  Future<Result<T>> _attempt<T>(
    Future<T> Function() call, {
    bool loginAttempt = false,
  }) async {
    try {
      return Result.success(await call());
    } on AuthApiException catch (error) {
      return Result.failure(_failureOfCode(error));
    } on DioException catch (error) {
      return Result.failure(_failureOf(error, loginAttempt: loginAttempt));
    } on ApiException catch (error) {
      return Result.failure(ServerFailure(error.message));
    } catch (error) {
      // Le stockage sécurisé ou la lecture des infos de l'appareil peuvent
      // échouer (ex. téléphone verrouillé par une politique d'entreprise).
      return Result.failure(UnknownFailure('$error'));
    }
  }

  Failure _failureOf(DioException error, {bool loginAttempt = false}) {
    final apiError = error.error;
    final message = apiError is ApiException ? apiError.message : null;

    return switch (apiError) {
      UnauthorizedApiException() =>
        AuthenticationFailure(message ?? 'Identifiants incorrects.'),
      ForbiddenApiException() => loginAttempt
          ? AccountSuspendedFailure(
              message ?? 'Compte suspendu. Contactez votre responsable.')
          : AuthenticationFailure(message ?? 'Accès refusé.'),
      ValidationApiException() =>
        ValidationFailure(message ?? 'Les données saisies sont invalides.'),
      LockedApiException() => AccountLockedFailure(
          message ?? 'Compte temporairement verrouillé après trop d’essais.',
        ),
      NetworkApiException() => const NetworkFailure(),
      TimeoutApiException() =>
        const NetworkFailure('Le serveur met trop de temps à répondre.'),
      ServerApiException() =>
        ServerFailure(message ?? 'Le serveur est momentanément indisponible.'),
      _ => ServerFailure(message ?? 'Une erreur est survenue.'),
    };
  }

  Failure _failureOfCode(AuthApiException error) => switch (error.code) {
        AuthApiException.accountSuspended =>
          AccountSuspendedFailure(error.message),
        _ => AuthenticationFailure(error.message),
      };
}
