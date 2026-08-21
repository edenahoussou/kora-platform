part of 'auth_bloc.dart';

/// Où en est la session.
///
/// [unknown] n'est pas un détail : au démarrage, tant que le stockage
/// sécurisé n'a pas répondu, on ne sait pas si quelqu'un est connecté. Le
/// routeur doit attendre cette réponse plutôt que de renvoyer sur la
/// connexion quelqu'un qui l'était déjà.
enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState extends Equatable {
  const AuthState({
    this.status = AuthStatus.unknown,
    this.session,
    this.errorMessage = '',
  });

  final AuthStatus status;
  final AuthSession? session;

  /// Renseigné quand la déconnexion ou le rafraîchissement a échoué. La
  /// session, elle, suit `status`.
  final String errorMessage;

  AuthUser? get user => session?.user;

  bool get isAuthenticated => status == AuthStatus.authenticated;

  AuthState copyWith({
    AuthStatus? status,
    AuthSession? session,
    String? errorMessage,
    bool clearSession = false,
  }) {
    return AuthState(
      status: status ?? this.status,
      session: clearSession ? null : (session ?? this.session),
      errorMessage: errorMessage ?? this.errorMessage,
    );
  }

  @override
  List<Object?> get props => [status, session, errorMessage];
}
