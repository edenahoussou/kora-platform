/// Ce pour quoi un code OTP est demandé.
///
/// Le même trio d'endpoints (`otp/request`, `otp/verify`, puis l'action)
/// sert les quatre cas : c'est ce champ qui les distingue côté serveur, et
/// le `verification_token` obtenu ne vaut que pour le motif demandé.
enum OtpPurpose {
  /// Création d'un compte.
  registration('registration'),

  /// Connexion depuis un appareil que le serveur ne connaît pas.
  loginNewDevice('login_new_device'),

  /// PIN oublié.
  pinReset('pin_reset'),

  /// Compte verrouillé après trop d'erreurs de PIN.
  accountUnlock('account_unlock');

  const OtpPurpose(this.value);

  /// Valeur attendue par l'API.
  final String value;

  static OtpPurpose fromValue(String value) => OtpPurpose.values.firstWhere(
    (purpose) => purpose.value == value,
    orElse: () => OtpPurpose.registration,
  );
}
