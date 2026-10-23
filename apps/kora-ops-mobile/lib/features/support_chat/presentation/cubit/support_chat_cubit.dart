import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../data/support_chat_remote_datasource.dart';
import '../../domain/support_chat_config.dart';

enum SupportChatStatus {
  /// Récupération de la config en cours.
  loading,

  /// Chat activé et appareil en ligne : l'entrée est utilisable.
  available,

  /// Hors ligne : l'entrée est grisée (état dégradé, jamais bloquant).
  offline,

  /// Désactivé côté serveur, non configuré, ou config injoignable.
  unavailable,
}

class SupportChatState extends Equatable {
  const SupportChatState({
    this.status = SupportChatStatus.loading,
    this.config,
  });

  final SupportChatStatus status;
  final SupportChatConfig? config;

  @override
  List<Object?> get props => <Object?>[status, config];
}

/// Disponibilité du chat support tawk.to. Aucune exception ne sort d'ici :
/// tout échec (réseau, serveur, 401…) se traduit en [SupportChatStatus.offline]
/// ou [SupportChatStatus.unavailable] — l'entrée « Chat support » est alors
/// simplement grisée. Se recharge à chaque retour de connexion.
///
/// Singleton d'application (bouton flottant visible sur tous les écrans
/// connectés) : si un [AuthCubit] est fourni, la config n'est chargée que
/// pour un agent authentifié — l'appel exige un token, et un 401 sur les
/// écrans de connexion n'a aucun intérêt — et elle est réinitialisée à la
/// déconnexion (terminal partagé : pas de config de l'agent précédent).
class SupportChatCubit extends Cubit<SupportChatState> {
  SupportChatCubit(
    this._remote, {
    Connectivity? connectivity,
    AuthCubit? authCubit,
  })  : _connectivity = connectivity ?? Connectivity(),
        _authCubit = authCubit,
        super(const SupportChatState()) {
    _subscription = _connectivity.onConnectivityChanged.listen((_) => load());
    _authSubscription = _authCubit?.stream.listen((AuthState authState) {
      if (authState is AuthAuthenticated) {
        load();
      } else if (authState is AuthUnauthenticated) {
        emit(const SupportChatState());
      }
    });
    if (_authCubit?.state is AuthAuthenticated) load();
  }

  final SupportChatRemoteDataSource _remote;
  final Connectivity _connectivity;
  final AuthCubit? _authCubit;
  StreamSubscription<List<ConnectivityResult>>? _subscription;
  StreamSubscription<AuthState>? _authSubscription;

  Future<void> load() async {
    if (_authCubit != null && _authCubit.state is! AuthAuthenticated) return;
    try {
      final List<ConnectivityResult> link =
          await _connectivity.checkConnectivity();
      if (link.isEmpty || link.every((r) => r == ConnectivityResult.none)) {
        if (!isClosed) {
          emit(SupportChatState(
              status: SupportChatStatus.offline, config: state.config));
        }
        return;
      }

      final SupportChatConfig? config = await _remote.fetchConfig();
      if (isClosed) return;
      emit(config == null
          ? const SupportChatState(status: SupportChatStatus.unavailable)
          : SupportChatState(
              status: SupportChatStatus.available, config: config));
    } catch (_) {
      if (isClosed) return;
      emit(const SupportChatState(status: SupportChatStatus.unavailable));
    }
  }

  @override
  Future<void> close() async {
    await _subscription?.cancel();
    await _authSubscription?.cancel();
    return super.close();
  }
}
