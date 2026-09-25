import 'package:equatable/equatable.dart';

import 'otp_purpose.dart';

/// Un code envoyé au compte, en attente de vérification.
///
/// L'app ne connaît jamais le code : elle garde l'identifiant du défi, le
/// saisit avec ce que tape l'utilisateur, et l'API tranche.
///
/// Elle ne choisit pas non plus la voie d'acheminement : le serveur
/// envoie par WhatsApp dès que le compte a un téléphone, et retombe sur
/// l'e-mail sinon.
class OtpChallenge extends Equatable {
  const OtpChallenge({
    required this.uuid,
    required this.purpose,
    required this.identifier,
    required this.expiresAt,
  });

  final String uuid;
  final OtpPurpose purpose;

  /// Le compte visé, tel qu'il a été saisi : un téléphone au format
  /// international, ou une adresse e-mail à l'inscription.
  final String identifier;

  /// Passé ce moment, le code ne vaut plus rien et il faut en redemander
  /// un — c'est ce que décompte l'écran OTP.
  final DateTime expiresAt;

  bool isExpiredAt(DateTime now) => !now.isBefore(expiresAt);

  Duration remainingAt(DateTime now) {
    final left = expiresAt.difference(now);
    return left.isNegative ? Duration.zero : left;
  }

  @override
  List<Object?> get props => [uuid, purpose, identifier, expiresAt];
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
