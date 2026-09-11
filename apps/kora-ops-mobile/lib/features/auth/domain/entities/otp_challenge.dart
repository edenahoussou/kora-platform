import 'package:equatable/equatable.dart';

import 'otp_purpose.dart';

/// Un code à six chiffres envoyé par e-mail, en attente de vérification.
/// L'app ne connaît jamais le code lui-même — seulement l'identifiant du
/// défi.
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

  /// Passé ce moment, le code ne vaut plus rien.
  final DateTime expiresAt;

  bool isExpiredAt(DateTime now) => !now.isBefore(expiresAt);

  Duration remainingAt(DateTime now) {
    final left = expiresAt.difference(now);
    return left.isNegative ? Duration.zero : left;
  }

  @override
  List<Object?> get props => <Object?>[uuid, purpose, email, expiresAt];
}

/// Le jeton à usage unique rendu par `otp/verify`, à présenter à l'appel
/// qui suit (ici : `login`, rejoué avec ce jeton).
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
  List<Object?> get props =>
      <Object?>[challengeUuid, purpose, verificationToken];
}
