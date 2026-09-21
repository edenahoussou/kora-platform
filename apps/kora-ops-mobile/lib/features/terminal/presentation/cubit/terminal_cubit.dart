import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/errors/failures.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../domain/entities/terminal.dart';
import '../../domain/repositories/terminal_repository.dart';

part 'terminal_state.dart';

/// Lie le terminal P501 à cet appareil et expose son état courant — c'est
/// la seule source de vérité utilisée par `AuthGuard` pour décider si
/// l'écran de liaison terminal doit s'afficher (voir MEZ-91 / document.md
/// § Terminal P501, même rôle qu'`AuthCubit` pour la session).
class TerminalCubit extends Cubit<TerminalState> {
  TerminalCubit(this._repository, AuthCubit authCubit)
      : super(const TerminalInitial()) {
    // La session OPS (`TerminalSession` côté backend) est liée au jeton
    // Sanctum courant : un nouveau login change de jeton, donc l'ancienne
    // session OPS ne couvre plus les appels `ops/*` même si le terminal
    // reste correctement lié sur cet appareil (GET /ops/departures répond
    // alors 401 "Aucune session OPS n'est ouverte pour ce terminal").
    // On rejoue donc le handshake dès qu'une connexion aboutit — même
    // stratégie que `NotificationCubit`, qui écoute déjà `AuthCubit` pour
    // se réinitialiser à la connexion.
    _authSubscription = authCubit.stream.listen((AuthState state) {
      if (state is AuthAuthenticated) {
        unawaited(_relinkAfterAuthentication());
      }
    });
  }

  final TerminalRepository _repository;
  late final StreamSubscription<AuthState> _authSubscription;

  /// Appelé au démarrage (voir `SplashPage._bootstrap`, en parallèle de
  /// `AuthCubit.checkAuthStatus`) : relit le terminal en cache pour une
  /// décision **immédiate**, sans attendre le réseau — l'app doit rester
  /// utilisable hors ligne tant que le terminal n'a pas été explicitement
  /// perdu/désactivé côté serveur. Se résout dès la lecture locale
  /// terminée ; si un terminal est en cache, un handshake de
  /// rafraîchissement est lancé en tâche de fond **sans être attendu ici**
  /// (voir `_refreshInBackground`) — `SplashPage` ne doit jamais rester
  /// bloqué sur un réseau lent ou absent.
  Future<void> bootstrap() async {
    final Terminal? cached = await _repository.cachedTerminal();

    if (cached == null) {
      emit(const TerminalUnlinked());
      return;
    }

    emit(TerminalLinked(cached, fromCache: true));
    unawaited(_refreshInBackground(cached.serialNumber));
  }

  Future<void> _refreshInBackground(String serialNumber) async {
    final result = await _repository.handshake(serialNumber);

    result.when(
      onSuccess: (terminal) => emit(TerminalLinked(terminal)),
      onFailure: (failure) {
        switch (failure) {
          case TerminalUnknownFailure():
            emit(TerminalUnknown(failure.message));
          case TerminalNotUsableFailure():
            emit(TerminalNotUsable(failure.code, failure.message));
          default:
            // Réseau/serveur indisponible : on reste sur l'instantané en
            // cache déjà émis, silencieusement — voir docstring ci-dessus.
            break;
        }
      },
    );
  }

  /// Appelé depuis `TerminalSetupPage` : soit la saisie manuelle d'un
  /// numéro de série P501, soit — en mode téléphone — la liaison
  /// automatique avec l'empreinte de l'appareil (voir
  /// `TerminalRepository.phoneSerialNumber`, ticket "Supporter le scan
  /// par caméra téléphone en alternative au P501").
  Future<void> link(String serialNumber) async {
    emit(const TerminalLinking());
    final result = await _repository.handshake(serialNumber);

    result.when(
      onSuccess: (terminal) => emit(TerminalLinked(terminal)),
      onFailure: (failure) {
        switch (failure) {
          case TerminalUnknownFailure():
            emit(TerminalUnknown(failure.message));
          case TerminalNotUsableFailure():
            emit(TerminalNotUsable(failure.code, failure.message));
          default:
            emit(TerminalError(failure.message));
        }
      },
    );
  }

  /// « Changer de terminal » (voir Profil) : oublie le terminal lié et
  /// fait retomber l'app sur l'écran de liaison à la prochaine navigation.
  Future<void> forget() async {
    await _repository.forget();
    emit(const TerminalUnlinked());
  }

  /// Déclenché automatiquement sur `AuthAuthenticated` (voir le `listen`
  /// du constructeur) : rejoue le handshake du terminal en cache avec le
  /// jeton fraîchement émis, pour rouvrir la session OPS derrière.
  /// Ne fait rien s'il n'y a pas de terminal en cache ("tant que c'est
  /// valide") : `AuthGuard` renvoie déjà sur `/terminal/setup` dans ce
  /// cas, il n'y a rien à relier automatiquement.
  Future<void> _relinkAfterAuthentication() async {
    final Terminal? cached = await _repository.cachedTerminal();
    if (cached == null) return;

    await _refreshInBackground(cached.serialNumber);
  }

  @override
  Future<void> close() {
    unawaited(_authSubscription.cancel());
    return super.close();
  }
}
