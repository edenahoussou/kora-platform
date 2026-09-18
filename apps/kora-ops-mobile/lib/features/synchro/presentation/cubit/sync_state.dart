part of 'sync_cubit.dart';

class SyncState extends Equatable {
  const SyncState({
    this.isOnline = true,
    this.isSyncing = false,
    this.pending = const <PendingOperation>[],
    this.pendingBoardingCount = 0,
    this.lastSyncSummary,
    this.lastSyncError,
  });

  final bool isOnline;
  final bool isSyncing;

  /// File mock historique (descente anticipée, refus manuel — voir
  /// `ManifesteCubit.updateStatut`), affichée telle quelle dans l'onglet
  /// Synchro en plus des embarquements réels ci-dessous. À reprendre
  /// quand ces flux seront à leur tour branchés sur de vraies opérations.
  final List<PendingOperation> pending;

  /// Nombre réel d'embarquements en attente de synchronisation (sqlite,
  /// `boarding_operations` — voir `BoardingRepository.totalPendingCount`,
  /// MEZ-88), tous départs confondus.
  final int pendingBoardingCount;

  /// Résumé du dernier lot envoyé avec succès — voir
  /// `SyncCubit.syncNow`. `null` tant qu'aucune synchro n'a encore abouti
  /// dans cette session.
  final SyncSummary? lastSyncSummary;

  /// Message du dernier échec de synchro (hors simple absence de réseau,
  /// silencieuse — voir `SyncRepositoryImpl.syncPendingBoardingOperations`).
  final String? lastSyncError;

  int get totalPending => pending.length + pendingBoardingCount;

  SyncState copyWith({
    bool? isOnline,
    bool? isSyncing,
    List<PendingOperation>? pending,
    int? pendingBoardingCount,
    SyncSummary? lastSyncSummary,
    String? lastSyncError,
  }) {
    return SyncState(
      isOnline: isOnline ?? this.isOnline,
      isSyncing: isSyncing ?? this.isSyncing,
      pending: pending ?? this.pending,
      pendingBoardingCount: pendingBoardingCount ?? this.pendingBoardingCount,
      lastSyncSummary: lastSyncSummary,
      lastSyncError: lastSyncError,
    );
  }

  @override
  List<Object?> get props => <Object?>[
        isOnline,
        isSyncing,
        pending,
        pendingBoardingCount,
        lastSyncSummary,
        lastSyncError,
      ];
}
