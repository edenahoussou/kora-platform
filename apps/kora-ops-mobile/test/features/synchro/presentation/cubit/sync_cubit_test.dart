import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/synchro/domain/entities/pending_operation.dart';
import 'package:kora_ops_mobile/features/synchro/presentation/cubit/sync_cubit.dart';

void main() {
  group('SyncCubit', () {
    blocTest<SyncCubit, SyncState>(
      'goOffline() passe isOnline à false',
      build: SyncCubit.new,
      act: (SyncCubit cubit) => cubit.goOffline(),
      expect: () => <dynamic>[predicate<SyncState>((s) => s.isOnline == false)],
    );

    blocTest<SyncCubit, SyncState>(
      'enqueue() ajoute une opération en tête de file',
      build: SyncCubit.new,
      act: (SyncCubit cubit) => cubit.enqueue(
        const PendingOperation(
            id: '1',
            label: 'Embarquement — Siège 12A',
            createdAtLabel: 'À l\'instant'),
      ),
      expect: () =>
          <dynamic>[predicate<SyncState>((s) => s.pending.length == 1)],
    );

    blocTest<SyncCubit, SyncState>(
      'syncNow() vide la file quand en ligne',
      build: () {
        final cubit = SyncCubit();
        cubit.enqueue(
          const PendingOperation(
              id: '1',
              label: 'Refus — Siège 4B',
              createdAtLabel: 'À l\'instant'),
        );
        return cubit;
      },
      act: (SyncCubit cubit) => cubit.syncNow(),
      expect: () => <dynamic>[
        predicate<SyncState>((s) => s.isSyncing == true),
        predicate<SyncState>((s) => s.isSyncing == false && s.pending.isEmpty),
      ],
    );

    test('syncNow() ne fait rien si hors ligne', () async {
      final cubit = SyncCubit();
      cubit.enqueue(const PendingOperation(
          id: '1', label: 'Test', createdAtLabel: 'À l\'instant'));
      cubit.goOffline();

      await cubit.syncNow();

      expect(cubit.state.pending.length, 1);
    });
  });
}
