part of 'auth_flow_bloc.dart';

/// L'étape à afficher.
///
/// C'est le parcours qui décide de l'écran suivant, pas l'écran lui-même :
/// après un code vérifié, on va choisir un PIN si on crée un compte, mais
/// on retourne à la connexion si le code servait à valider un appareil.
enum AuthFlowStep {
  /// Saisie du compte : le téléphone à la connexion, l'adresse e-mail à
  /// l'inscription.
  identifier,

  /// Appareil inconnu du serveur : on explique la vérification avant de
  /// demander le code (écran `new-device` de la maquette).
  newDevice,

  /// Saisie du code à six chiffres.
  code,

  /// Choix du PIN (inscription ou réinitialisation).
  pin,

  /// Session ouverte : l'appelant navigue vers l'accueil.
  done,
}

class AuthFlowState extends Equatable {
  const AuthFlowState({
    this.step = AuthFlowStep.identifier,
    this.purpose = OtpPurpose.registration,
    this.justRegistered = false,
    this.isSubmitting = false,
    this.challenge,
    this.verification,
    this.session,
    this.identifier = '',
    this.registrationName = '',
    this.registrationReferralCode = '',
    this.pendingPin = '',
    this.errorMessage = '',
    this.referralError = '',
    this.isAccountLocked = false,
    this.accountExists = false,
    this.deviceName = '',
  });

  final AuthFlowStep step;
  final OtpPurpose purpose;

  /// Le compte vient d'être créé à l'instant.
  ///
  /// **Ne se déduit pas de [purpose].** Celui-ci vaut `registration` par
  /// défaut — c'est le motif d'OTP le plus courant — et une connexion
  /// ordinaire ne le change jamais, faute d'OTP. S'y fier renvoyait donc
  /// tout le monde à l'écran des autorisations après chaque connexion,
  /// pour y redemander un nom et un téléphone déjà enregistrés.
  final bool justRegistered;

  /// Une requête est en cours : les écrans désactivent leur bouton.
  final bool isSubmitting;

  /// Le défi en cours, avec son échéance — c'est lui que décompte l'écran
  /// OTP.
  final OtpChallenge? challenge;

  /// Le jeton à usage unique obtenu après vérification du code.
  final OtpVerification? verification;

  /// Renseignée quand [step] vaut [AuthFlowStep.done].
  final AuthSession? session;

  /// Le compte du parcours en cours : téléphone au format international,
  /// ou e-mail pour une inscription.
  final String identifier;

  /// Le nom saisi à l'inscription par téléphone, gardé jusqu'à la
  /// création du compte — c'est `register/complete` qui l'exige, pas
  /// l'écran du code.
  final String registrationName;

  /// Le code de parrainage saisi au formulaire d'inscription, gardé
  /// jusqu'à la création du compte — c'est `register/complete` qui le
  /// rattache, et elle n'a lieu qu'au bout du parcours.
  final String registrationReferralCode;

  /// PIN saisi lors d'une connexion, gardé le temps d'un aller-retour par
  /// l'OTP : le serveur le redemande quand on rappelle `login` avec le
  /// `verification_token`, et le faire retaper serait absurde.
  final String pendingPin;

  final String errorMessage;

  /// Pourquoi le serveur a refusé le code de parrainage de
  /// l'inscription — son `errors.referral_code`.
  ///
  /// À part de [errorMessage] parce que la suite n'est pas la même : ce
  /// n'est pas l'inscription qui a échoué sur le fond, c'est un champ
  /// facultatif à corriger ou à retirer. L'écran le montre sous ce
  /// champ, PIN et code OTP toujours en main.
  final String referralError;

  /// Compte bloqué après trop d'erreurs de PIN : l'écran propose alors le
  /// déverrouillage plutôt qu'un simple message d'échec.
  final bool isAccountLocked;

  /// L'identifiant donné à l'inscription porte déjà un compte : l'écran
  /// mène à la connexion plutôt que de laisser retaper la même chose.
  final bool accountExists;

  /// Nom lisible de cet appareil, lu au moment où le serveur le refuse.
  /// L'écran « Nouvel appareil » le montre : c'est ce qui permet de
  /// reconnaître sa propre tentative, ou celle de quelqu'un d'autre.
  final String deviceName;

  bool get hasError => errorMessage.isNotEmpty;

  AuthFlowState copyWith({
    AuthFlowStep? step,
    OtpPurpose? purpose,
    bool? justRegistered,
    bool? isSubmitting,
    OtpChallenge? challenge,
    OtpVerification? verification,
    AuthSession? session,
    String? identifier,
    String? registrationName,
    String? registrationReferralCode,
    String? pendingPin,
    String? errorMessage,
    String? referralError,
    bool? isAccountLocked,
    bool? accountExists,
    String? deviceName,
  }) {
    return AuthFlowState(
      step: step ?? this.step,
      purpose: purpose ?? this.purpose,
      justRegistered: justRegistered ?? this.justRegistered,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      challenge: challenge ?? this.challenge,
      verification: verification ?? this.verification,
      session: session ?? this.session,
      identifier: identifier ?? this.identifier,
      registrationName: registrationName ?? this.registrationName,
      registrationReferralCode:
          registrationReferralCode ?? this.registrationReferralCode,
      pendingPin: pendingPin ?? this.pendingPin,
      errorMessage: errorMessage ?? this.errorMessage,
      referralError: referralError ?? this.referralError,
      isAccountLocked: isAccountLocked ?? this.isAccountLocked,
      accountExists: accountExists ?? this.accountExists,
      deviceName: deviceName ?? this.deviceName,
    );
  }

  @override
  List<Object?> get props => [
    step,
    purpose,
    justRegistered,
    isSubmitting,
    challenge,
    verification,
    session,
    identifier,
    registrationName,
    registrationReferralCode,
    pendingPin,
    errorMessage,
    referralError,
    isAccountLocked,
    accountExists,
    deviceName,
  ];
}
