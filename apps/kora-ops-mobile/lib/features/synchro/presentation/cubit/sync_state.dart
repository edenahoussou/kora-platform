part of 'sync_cubit.dart';

class SyncState extends Equatable {
  const SyncState(
      {this.isOnline = true,
      this.isSyncing = false,
      this.pending = const <PendingOperation>[]});

  final bool isOnline;
  final bool isSyncing;
  final List<PendingOperation> pending;

  SyncState copyWith(
      {bool? isOnline, bool? isSyncing, List<PendingOperation>? pending}) {
    return SyncState(
      isOnline: isOnline ?? this.isOnline,
      isSyncing: isSyncing ?? this.isSyncing,
      pending: pending ?? this.pending,
    );
  }

  @override
  List<Object?> get props => <Object?>[isOnline, isSyncing, pending];
}
