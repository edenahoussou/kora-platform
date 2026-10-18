import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

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
class SupportChatCubit extends Cubit<SupportChatState> {
  SupportChatCubit(this._remote, {Connectivity? connectivity})
      : _connectivity = connectivity ?? Connectivity(),
        super(const SupportChatState()) {
    _subscription = _connectivity.onConnectivityChanged.listen((_) => load());
  }

  final SupportChatRemoteDataSource _remote;
  final Connectivity _connectivity;
  StreamSubscription<List<ConnectivityResult>>? _subscription;

  Future<void> load() async {
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
    return super.close();
  }
}
