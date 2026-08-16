import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/manifeste/presentation/cubit/manifeste_cubit.dart';
import 'package:kora_ops_mobile/features/ops/data/mock_ops_data_source.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';
import 'package:kora_ops_mobile/features/ops/presentation/cubit/selected_depart_cubit.dart';
import 'package:kora_ops_mobile/features/synchro/presentation/cubit/sync_cubit.dart';

void main() {
  late SelectedDepartCubit selectedDepartCubit;
  late SyncCubit syncCubit;

  setUp(() {
    selectedDepartCubit = SelectedDepartCubit();
    syncCubit = SyncCubit();
  });

  test('état initial : noDepart tant qu\'aucun départ n\'est sélectionné', () {
    final cubit = ManifesteCubit(
      selectedDepartCubit: selectedDepartCubit,
      syncCubit: syncCubit,
    );
    expect(cubit.state.status, ManifesteStatus.noDepart);
  });

  blocTest<ManifesteCubit, ManifesteState>(
    'charge les passagers quand un départ est déjà sélectionné à la création',
    build: () {
      selectedDepartCubit.select(MockOpsDataSource.departs.first);
      return ManifesteCubit(
        selectedDepartCubit: selectedDepartCubit,
        syncCubit: syncCubit,
      );
    },
    wait: const Duration(milliseconds: 800),
    expect: () => <dynamic>[
      predicate<ManifesteState>(
        (s) => s.status == ManifesteStatus.success && s.passengers.isNotEmpty,
      ),
    ],
  );

  blocTest<ManifesteCubit, ManifesteState>(
    'se recharge automatiquement quand SelectedDepartCubit change de départ',
    build: () => ManifesteCubit(
      selectedDepartCubit: selectedDepartCubit,
      syncCubit: syncCubit,
    ),
    act: (ManifesteCubit cubit) async {
      selectedDepartCubit.select(MockOpsDataSource.departs.first);
      await Future<void>.delayed(const Duration(milliseconds: 800));
    },
    expect: () => <dynamic>[
      predicate<ManifesteState>((s) => s.status == ManifesteStatus.loading),
      predicate<ManifesteState>((s) => s.status == ManifesteStatus.success),
    ],
  );

  test(
    'updateStatut met à jour le passager et enfile une opération de synchro',
    () async {
      selectedDepartCubit.select(MockOpsDataSource.departs.first);
      final cubit = ManifesteCubit(
        selectedDepartCubit: selectedDepartCubit,
        syncCubit: syncCubit,
      );
      await Future<void>.delayed(const Duration(milliseconds: 800));

      final Passenger first = cubit.state.passengers.first;
      cubit.updateStatut(first.id, BoardingStatus.embarque);

      expect(cubit.byId(first.id)?.statut, BoardingStatus.embarque);
      expect(syncCubit.state.pending, isNotEmpty);
    },
  );

  test(
    'revient à noDepart quand SelectedDepartCubit.clear() est appelé',
    () async {
      selectedDepartCubit.select(MockOpsDataSource.departs.first);
      final cubit = ManifesteCubit(
        selectedDepartCubit: selectedDepartCubit,
        syncCubit: syncCubit,
      );
      await Future<void>.delayed(const Duration(milliseconds: 800));

      selectedDepartCubit.clear();
      await Future<void>.delayed(const Duration(milliseconds: 50));

      expect(cubit.state.status, ManifesteStatus.noDepart);
    },
  );
}
