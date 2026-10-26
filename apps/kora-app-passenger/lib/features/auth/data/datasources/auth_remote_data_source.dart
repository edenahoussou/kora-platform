import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/auth_device.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/auth_user.dart';
import '../../domain/entities/otp_challenge.dart';
import '../../domain/entities/otp_purpose.dart';
import '../../domain/entities/user_preferences.dart';
import '../models/auth_dtos.dart';

/// Erreur métier renvoyée par l'API sous la forme
/// `{ "error": { "code": "account_locked", "message": "…" } }`.
///
/// Distincte des [ApiException] du package réseau, qui ne connaissent que
/// des statuts HTTP : c'est le **code** qui dit à l'app quoi proposer —
/// un compte verrouillé se rouvre par un OTP, un email manquant demande
/// un autre fournisseur.
class AuthApiException implements Exception {
  const AuthApiException(this.code, this.message);

  /// Codes documentés dans `docs/mintlify/guides/error-handling.mdx`.
  static const String accountLocked = 'account_locked';
  static const String accountSuspended = 'account_suspended';
  static const String emailRequired = 'email_required';

  /// Inscription par téléphone d'un compte neuf : le serveur n'a pas
  /// d'adresse d'où tirer un nom, il le réclame.
  static const String nameRequired = 'name_required';

  /// Un compte avec PIN existe déjà pour cet identifiant : c'est une
  /// connexion qu'il faut, pas une inscription.
  static const String accountExists = 'account_exists';

  static const String emailTaken = 'email_taken';
  static const String phoneTaken = 'phone_taken';

  /// Ni WhatsApp ni e-mail n'ont pu porter le code : le serveur refuse
  /// plutôt que de créer un défi que personne ne recevra.
  static const String otpDeliveryUnavailable = 'otp_delivery_unavailable';

  final String code;
  final String message;

  @override
  String toString() => 'AuthApiException($code): $message';
}

/// Couche DATA : les appels réseau du flux d'authentification.
///
/// Suit `docs/mintlify/authentication.mdx` et la spec OpenAPI du dépôt.
/// Lève des exceptions ; c'est le repository qui les traduit en `Failure`.
abstract class AuthRemoteDataSource {
  Future<OtpChallenge> requestOtp({
    required String identifier,
    required OtpPurpose purpose,
  });

  Future<OtpVerification> verifyOtp({
    required String challengeUuid,
    required OtpPurpose purpose,
    required String code,
  });

  Future<AuthSession> completeRegistration({
    required OtpVerification verification,
    required String pin,
    required DeviceRegistration device,
    String? name,
    String? email,
    String? phone,
    String? countryCode,
  });

  Future<LoginOutcome> login({
    required String identifier,
    required String pin,
    required DeviceRegistration device,
    OtpVerification? verification,
  });

  Future<AuthUser> me();

  Future<AuthUser> updateProfile({
    required String name,
    required String email,
    String? phone,
    String? countryCode,
    DateTime? birthDate,
  });

  Future<void> logout();

  Future<void> resetPin({
    required OtpVerification verification,
    required String pin,
  });

  Future<void> changePin({required String currentPin, required String newPin});

  Future<void> unlockAccount(OtpVerification verification);

  Future<List<AuthDevice>> devices();

  Future<UserPreferences> preferences();

  Future<UserPreferences> updatePreferences(UserPreferences preferences);

  Future<void> revokeDevice(String uuid);

  /// `DELETE auth/me` : demande la suppression du compte.
  Future<void> requestAccountDeletion({required String pin, String? reason});
}

class AuthRemoteDataSourceImpl implements AuthRemoteDataSource {
  AuthRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<OtpChallenge> requestOtp({
    required String identifier,
    required OtpPurpose purpose,
  }) async {
    final response = await _post(ApiConstants.otpRequest, {
      // `identifier` accepte le téléphone comme l'e-mail ; `email`, son
      // alias d'avant, ne prend que le second. Les deux ensemble sont
      // refusés par l'API, on n'envoie donc que celui-ci.
      'identifier': identifier,
      'purpose': purpose.value,
    });

    return AuthDtos.otpChallenge(response.data, identifier: identifier);
  }

  @override
  Future<OtpVerification> verifyOtp({
    required String challengeUuid,
    required OtpPurpose purpose,
    required String code,
  }) async {
    final response = await _post(ApiConstants.otpVerify, {
      'otp_challenge_uuid': challengeUuid,
      'purpose': purpose.value,
      'code': code,
    });

    return AuthDtos.otpVerification(response.data);
  }

