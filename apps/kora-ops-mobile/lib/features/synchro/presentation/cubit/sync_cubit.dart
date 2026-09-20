import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../boarding/domain/repositories/boarding_repository.dart';
import '../../../incidents/domain/repositories/incident_repository.dart';
import '../../../seat_release/domain/repositories/seat_release_repository.dart';
import '../../domain/entities/pending_operation.dart';
import '../../domain/entities/sync_summary.dart';
import '../../domain/repositories/sync_repository.dart';

part 'sync_state.dart';

/// État de connexion + synchronisation des opérations terrain
/// enregistrées hors ligne — embarquements (MEZ-88), descentes
/// anticipées et no-show (MEZ-87), incidents (MEZ-98). Détecte
/// la connectivité en continu (`connectivity_plus`) et relance
/// automatiquement `syncNow()` dès le retour du réseau ; la bascule
/// manuelle depuis `SynchroPage` reste disponible (utile en test/démo,
/// ou pour forcer une resynchronisation immédiate).
///
/// Singleton (voir injection.dart) : c'est la même instance qui alimente
/// `SyncStatusBanner`, visible sur tous les écrans, et `SynchroPage`.
class SyncCubit extends Cubit<SyncState> {
  SyncCubit(
    this._syncRepository,
    this._boardingRepository,
    this._seatReleaseRepository,
    this._incidentRepository, {
    Connectivity? connectivity,
  })  : _connectivity = connectivity ?? Connectivity(),
        super(const SyncState());

  final SyncRepository _syncRepository;
  final BoardingRepository _boardingRepository;
  final SeatReleaseRepository _seatReleaseRepository;
  final IncidentRepository _incidentRepository;
  final Connectivity _connectivity;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;

  /// À appeler une fois au démarrage (voir injection.dart) : relit le
  /// nombre d'opérations en attente (persisté en sqlite, donc valable
  /// après un redémarrage à froid) et démarre l'écoute continue de la
  /// connectivité. Fire-and-forget côté appelant — ne bloque jamais le
  /// démarrage de l'app.
  Future<void> bootstrap() async {
    await refreshPendingCount();

    final List<ConnectivityResult> initial =
        await _connectivity.checkConnectivity();
    _applyConnectivity(initial, triggerSync: false);

    _connectivitySubscription =
        _connectivity.onConnectivityChanged.listen(_applyConnectivity);
  }

  void _applyConnectivity(List<ConnectivityResult> results,
      {bool triggerSync = true}) {
    final bool isOnline = !results.contains(ConnectivityResult.none);
    final bool wasOffline = !state.isOnline;
    emit(state.copyWith(
      isOnline: isOnline,
      lastSyncSummary: state.lastSyncSummary,
      lastSyncError: state.lastSyncError,
    ));

    // Retour du réseau après une coupure : on reprend automatiquement la
    // file en attente, sans action de l'agent — voir critère
    // d'acceptation MEZ-91 "puis afficher le résultat de la
    // synchronisation".
    if (triggerSync && isOnline && wasOffline) {
      unawaited(syncNow());
    }
  }

  /// Bascule manuelle (voir `SynchroPage`, utile en test/démo).
  void goOffline() => emit(state.copyWith(
        isOnline: false,
        lastSyncSummary: state.lastSyncSummary,
        lastSyncError: state.lastSyncError,
      ));

  void goOnline() {
    emit(state.copyWith(
      isOnline: true,
      lastSyncSummary: state.lastSyncSummary,
      lastSyncError: state.lastSyncError,
    ));
    unawaited(syncNow());
  }

  /// File mock historique (descente anticipée — voir docstring
  /// `SyncState.pending`).
  void enqueue(PendingOperation operation) {
    emit(state.copyWith(
      pending: <PendingOperation>[operation, ...state.pending],
      lastSyncSummary: state.lastSyncSummary,
      lastSyncError: state.lastSyncError,
    ));
  }

  Future<void> refreshPendingCount() async {
    final int count = await _totalPendingCount();
    emit(state.copyWith(
      pendingOperationsCount: count,
      lastSyncSummary: state.lastSyncSummary,
      lastSyncError: state.lastSyncError,
    ));
  }

  /// Envoie la file d'opérations en attente, tous types confondus
  /// (MEZ-87/MEZ-88), puis réémet les incidents en attente (MEZ-98, pas
  /// de lot dédié côté serveur pour ceux-ci — voir
  /// `IncidentRepository.retryPending`). Ne fait rien si hors ligne ou si
  /// une synchro est déjà en cours — jamais deux lots simultanés depuis
  /// ce terminal.
  Future<void> syncNow() async {
    if (!state.isOnline || state.isSyncing) return;

    emit(state.copyWith(
      isSyncing: true,
      lastSyncSummary: state.lastSyncSummary,
      lastSyncError: state.lastSyncError,
    ));

    final result = await _syncRepository.syncPending();
    await _incidentRepository.retryPending();
    final int remaining = await _totalPendingCount();

    result.when(
      onSuccess: (summary) => emit(state.copyWith(
        isSyncing: false,
        pendingOperationsCount: remaining,
        lastSyncSummary: summary,
        lastSyncError: null,
      )),
      onFailure: (failure) => emit(state.copyWith(
        isSyncing: false,
        pendingOperationsCount: remaining,
        lastSyncSummary: state.lastSyncSummary,
        lastSyncError: failure.message,
      )),
    );
  }

  Future<int> _totalPendingCount() async {
    final int boarding = await _boardingRepository.totalPendingCount();
    final int seatReleases = await _seatReleaseRepository.totalPendingCount();
    final int incidents = await _incidentRepository.totalPendingCount();
    return boarding + seatReleases + incidents;
  }

  @override
  Future<void> close() {
    unawaited(_connectivitySubscription?.cancel());
    return super.close();
  }
}
