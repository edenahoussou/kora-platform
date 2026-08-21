import 'package:equatable/equatable.dart';

import 'otp_purpose.dart';

/// Un code envoyé par e-mail, en attente de vérification.
///
/// L'app ne connaît jamais le code : elle garde l'identifiant du défi, le
/// saisit avec ce que tape l'utilisateur, et l'API tranche.
class OtpChallenge extends Equatable {
  const OtpChallenge({
    required this.uuid,
    required this.purpose,
    required this.email,
    required this.expiresAt,
  });

  final String uuid;
  final OtpPurpose purpose;
  final String email;

  /// Passé ce moment, le code ne vaut plus rien et il faut en redemander
  /// un — c'est ce que décompte l'écran OTP.
  final DateTime expiresAt;

  bool isExpiredAt(DateTime now) => !now.isBefore(expiresAt);

  Duration remainingAt(DateTime now) {
    final left = expiresAt.difference(now);
    return left.isNegative ? Duration.zero : left;
  }

  @override
  List<Object?> get props => [uuid, purpose, email, expiresAt];
}

/// Le jeton à usage unique rendu par `otp/verify`, à présenter à l'action
/// qui suit (création de compte, connexion, réinitialisation du PIN...).
class OtpVerification extends Equatable {
  const OtpVerification({
    required this.challengeUuid,
    required this.purpose,
    required this.verificationToken,
  });

  final String challengeUuid;
  final OtpPurpose purpose;
  final String verificationToken;

  @override
  List<Object?> get props => [challengeUuid, purpose, verificationToken];
}
