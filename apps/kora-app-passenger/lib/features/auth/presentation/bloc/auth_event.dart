part of 'auth_bloc.dart';

sealed class AuthEvent extends Equatable {
  const AuthEvent();

  @override
  List<Object?> get props => [];
}

/// Émis une fois au démarrage : relit la session stockée.
class AuthStarted extends AuthEvent {
  const AuthStarted();
}

/// Émis par le parcours d'inscription ou de connexion quand une session
/// vient d'être ouverte.
class AuthSessionOpened extends AuthEvent {
  const AuthSessionOpened(this.session);

  final AuthSession session;

  @override
  List<Object?> get props => [session];
}

/// Rafraîchit le profil depuis `auth/me`.
class AuthUserRefreshed extends AuthEvent {
  const AuthUserRefreshed();
}

/// Déconnexion demandée par l'utilisateur.
class AuthLogoutRequested extends AuthEvent {
  const AuthLogoutRequested();
}
