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

/// Profil complété ailleurs (écran des autorisations) : on remplace
/// l'utilisateur de la session sans repasser par `auth/me`, la réponse du
/// `PUT` faisant déjà autorité.
class AuthProfileUpdated extends AuthEvent {
  const AuthProfileUpdated(this.user);

  final AuthUser user;

  @override
  List<Object?> get props => [user];
}

/// Déconnexion demandée par l'utilisateur.
/// Le serveur a refusé le token : session périmée, ou révoquée depuis un
/// autre appareil.
///
/// Distinct de [AuthLogoutRequested] : il n'y a rien à révoquer côté
/// serveur — c'est lui qui vient de nous refuser — et l'utilisateur n'a
/// rien demandé. On purge et on le ramène à la connexion, plutôt que de
/// le laisser devant un accueil qui ne chargera jamais.
class AuthSessionExpired extends AuthEvent {
  const AuthSessionExpired();
}

class AuthLogoutRequested extends AuthEvent {
  const AuthLogoutRequested();
}
