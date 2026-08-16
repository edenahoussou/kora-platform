import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../domain/entities/pending_operation.dart';

part 'sync_state.dart';

/// État de connexion + file d'attente de synchronisation — **mocké** ce
/// sprint (voir document.md § Synchro) : bascule manuelle en/hors ligne
/// depuis `SynchroPage`, aucune vraie détection réseau ni API de sync.
/// Singleton (voir injection.dart) : c'est la même instance qui alimente
/// `SyncStatusBanner`, visible sur tous les écrans, et `SynchroPage`.
class SyncCubit extends Cubit<SyncState> {
  SyncCubit() : super(const SyncState());

  void goOffline() => emit(state.copyWith(isOnline: false));

  void goOnline() => emit(state.copyWith(isOnline: true));

  void enqueue(PendingOperation operation) {
    emit(state
        .copyWith(pending: <PendingOperation>[operation, ...state.pending]));
  }

  /// Simule l'envoi de la file au serveur — vide la file après un court
  /// délai si l'app est en ligne.
  Future<void> syncNow() async {
    if (!state.isOnline || state.pending.isEmpty) return;
    emit(state.copyWith(isSyncing: true));
    await Future<void>.delayed(const Duration(seconds: 1));
    emit(state.copyWith(isSyncing: false, pending: const <PendingOperation>[]));
  }
}