  @override
  Future<AuthSession> completeRegistration({
    required OtpVerification verification,
    required String pin,
    required DeviceRegistration device,
    String? name,
    String? email,
    String? phone,
    String? countryCode,
  }) async {
    final response = await _post(ApiConstants.registerComplete, {
      'otp_challenge_uuid': verification.challengeUuid,
      'verification_token': verification.verificationToken,
      'pin': pin,
      // Facultatifs, sauf `name` pour une inscription par téléphone : le
      // serveur n'a alors pas d'adresse d'où tirer un nom, et répond
      // `422 name_required`. On n'envoie que ce qui est renseigné —
      // une clé nulle vaudrait effacement du côté du serveur.
      if (name != null && name.isNotEmpty) 'name': name,
      if (email != null && email.isNotEmpty) 'email': email,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      if (countryCode != null && countryCode.isNotEmpty)
        'country_code': countryCode,
      // Le serveur valide le PIN avec la règle `confirmed` de Laravel :
      // sans `pin_confirmation` identique, il répond 422 « La confirmation
      // du PIN ne correspond pas. ». L'écran fait déjà saisir le code deux
      // fois, la comparaison a donc lieu avant l'appel.
      'pin_confirmation': pin,
      ...device.toJson(),
    });

    return AuthDtos.session(response.data);
  }

  @override
  Future<LoginOutcome> login({
    required String identifier,
    required String pin,
    required DeviceRegistration device,
    OtpVerification? verification,
  }) async {
    final response = await _post(ApiConstants.login, {
      'identifier': identifier,
      'pin': pin,
      ...device.toJson(),
      // Les deux champs vont ensemble : l'API refuse l'un sans l'autre.
      if (verification != null) ...{
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
  Future<AuthUser> updateProfile({
    required String name,
    required String email,
    String? phone,
    String? countryCode,
    DateTime? birthDate,
  }) async {
    final response = await _guard(
      () => _dio.put<dynamic>(
        ApiConstants.me,
        data: {
          'name': name,
          // Obligatoire côté serveur, même quand l'écran appelant ne le
          // modifie pas : il renvoie alors l'adresse du compte.
          'email': email,
          'phone': phone,
          'country_code': countryCode,
          // `date` côté Laravel : la partie horaire n'a pas de sens pour
          // une date de naissance et serait refusée telle quelle.
          'birth_date': birthDate?.toIso8601String().split('T').first,
        },
      ),
    );

    return AuthDtos.user(response.data);
  }

  @override
  Future<UserPreferences> preferences() async {
    final response = await _guard(
      () => _dio.get<dynamic>(ApiConstants.preferences),
    );

    return AuthDtos.preferences(response.data);
  }

  @override
  Future<UserPreferences> updatePreferences(UserPreferences preferences) async {
    final response = await _guard(
      () => _dio.put<dynamic>(
        ApiConstants.preferences,
        data: {
          'push_notifications': preferences.pushNotifications,
          'whatsapp_notifications': preferences.whatsappNotifications,
          'sms_notifications': preferences.smsNotifications,
          'email_notifications': preferences.emailNotifications,
          'locale': preferences.locale,
          'theme': preferences.theme,
        },
      ),
    );

    return AuthDtos.preferences(response.data);
  }

  @override
  Future<void> logout() => _guard(() => _dio.post<void>(ApiConstants.logout));

  @override
  Future<void> resetPin({
    required OtpVerification verification,
    required String pin,
  }) => _post(ApiConstants.pinReset, {
    'otp_challenge_uuid': verification.challengeUuid,
    'verification_token': verification.verificationToken,
    'pin': pin,
    'pin_confirmation': pin,
  });

  @override
  Future<void> changePin({
    required String currentPin,
    required String newPin,
  }) =>
      // Le serveur attend `new_pin` (et sa confirmation), pas `pin`.
      _post(ApiConstants.pinChange, {
        'current_pin': currentPin,
        'new_pin': newPin,
        'new_pin_confirmation': newPin,
      });

  @override
  Future<void> unlockAccount(OtpVerification verification) =>
      _post(ApiConstants.unlock, {
        'otp_challenge_uuid': verification.challengeUuid,
        'verification_token': verification.verificationToken,
      });

  @override
  Future<List<AuthDevice>> devices() async {
    final response = await _guard(
      () => _dio.get<dynamic>(ApiConstants.devices),
    );
    return AuthDtos.devices(response.data);
  }

  @override
  Future<void> revokeDevice(String uuid) =>
      _guard(() => _dio.delete<void>(ApiConstants.device(uuid)));

  @override
  Future<void> requestAccountDeletion({required String pin, String? reason}) =>
      _guard(
        () => _dio.delete<void>(
          ApiConstants.me,
          data: {
            'pin': pin,
            if (reason != null && reason.trim().isNotEmpty)
              'reason': reason.trim(),
          },
        ),
      );

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

  /// Traduit les erreurs métier de l'API avant qu'elles ne remontent.
  ///
  /// Le package réseau ne connaît que des statuts HTTP ; les codes
  /// documentés (`account_locked`, `email_required`...) vivent dans le
  /// corps de la réponse et disparaîtraient sans cette lecture.
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
