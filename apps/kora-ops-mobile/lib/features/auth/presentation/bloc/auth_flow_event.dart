part of 'auth_flow_bloc.dart';

sealed class AuthFlowEvent extends Equatable {
  const AuthFlowEvent();

  @override
  List<Object?> get props => <Object?>[];
}

/// Écran identifiant : mémorise l'email ou le téléphone saisi et passe à
/// l'étape suivante — aucun appel réseau ici, contrairement à l'app
/// passager (pas d'inscription dans ce périmètre, donc rien à envoyer
/// avant d'avoir aussi le PIN).
class AuthFlowIdentifierEntered extends AuthFlowEvent {
  const AuthFlowIdentifierEntered(this.identifier);

  final String identifier;

  @override
  List<Object?> get props => <Object?>[identifier];
}

/// Écran PIN : identifiant + PIN, tentative de connexion.
class AuthFlowLoginSubmitted extends AuthFlowEvent {
  const AuthFlowLoginSubmitted({required this.identifier, required this.pin});

  final String identifier;
  final String pin;

  @override
  List<Object?> get props => <Object?>[identifier, pin];
}

/// Écran OTP : les six chiffres saisis.
class AuthFlowCodeSubmitted extends AuthFlowEvent {
  const AuthFlowCodeSubmitted(this.code);

  final String code;

  @override
  List<Object?> get props => <Object?>[code];
}

/// « Je n'ai pas reçu le code » : redemande un code pour le même motif.
class AuthFlowCodeResent extends AuthFlowEvent {
  const AuthFlowCodeResent();
}

/// Lien « PIN oublié » de l'écran de connexion.
class AuthFlowPinResetRequested extends AuthFlowEvent {
  const AuthFlowPinResetRequested(this.identifier);

  final String identifier;

  @override
  List<Object?> get props => <Object?>[identifier];
}

/// Écran « Choisissez un nouveau PIN », après un OTP `pin_reset` vérifié.
class AuthFlowNewPinSubmitted extends AuthFlowEvent {
  const AuthFlowNewPinSubmitted(this.pin);

  final String pin;

  @override
  List<Object?> get props => <Object?>[pin];
}

/// Remet le parcours à zéro (retour arrière, abandon, déconnexion).
class AuthFlowReset extends AuthFlowEvent {
  const AuthFlowReset();
}
