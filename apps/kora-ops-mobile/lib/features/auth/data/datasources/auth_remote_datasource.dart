import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/auth_device.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/auth_user.dart';
import '../../domain/entities/otp_challenge.dart';
import '../../domain/entities/otp_purpose.dart';
import '../models/auth_dtos.dart';

/// Erreur métier renvoyée par l'API sous la forme
/// `{ "error": { "code": "account_suspended", "message": "…" } }`.
/// Distincte des `ApiException` du package réseau, qui ne connaissent que
/// des statuts HTTP.
class AuthApiException implements Exception {
  const AuthApiException(this.code, this.message);

  static const String accountSuspended = 'account_suspended';

  final String code;
  final String message;

  @override
  String toString() => 'AuthApiException($code): $message';
}

/// Couche DATA : les appels réseau du flux d'authentification. Lève des
/// exceptions ; c'est le repository qui les traduit en [Failure].
abstract class AuthRemoteDataSource {
  Future<OtpChallenge> requestOtp({
    required String email,
    required OtpPurpose purpose,
  });

  Future<OtpVerification> verifyOtp({
    required String challengeUuid,
    required OtpPurpose purpose,
    required String code,
  });

  Future<LoginOutcome> login({
    required String email,
    required String pin,
    required DeviceRegistration device,
    OtpVerification? verification,
  });

  Future<AuthUser> me();

  Future<void> logout();

  Future<void> resetPin({
    required OtpVerification verification,
    required String pin,
  });

  Future<void> unlockAccount(OtpVerification verification);
}

class AuthRemoteDataSourceImpl implements AuthRemoteDataSource {
  AuthRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<OtpChallenge> requestOtp({
    required String email,
    required OtpPurpose purpose,
  }) async {
    final response = await _post(ApiConstants.otpRequest, <String, dynamic>{
      'email': email,
      'purpose': purpose.value,
    });

    return AuthDtos.otpChallenge(response.data, email: email);
  }

  @override
  Future<OtpVerification> verifyOtp({
    required String challengeUuid,
    required OtpPurpose purpose,
    required String code,
  }) async {
    final response = await _post(ApiConstants.otpVerify, <String, dynamic>{
      'otp_challenge_uuid': challengeUuid,
      'purpose': purpose.value,
      'code': code,
    });

    return AuthDtos.otpVerification(response.data);
  }

  @override
  Future<LoginOutcome> login({
    required String email,
    required String pin,
    required DeviceRegistration device,
    OtpVerification? verification,
  }) async {
    final response = await _post(ApiConstants.login, <String, dynamic>{
      'email': email,
      'pin': pin,
      ...device.toJson(),
      if (verification != null) ...<String, dynamic>{
        'otp_challenge_uuid': verification.challengeUuid,
        'verification_token': verification.verificationToken,
      },
    });

    // Appareil inconnu : 202 sans token, avec un défi à relever. C'est un
    // déroulement normal, pas une erreur.
    if (response.statusCode == 202 || AuthDtos.requiresOtp(response.data)) {
      return LoginNeedsOtp(AuthDtos.loginChallenge(response.data));
    }

    return LoginSucceeded(AuthDtos.session(response.data));
  }

  @override
  Future<AuthUser> me() async {
    final response = await _guard(() => _dio.get<dynamic>(ApiConstants.me));
    return AuthDtos.user(AuthDtos.unwrap(response.data));
  }

  @override
  Future<void> logout() => _guard(() => _dio.post<void>(ApiConstants.logout));

  @override
  Future<void> resetPin({
    required OtpVerification verification,
    required String pin,
  }) =>
      _post(ApiConstants.pinReset, <String, dynamic>{
        'otp_challenge_uuid': verification.challengeUuid,
        'verification_token': verification.verificationToken,
        'pin': pin,
        'pin_confirmation': pin,
      });

  @override
  Future<void> unlockAccount(OtpVerification verification) =>
      _post(ApiConstants.unlock, <String, dynamic>{
        'otp_challenge_uuid': verification.challengeUuid,
        'verification_token': verification.verificationToken,
      });

  Future<Response<dynamic>> _post(String path, Map<String, dynamic> body) {
    return _guard(
      () => _dio.post<dynamic>(
        path,
        data: body,
        // 202 est une réponse attendue (OTP demandé, appareil inconnu),
        // pas une erreur : sans ça Dio la transformerait en exception.
        options: Options(
          validateStatus: (int? status) => status != null && status < 400,
        ),
      ),
    );
  }

  /// Traduit les erreurs métier de l'API (corps `{ "error": { "code" } }`)
  /// avant qu'elles ne remontent — le reste (401/422/423/5xx/réseau) est
  /// déjà traduit par l'`ErrorInterceptor` du package réseau, plus haut
  /// dans la chaîne.
  Future<T> _guard<T>(Future<T> Function() call) async {
    try {
      return await call();
    } on DioException catch (error) {
      final code = AuthDtos.errorCode(error.response?.data);
      if (code == null) rethrow;

      throw AuthApiException(
        code,
        _messageOf(error.response?.data) ?? 'Erreur d’authentification.',
      );
    }
  }

  static String? _messageOf(Object? body) {
    if (body is! Map) return null;

    final error = body['error'];
    if (error is Map && error['message'] is String) {
      return error['message'] as String;
    }

    final message = body['message'];
    return message is String ? message : null;
  }
}
