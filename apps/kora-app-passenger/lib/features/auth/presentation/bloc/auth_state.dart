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
    this.isLoggingOut = false,
  });

  final AuthStatus status;
  final AuthSession? session;

  /// Vrai le temps de l'appel à `auth/logout`. L'écran de profil s'en
  /// sert pour neutraliser le lien : la révocation du token passe par le
  /// réseau, et deux appuis lanceraient deux déconnexions.
  final bool isLoggingOut;

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
    bool? isLoggingOut,
  }) {
    return AuthState(
      status: status ?? this.status,
      session: clearSession ? null : (session ?? this.session),
      errorMessage: errorMessage ?? this.errorMessage,
      isLoggingOut: isLoggingOut ?? this.isLoggingOut,
    );
  }

  @override
  List<Object?> get props => [status, session, errorMessage, isLoggingOut];
}
