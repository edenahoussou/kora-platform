part of 'auth_flow_bloc.dart';

sealed class AuthFlowEvent extends Equatable {
  const AuthFlowEvent();

  @override
  List<Object?> get props => [];
}

/// Écran « Votre adresse email » : demande un code pour [purpose].
class AuthFlowEmailSubmitted extends AuthFlowEvent {
  const AuthFlowEmailSubmitted({
    required this.email,
    this.purpose = OtpPurpose.registration,
  });

  final String email;
  final OtpPurpose purpose;

  @override
  List<Object?> get props => [email, purpose];
}

/// Écran de connexion : e-mail + PIN.
class AuthFlowLoginSubmitted extends AuthFlowEvent {
  const AuthFlowLoginSubmitted({required this.email, required this.pin});

  final String email;
  final String pin;

  @override
  List<Object?> get props => [email, pin];
}

/// Écran OTP : les six chiffres saisis.
/// L'utilisateur demande la vérification d'un nouvel appareil, depuis le
/// lien de l'écran de connexion. Le serveur peut aussi l'imposer de
/// lui-même en répondant 202 à `login`.
class AuthFlowNewDeviceRequested extends AuthFlowEvent {
  const AuthFlowNewDeviceRequested(this.email);

  final String email;

  @override
  List<Object?> get props => [email];
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
  const AuthFlowPinSubmitted(this.pin);

  final String pin;

  @override
  List<Object?> get props => [pin];
}

/// Boutons Google / Facebook / TikTok, une fois le jeton du fournisseur
/// obtenu par son SDK.
class AuthFlowProviderSubmitted extends AuthFlowEvent {
  const AuthFlowProviderSubmitted({
    required this.provider,
    required this.providerToken,
  });

  final String provider;
  final String providerToken;

  @override
  List<Object?> get props => [provider, providerToken];
}

/// Remet le parcours à zéro (retour arrière, abandon).
class AuthFlowReset extends AuthFlowEvent {
  const AuthFlowReset();
}
