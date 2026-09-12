import 'package:equatable/equatable.dart';

import 'auth_device.dart';
import 'auth_user.dart';

/// Une session ouverte : le token Sanctum, son porteur et l'appareil
/// depuis lequel elle a été ouverte.
///
/// **Il n'existe aucun endpoint de rafraîchissement** côté API : le token
/// vaut environ 7 jours, et son expiration se traite comme une
/// déconnexion — l'utilisateur ressaisit son PIN (pas l'e-mail, l'appareil
/// reste de confiance).
class AuthSession extends Equatable {
  const AuthSession({
    required this.token,
    required this.expiresAt,
    required this.user,
    this.device,
    this.tokenType = 'Bearer',
  });

  final String token;
  final String tokenType;
  final DateTime expiresAt;
  final AuthUser user;
  final AuthDevice? device;

  bool isExpiredAt(DateTime now) => !now.isBefore(expiresAt);

  String get authorizationHeader => '$tokenType $token';

  AuthSession copyWith({AuthUser? user}) => AuthSession(
        token: token,
        tokenType: tokenType,
        expiresAt: expiresAt,
        user: user ?? this.user,
        device: device,
      );

  @override
  List<Object?> get props =>
      <Object?>[token, tokenType, expiresAt, user, device];
}

/// Ce que rend une tentative de connexion : soit une session, soit une
/// demande d'OTP quand le serveur ne reconnaît pas l'appareil (202).
///
/// Deux cas de succès, pas un succès et une erreur : un appareil inconnu
/// est le déroulement normal d'une première connexion sur un téléphone.
sealed class LoginOutcome extends Equatable {
  const LoginOutcome();

  @override
  List<Object?> get props => const <Object?>[];
}

class LoginSucceeded extends LoginOutcome {
  const LoginSucceeded(this.session);

  final AuthSession session;

  @override
  List<Object?> get props => <Object?>[session];
}

/// Appareil inconnu : il faut vérifier un code, puis rappeler la connexion
/// avec le `verification_token`.
class LoginNeedsOtp extends LoginOutcome {
  const LoginNeedsOtp(this.challenge);

  final OtpChallengeRef challenge;

  @override
  List<Object?> get props => <Object?>[challenge];
}

/// Le défi renvoyé dans une réponse 202 de connexion.
class OtpChallengeRef extends Equatable {
  const OtpChallengeRef({required this.uuid, required this.expiresAt});

  final String uuid;
  final DateTime expiresAt;

  @override
  List<Object?> get props => <Object?>[uuid, expiresAt];
}
