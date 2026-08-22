import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/auth_session.dart';
import '../../domain/entities/auth_user.dart';
import '../../domain/repositories/auth_repository.dart';

part 'auth_event.dart';
part 'auth_state.dart';

/// Couche PRESENTATION : qui est connecté, et depuis quand.
///
/// Il vit aussi longtemps que l'app — c'est lui que le routeur interroge
/// pour savoir s'il faut afficher la bienvenue ou l'accueil. La
/// chorégraphie de l'inscription (e-mail → code → PIN) ne le concerne
/// pas : elle est dans [AuthFlowBloc], et ne lui parle qu'une fois la
/// session ouverte, par [AuthSessionOpened].
class AuthBloc extends Bloc<AuthEvent, AuthState> {
  AuthBloc(this._repository) : super(const AuthState()) {
    on<AuthStarted>(_onStarted);
    on<AuthSessionOpened>(_onSessionOpened);
    on<AuthUserRefreshed>(_onUserRefreshed);
    on<AuthProfileUpdated>(_onProfileUpdated);
    on<AuthLogoutRequested>(_onLogoutRequested);
  }

  final AuthRepository _repository;

  Future<void> _onStarted(AuthStarted event, Emitter<AuthState> emit) async {
    final result = await _repository.currentSession();

    emit(
      result.fold(
        // Même en cas d'échec de lecture, on tranche : « on ne sait pas »
        // n'est pas un état dans lequel l'app peut rester, le routeur
        // attend une réponse.
        (failure) => const AuthState(status: AuthStatus.unauthenticated),
        (session) => session == null
            ? const AuthState(status: AuthStatus.unauthenticated)
            : AuthState(status: AuthStatus.authenticated, session: session),
      ),
    );
  }

  void _onSessionOpened(AuthSessionOpened event, Emitter<AuthState> emit) {
    emit(AuthState(status: AuthStatus.authenticated, session: event.session));
  }

  Future<void> _onUserRefreshed(
    AuthUserRefreshed event,
    Emitter<AuthState> emit,
  ) async {
    final session = state.session;
    if (session == null) return;

    final result = await _repository.refreshUser();

    emit(
      result.fold(
        // Un profil non rafraîchi n'est pas une déconnexion : on garde la
        // session et on signale l'échec.
        (failure) => state.copyWith(errorMessage: failure.message),
        (user) => state.copyWith(
          session: AuthSession(
            token: session.token,
            tokenType: session.tokenType,
            expiresAt: session.expiresAt,
            user: user,
            device: session.device,
          ),
          errorMessage: '',
        ),
      ),
    );
  }

  void _onProfileUpdated(AuthProfileUpdated event, Emitter<AuthState> emit) {
    final session = state.session;
    if (session == null) return;

    emit(
      state.copyWith(
        session: AuthSession(
          token: session.token,
          tokenType: session.tokenType,
          expiresAt: session.expiresAt,
          user: event.user,
          device: session.device,
        ),
        errorMessage: '',
      ),
    );
  }

  Future<void> _onLogoutRequested(
    AuthLogoutRequested event,
    Emitter<AuthState> emit,
  ) async {
    // Une seconde demande pendant l'appel révoquerait un token déjà
    // révoqué, et ferait clignoter l'écran de profil pour rien.
    if (state.isLoggingOut) return;

    emit(state.copyWith(isLoggingOut: true, errorMessage: ''));

    final result = await _repository.logout();

    // Déconnecté dans les deux cas : le repository purge le stockage même
    // quand le serveur ne répond pas. Rester « connecté » ici afficherait
    // un compte auquel l'app n'a plus accès.
    emit(
      AuthState(
        status: AuthStatus.unauthenticated,
        errorMessage: result.fold((failure) => failure.message, (_) => ''),
      ),
    );
  }
}
