part of 'auth_flow_bloc.dart';

/// L'étape à afficher. C'est le parcours qui décide de l'écran suivant,
/// pas l'écran lui-même.
enum AuthFlowStep {
  /// Saisie de l'identifiant — email ou téléphone (première ouverture de
  /// l'app sur cet appareil, ou lien "pas moi" depuis l'écran PIN).
  identifier,

  /// Saisie du PIN — identifiant déjà connu (appareil déjà reconnu ou
  /// juste saisi à l'étape précédente).
  pin,

  /// Saisie du code à six chiffres — appareil inconnu du serveur, ou
  /// réinitialisation de PIN en cours.
  code,

  /// Choix d'un nouveau PIN, après un OTP `pin_reset` vérifié.
  newPin,

  /// Session ouverte : l'appelant (AuthCubit.authenticate) navigue vers
  /// l'accueil.
  done,
}

class AuthFlowState extends Equatable {
  const AuthFlowState({
    this.step = AuthFlowStep.identifier,
    this.purpose = OtpPurpose.loginNewDevice,
    this.isSubmitting = false,
    this.challenge,
    this.verification,
    this.session,
    this.identifier = '',
    this.pendingPin = '',
    this.errorMessage = '',
    this.isAccountLocked = false,
  });

  final AuthFlowStep step;
  final OtpPurpose purpose;
  final bool isSubmitting;

  /// Le défi OTP en cours, avec son échéance.
  final OtpChallenge? challenge;

  /// Le jeton à usage unique obtenu après vérification du code.
  final OtpVerification? verification;

  /// Renseignée quand [step] vaut [AuthFlowStep.done].
  final AuthSession? session;

  /// Email ou numéro de téléphone (E.164) saisi par l'utilisateur.
  final String identifier;

  /// PIN saisi lors d'une connexion, gardé le temps d'un aller-retour par
  /// l'OTP : le serveur le redemande quand on rappelle `login` avec le
  /// `verification_token`, et le faire retaper serait absurde.
  final String pendingPin;

  final String errorMessage;

  /// Compte bloqué après trop d'erreurs de PIN — l'écran propose alors le
  /// déverrouillage plutôt qu'un simple message d'échec.
  final bool isAccountLocked;

  bool get hasError => errorMessage.isNotEmpty;

  AuthFlowState copyWith({
    AuthFlowStep? step,
    OtpPurpose? purpose,
    bool? isSubmitting,
    OtpChallenge? challenge,
    OtpVerification? verification,
    AuthSession? session,
    String? identifier,
    String? pendingPin,
    String? errorMessage,
    bool? isAccountLocked,
  }) {
    return AuthFlowState(
      step: step ?? this.step,
      purpose: purpose ?? this.purpose,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      challenge: challenge ?? this.challenge,
      verification: verification ?? this.verification,
      session: session ?? this.session,
      identifier: identifier ?? this.identifier,
      pendingPin: pendingPin ?? this.pendingPin,
      errorMessage: errorMessage ?? this.errorMessage,
      isAccountLocked: isAccountLocked ?? this.isAccountLocked,
    );
  }

  @override
  List<Object?> get props => <Object?>[
        step,
        purpose,
        isSubmitting,
        challenge,
        verification,
        session,
        identifier,
        pendingPin,
        errorMessage,
        isAccountLocked,
      ];
}
