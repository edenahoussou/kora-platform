part of 'sync_cubit.dart';

class SyncState extends Equatable {
  const SyncState({
    this.isOnline = true,
    this.isSyncing = false,
    this.pending = const <PendingOperation>[],
    this.pendingOperationsCount = 0,
    this.lastSyncSummary,
    this.lastSyncError,
  });

  final bool isOnline;
  final bool isSyncing;

  /// File mock historique (descente anticipée manuelle avant MEZ-87,
  /// refus manuel — voir `ManifesteCubit.updateStatut`), affichée telle
  /// quelle dans l'onglet Synchro en plus des opérations réelles
  /// ci-dessous. À reprendre pour les derniers flux encore mockés.
  final List<PendingOperation> pending;

  /// Nombre réel d'opérations en attente de synchronisation, tous types
  /// confondus (embarquements, descentes anticipées, no-show — voir
  /// `BoardingRepository`/`SeatReleaseRepository.totalPendingCount`,
  /// MEZ-87/MEZ-88), tous départs confondus.
  final int pendingOperationsCount;

  /// Résumé du dernier lot envoyé avec succès — voir
  /// `SyncCubit.syncNow`. `null` tant qu'aucune synchro n'a encore abouti
  /// dans cette session.
  final SyncSummary? lastSyncSummary;

  /// Message du dernier échec de synchro (hors simple absence de réseau,
  /// silencieuse — voir `SyncRepositoryImpl.syncPending`).
  final String? lastSyncError;

  int get totalPending => pending.length + pendingOperationsCount;

  SyncState copyWith({
    bool? isOnline,
    bool? isSyncing,
    List<PendingOperation>? pending,
    int? pendingOperationsCount,
    SyncSummary? lastSyncSummary,
    String? lastSyncError,
  }) {
    return SyncState(
      isOnline: isOnline ?? this.isOnline,
      isSyncing: isSyncing ?? this.isSyncing,
      pending: pending ?? this.pending,
      pendingOperationsCount:
          pendingOperationsCount ?? this.pendingOperationsCount,
      lastSyncSummary: lastSyncSummary,
      lastSyncError: lastSyncError,
    );
  }

  @override
  List<Object?> get props => <Object?>[
        isOnline,
        isSyncing,
        pending,
        pendingOperationsCount,
        lastSyncSummary,
        lastSyncError,
      ];
}
