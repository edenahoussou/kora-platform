import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/auth_user.dart';
import '../../domain/repositories/auth_repository.dart';

part 'auth_state.dart';

/// État de session global — c'est la seule source de vérité utilisée par
/// [AuthGuard] pour rediriger entre les écrans d'authentification et
/// `/home`. La logique détaillée du parcours de connexion (email → OTP →
/// PIN → nouvel appareil) vit dans `AuthFlowBloc`, propre à la feature de
/// connexion ; celui-ci appelle [authenticate] une fois la connexion
/// aboutie.
///
/// Appelle directement [AuthRepository] plutôt que de passer par des
/// UseCases dédiés — même convention que `AuthFlowBloc` et que
/// kora-app-passenger : un seul repository, pas de couche intermédiaire
/// pour des opérations qui ne font qu'un appel chacune.
class AuthCubit extends Cubit<AuthState> {
  AuthCubit(this._authRepository) : super(const AuthInitial());

  final AuthRepository _authRepository;

  /// Appelée au démarrage de l'app ET au retour au premier plan (voir
  /// `_AppLifecycleListener` dans `app.dart`) : relit la session stockée
  /// localement, sans appel réseau — l'app doit rester utilisable même
  /// hors connexion tant que le token n'est pas expiré.
  Future<void> checkAuthStatus() async {
    emit(const AuthLoading());
    final result = await _authRepository.currentSession();

    await result.when(
      onSuccess: (session) async {
        if (session != null) {
          emit(AuthAuthenticated(session.user));
          // Rôles, permissions et obligation de terminal peuvent avoir
          // changé côté serveur (ex. nouvelle permission `trip-logs.create`,
          // OPS_FORCE_TERMINAL_FOR_ALL basculé) : rafraîchis en tâche de
          // fond, jamais bloquant (hors ligne → on garde le cache).
          unawaited(_refreshUserInBackground());
          return;
        }
        emit(AuthUnauthenticated(
            savedIdentifier: await _authRepository.lastIdentifier()));
      },
      onFailure: (Failure failure) async {
        // Erreur du stockage local lui-même (rare) : on ne peut pas
        // affirmer que l'utilisateur est déconnecté, mais on ne peut pas
        // non plus le laisser sur un écran de chargement indéfini.
        emit(
          AuthCheckFailed(
            failure.message,
            savedIdentifier: await _authRepository.lastIdentifier(),
          ),
        );
      },
    );
  }

  Future<void> _refreshUserInBackground() async {
    try {
      final Result<AuthUser> result = await _authRepository.refreshUser();
      result.when(
        onSuccess: (AuthUser user) {
          final AuthState current = state;
          if (!isClosed &&
              current is AuthAuthenticated &&
              current.user != user) {
            emit(AuthAuthenticated(user));
          }
        },
        onFailure: (_) {},
      );
    } on Object {
      // Best-effort : le profil en cache reste valable.
    }
  }

  /// Appelé par `AuthFlowBloc` une fois la connexion aboutie (PIN seul ou
  /// PIN + OTP).
  void authenticate(AuthUser user) => emit(AuthAuthenticated(user));

  /// Un appel API protégé a reçu un 401 : le token n'est plus valide côté
  /// serveur (expiré ou révoqué). Purge la session locale et fait
  /// retomber l'app sur l'écran PIN — pas email, l'appareil reste de
  /// confiance.
  Future<void> sessionExpired() async {
    // Purge locale uniquement : le serveur a déjà rejeté ce token (c'est
    // pour ça qu'on est ici), donc appeler logout() referait un appel
    // réseau qui répond 401 et redéclenche SessionExpiryInterceptor →
    // boucle infinie. Le token restera valide côté serveur jusqu'à son
    // expiration naturelle, comme pour logout() (compromis assumé, voir
    // AuthRepository.logout).
    await _authRepository.purgeLocalSession();
    emit(AuthUnauthenticated(
        savedIdentifier: await _authRepository.lastIdentifier()));
  }

  Future<void> logout() async {
    emit(const AuthLoading());
    final result = await _authRepository.logout();
    await result.when(
      onSuccess: (_) async {
        emit(AuthUnauthenticated(
            savedIdentifier: await _authRepository.lastIdentifier()));
      },
      onFailure: (_) async {
        // La purge locale a lieu quoi qu'il arrive dans le repository
        // (voir AuthRepositoryImpl.logout) : l'utilisateur est donc bien
        // déconnecté localement même si l'appel serveur a échoué.
        emit(AuthUnauthenticated(
            savedIdentifier: await _authRepository.lastIdentifier()));
      },
    );
  }
}
