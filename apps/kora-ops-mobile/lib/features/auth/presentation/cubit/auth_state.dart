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

  final UserEntity user;

  @override
  List<Object?> get props => <Object?>[user];
}

/// [savedEmail] non nul si un email est déjà enregistré sur l'appareil —
/// [AuthGuard] s'en sert pour rediriger vers l'écran PIN plutôt que l'écran
/// email (voir document.md § Flux d'authentification).
class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated({this.savedEmail});

  final String? savedEmail;

  @override
  List<Object?> get props => <Object?>[savedEmail];
}
