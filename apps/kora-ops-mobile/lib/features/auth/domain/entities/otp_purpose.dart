/// Ce pour quoi un code OTP est demandé — voir `OtpPurpose` côté backend
/// (`app/Modules/IdentityAccess/Data/OtpPurpose.php`).
///
/// `registration` est gardé pour la parité avec le contrat serveur même si
/// le parcours d'inscription (`/auth/register/complete`) n'est pas dans le
/// périmètre de ce ticket — voir MEZ (ticket séparé).
enum OtpPurpose {
  registration('registration'),

  /// Connexion depuis un appareil que le serveur ne reconnaît pas.
  loginNewDevice('login_new_device'),

  /// PIN oublié.
  pinReset('pin_reset'),

  /// Compte verrouillé après trop d'erreurs de PIN.
  accountUnlock('account_unlock');

  const OtpPurpose(this.value);

  final String value;

  static OtpPurpose fromValue(String value) => OtpPurpose.values.firstWhere(
        (purpose) => purpose.value == value,
        orElse: () => OtpPurpose.loginNewDevice,
      );
}
