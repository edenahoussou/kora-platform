import '../../domain/entities/auth_device.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/auth_user.dart';
import '../../domain/entities/device_platform.dart';
import '../../domain/entities/otp_challenge.dart';
import '../../domain/entities/otp_purpose.dart';

/// Couche DATA : lecture des réponses JSON de l'API d'authentification.
///
/// Les entités du domaine restent ignorantes du transport ; c'est ici, et
/// seulement ici, qu'on connaît les noms de champs de l'API
/// (`otp_challenge_uuid`, `expires_at`...).
///
/// Toutes les réponses documentées sont enveloppées dans `data` — c'est
/// [unwrap] qui l'ouvre.
abstract final class AuthDtos {
  /// Ouvre l'enveloppe `{ "data": … }`.
  ///
  /// Tolère une réponse déjà « à plat » : une API qui cesserait
  /// d'envelopper ne doit pas faire tomber l'app.
  static Map<String, dynamic> unwrap(Object? body) {
    if (body is! Map) return const <String, dynamic>{};

    final data = body['data'];
    if (data is Map) return Map<String, dynamic>.from(data);

    return Map<String, dynamic>.from(body);
  }

  static OtpChallenge otpChallenge(Object? body, {required String email}) {
    final data = unwrap(body);

    return OtpChallenge(
      uuid: '${data['otp_challenge_uuid']}',
      purpose: OtpPurpose.fromValue('${data['purpose']}'),
      // L'API renvoie l'e-mail visé ; on garde celui saisi en secours,
      // l'écran OTP l'affiche masqué.
      email: data['email'] as String? ?? email,
      expiresAt: _date(data['expires_at']) ?? DateTime.now().add(_otpFallback),
    );
  }

  /// Durée de validité retenue quand l'API n'en donne pas : la doc annonce
  /// « environ 15 minutes ». Mieux vaut un décompte approché qu'un écran
  /// sans échéance.
  static const Duration _otpFallback = Duration(minutes: 15);

  static OtpVerification otpVerification(Object? body) {
    final data = unwrap(body);

    return OtpVerification(
      challengeUuid: '${data['otp_challenge_uuid']}',
      purpose: OtpPurpose.fromValue('${data['purpose']}'),
      verificationToken: '${data['verification_token']}',
    );
  }

  static AuthSession session(Object? body) {
    final data = unwrap(body);

    return AuthSession(
      token: '${data['token']}',
      tokenType: data['token_type'] as String? ?? 'Bearer',
      expiresAt:
          _date(data['expires_at']) ?? DateTime.now().add(_tokenFallback),
      user: user(data['user']),
      device: data['device'] == null ? null : device(data['device']),
    );
  }

  /// Durée de vie retenue si l'API n'annonce pas d'échéance : la doc parle
  /// de ~7 jours. Faute d'endpoint de rafraîchissement, l'app doit savoir
  /// quand cesser de faire confiance au token.
  static const Duration _tokenFallback = Duration(days: 7);

  static AuthUser user(Object? raw) {
    // `auth/me` répond `{ data: { user: {...} } }` alors que la connexion
    // met l'utilisateur directement dans `data.user` : on accepte les deux
    // plutôt que d'avoir deux lecteurs presque identiques.
    final source = raw is Map && raw['user'] is Map ? raw['user'] : raw;
    final data = source is Map
        ? Map<String, dynamic>.from(source)
        : const <String, dynamic>{};

    return AuthUser(
      uuid: '${data['uuid']}',
      name: data['name'] as String? ?? '',
      email: data['email'] as String? ?? '',
      phone: data['phone'] as String?,
      countryCode: data['country_code'] as String?,
      birthDate: _date(data['birth_date']),
      status: data['status'] as String? ?? 'active',
      avatarUrl: data['avatar_url'] as String?,
      permissions: _strings(data['permissions']),
    );
  }

  static AuthDevice device(Object? raw) {
    final data = raw is Map
        ? Map<String, dynamic>.from(raw)
        : const <String, dynamic>{};

    return AuthDevice(
      uuid: '${data['uuid']}',
      name: data['name'] as String? ?? '',
      platform: DevicePlatform.fromValue(data['platform'] as String?),
      status: data['status'] as String? ?? 'trusted',
      trustedAt: _date(data['trusted_at']),
      lastSeenAt: _date(data['last_seen_at']),
    );
  }

  static List<AuthDevice> devices(Object? body) {
    final data = body is Map ? body['data'] : body;
    if (data is! List) return const [];

    return data.map(device).toList();
  }

  /// Le 202 de connexion depuis un appareil inconnu.
  static OtpChallengeRef loginChallenge(Object? body) {
    final data = unwrap(body);

    return OtpChallengeRef(
      uuid: '${data['otp_challenge_uuid']}',
      expiresAt: _date(data['expires_at']) ?? DateTime.now().add(_otpFallback),
    );
  }

  /// Une réponse de connexion porte-t-elle une demande d'OTP plutôt qu'un
  /// token ?
  static bool requiresOtp(Object? body) => unwrap(body)['requires_otp'] == true;

  /// Code métier d'une erreur (`account_locked`, `email_required`...),
  /// dans l'enveloppe `{ "error": { "code": … } }`.
  static String? errorCode(Object? body) {
    if (body is! Map) return null;

    final error = body['error'];
    if (error is! Map) return null;

    final code = error['code'];
    return code is String ? code : null;
  }

  static DateTime? _date(Object? raw) {
    if (raw is! String || raw.isEmpty) return null;
    return DateTime.tryParse(raw)?.toLocal();
  }

  static List<String> _strings(Object? raw) {
    if (raw is! List) return const [];
    return raw.map((value) => '$value').toList();
  }
}
