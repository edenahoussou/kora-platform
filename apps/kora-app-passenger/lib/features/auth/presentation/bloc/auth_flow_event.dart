part of 'auth_flow_bloc.dart';

sealed class AuthFlowEvent extends Equatable {
  const AuthFlowEvent();

  @override
  List<Object?> get props => [];
}

/// Le compte est donné — téléphone à la connexion, e-mail à
/// l'inscription : demande un code pour [purpose].
class AuthFlowIdentifierSubmitted extends AuthFlowEvent {
  const AuthFlowIdentifierSubmitted({
    required this.identifier,
    this.purpose = OtpPurpose.registration,
    this.name,
    this.referralCode,
  });

  /// Téléphone au format international (`+22997…`) ou adresse e-mail.
  final String identifier;
  final OtpPurpose purpose;

  /// Le nom du voyageur, recueilli à l'inscription par téléphone : le
  /// serveur n'a pas d'adresse d'où en dériver un et le réclame au
  /// moment de créer le compte. Il voyage donc depuis cet écran-ci
  /// jusqu'au choix du PIN.
  final String? name;

  /// Le code de parrainage facultatif, recueilli sur le même formulaire
  /// que l'identifiant. Il voyage lui aussi jusqu'à la création du
  /// compte : c'est elle, et elle seule, qui le rattache.
  final String? referralCode;

  @override
  List<Object?> get props => [identifier, purpose, name, referralCode];
}

/// Écran de connexion : téléphone + PIN.
class AuthFlowLoginSubmitted extends AuthFlowEvent {
  const AuthFlowLoginSubmitted({required this.identifier, required this.pin});

  final String identifier;
  final String pin;

  @override
  List<Object?> get props => [identifier, pin];
}

/// Écran OTP : les six chiffres saisis.
/// L'utilisateur demande la vérification d'un nouvel appareil, depuis le
/// lien de l'écran de connexion. Le serveur peut aussi l'imposer de
/// lui-même en répondant 202 à `login`.
class AuthFlowNewDeviceRequested extends AuthFlowEvent {
  const AuthFlowNewDeviceRequested(this.identifier);

  final String identifier;

  @override
  List<Object?> get props => [identifier];
}

/// « Recevoir le code OTP » sur l'écran du nouvel appareil.
class AuthFlowNewDeviceConfirmed extends AuthFlowEvent {
  const AuthFlowNewDeviceConfirmed();
}

class AuthFlowCodeSubmitted extends AuthFlowEvent {
  const AuthFlowCodeSubmitted(this.code);

  final String code;

  @override
  List<Object?> get props => [code];
}

/// « Je n'ai pas reçu le code » : redemande un code pour le même motif.
class AuthFlowCodeResent extends AuthFlowEvent {
  const AuthFlowCodeResent();
}

/// Écran « Créez votre PIN » : termine l'inscription, ou réinitialise le
/// PIN selon le motif du parcours.
class AuthFlowPinSubmitted extends AuthFlowEvent {
  const AuthFlowPinSubmitted(this.pin, {this.withReferralCode = true});

  final String pin;

  /// Emporte le code de parrainage recueilli au formulaire.
  ///
  /// Mis à faux pour **repartir sans lui** : le serveur l'a refusé, et
  /// le voyageur choisit de terminer son inscription quand même. Il
  /// pourra le rattacher ensuite depuis son Wallet, tant qu'il n'a pas
  /// voyagé.
  final bool withReferralCode;

  @override
  List<Object?> get props => [pin, withReferralCode];
}

/// Boutons Google / Facebook / TikTok, une fois le jeton du fournisseur
/// Remet le parcours à zéro (retour arrière, abandon).
class AuthFlowReset extends AuthFlowEvent {
  const AuthFlowReset();
}
