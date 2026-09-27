part of 'auth_cubit.dart';

sealed class AuthState extends Equatable {
  const AuthState();

  @override
  List<Object?> get props => <Object?>[];
}

class AuthInitial extends AuthState {
  const AuthInitial();
}

class AuthLoading extends AuthState {
  const AuthLoading();
}

class AuthAuthenticated extends AuthState {
  const AuthAuthenticated(this.user);

  final AuthUser user;

  @override
  List<Object?> get props => <Object?>[user];
}

/// [savedIdentifier] non nul si un email est déjà enregistré sur l'appareil —
/// [AuthGuard] s'en sert pour rediriger vers l'écran PIN plutôt que l'écran
/// email.
class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated({this.savedIdentifier});

  final String? savedIdentifier;

  @override
  List<Object?> get props => <Object?>[savedIdentifier];
}

/// Absence de réseau détectée au démarrage/retour au premier plan, sans
/// session locale valide pour trancher : l'écran ne doit ni afficher
/// "déconnecté" à tort, ni bloquer indéfiniment sur un chargement — voir
/// périmètre du ticket "absence de réseau".
class AuthCheckFailed extends AuthState {
  const AuthCheckFailed(this.message, {this.savedIdentifier});

  final String message;
  final String? savedIdentifier;

  @override
  List<Object?> get props => <Object?>[message, savedIdentifier];
}
