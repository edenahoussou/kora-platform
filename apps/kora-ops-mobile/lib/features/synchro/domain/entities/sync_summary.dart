import 'package:equatable/equatable.dart';

/// Résumé d'un lot de synchronisation (MEZ-88) — miroir de
/// `SyncBatchResource.summary` côté backend. Affiché sur
/// `SyncStatusBanner`/l'onglet Synchro après chaque `SyncCubit.syncNow()`.
class SyncSummary extends Equatable {
  const SyncSummary({
    this.accepted = 0,
    this.pending = 0,
    this.conflict = 0,
    this.rejected = 0,
  });

  final int accepted;
  final int pending;
  final int conflict;
  final int rejected;

  int get total => accepted + pending + conflict + rejected;

  @override
  List<Object?> get props => <Object?>[accepted, pending, conflict, rejected];
}
