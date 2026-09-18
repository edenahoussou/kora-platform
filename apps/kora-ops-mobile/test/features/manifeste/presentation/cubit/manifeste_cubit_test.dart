import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/boarding/domain/repositories/boarding_repository.dart';
import 'package:kora_ops_mobile/features/manifeste/domain/repositories/manifeste_repository.dart';
import 'package:kora_ops_mobile/features/manifeste/presentation/cubit/manifeste_cubit.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';
import 'package:kora_ops_mobile/features/ops/presentation/cubit/selected_depart_cubit.dart';
import 'package:kora_ops_mobile/features/synchro/domain/repositories/sync_repository.dart';
import 'package:kora_ops_mobile/features/synchro/presentation/cubit/sync_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockManifesteRepository extends Mock implements ManifesteRepository {}

class MockSyncRepository extends Mock implements SyncRepository {}

class MockBoardingRepository extends Mock implements BoardingRepository {}

void main() {
  late SelectedDepartCubit selectedDepartCubit;
  late SyncCubit syncCubit;
  late MockManifesteRepository repository;

  const Depart depart = Depart(
    id: 'dep-uuid-1',
    route: 'Cotonou → Porto-Novo',
    date: '16/08/2026',
    heure: '07:30',
    vehiculeImmat: 'AB-1234-RB',
    totalPlaces: 45,
    placesOccupees: 38,
  );

  const Passenger passenger = Passenger(
    id: 'ticket-1',
    nomComplet: 'Aïcha Hounkpé',
    siege: '12A',
    statut: BoardingStatus.enAttente,
    segmentOrigine: 'Cotonou',
    segmentDestination: 'Porto-Novo',
    ticketId: 'AB12CD',
  );

  setUp(() {
    selectedDepartCubit = SelectedDepartCubit();
    // Mocks jamais stubbés : ManifesteCubit n'exerce que `enqueue()` (via
    // `updateStatut`), qui ne les appelle pas — voir SyncCubit.enqueue.
    syncCubit = SyncCubit(MockSyncRepository(), MockBoardingRepository());
    repository = MockManifesteRepository();
  });

  test('état initial : noDepart tant qu\'aucun départ n\'est sélectionné', () {
    final cubit = ManifesteCubit(
      selectedDepartCubit: selectedDepartCubit,
      syncCubit: syncCubit,
      repository: repository,
    );
    expect(cubit.state.status, ManifesteStatus.noDepart);
  });

  blocTest<ManifesteCubit, ManifesteState>(
    'charge les passagers depuis le repository quand un départ est déjà '
    'sélectionné à la création',
    setUp: () {
      when(() => repository.load(depart.id)).thenAnswer(
        (_) async => const Result<List<Passenger>>.success(<Passenger>[
          passenger,
        ]),
      );
    },
    build: () {
      selectedDepartCubit.select(depart);
      return ManifesteCubit(
        selectedDepartCubit: selectedDepartCubit,
        syncCubit: syncCubit,
        repository: repository,
      );
    },
    // Le départ est déjà sélectionné avant la construction : `loading` part
    // depuis le constructeur, donc avant que bloc_test ne s'abonne au
    // stream — seul l'état final (`success`) est capté ici (même piège que
    // le test d'origine, qui n'attendait déjà qu'un seul état).
    expect: () => <dynamic>[
      predicate<ManifesteState>(
        (s) => s.status == ManifesteStatus.success && s.passengers.length == 1,
      ),
    ],
  );

  blocTest<ManifesteCubit, ManifesteState>(
    'un échec réseau sans repli possible passe en erreur',
    setUp: () {
      when(() => repository.load(depart.id)).thenAnswer(
        (_) async => const Result<List<Passenger>>.failure(
          NetworkFailure('Pas de connexion et aucun manifeste en cache.'),
        ),
      );
    },
    build: () {
      selectedDepartCubit.select(depart);
      return ManifesteCubit(
        selectedDepartCubit: selectedDepartCubit,
        syncCubit: syncCubit,
        repository: repository,
      );
    },
    expect: () => <dynamic>[
      predicate<ManifesteState>((s) => s.status == ManifesteStatus.error),
    ],
  );

  test('markBoarded met à jour le statut sans enfiler de synchro mock',
      () async {
    when(() => repository.load(depart.id)).thenAnswer(
      (_) async =>
          const Result<List<Passenger>>.success(<Passenger>[passenger]),
    );
    selectedDepartCubit.select(depart);
    final cubit = ManifesteCubit(
      selectedDepartCubit: selectedDepartCubit,
      syncCubit: syncCubit,
      repository: repository,
    );
    await cubit.stream
        .firstWhere((ManifesteState s) => s.status == ManifesteStatus.success);

    cubit.markBoarded('ticket-1');

    expect(cubit.byId('ticket-1')?.statut, BoardingStatus.embarque);
    expect(syncCubit.state.pending, isEmpty);
  });

  test('searchByShortCode filtre localement, sans appel réseau', () async {
    when(() => repository.load(depart.id)).thenAnswer(
      (_) async =>
          const Result<List<Passenger>>.success(<Passenger>[passenger]),
    );
    selectedDepartCubit.select(depart);
    final cubit = ManifesteCubit(
      selectedDepartCubit: selectedDepartCubit,
      syncCubit: syncCubit,
      repository: repository,
    );
    await cubit.stream
        .firstWhere((ManifesteState s) => s.status == ManifesteStatus.success);

    expect(cubit.searchByShortCode('ab12').single.id, 'ticket-1');
    expect(cubit.searchByShortCode('ZZZZ'), isEmpty);
    verify(() => repository.load(depart.id)).called(1);
  });

  test(
    'revient à noDepart quand SelectedDepartCubit.clear() est appelé',
    () async {
      when(() => repository.load(depart.id)).thenAnswer(
        (_) async =>
            const Result<List<Passenger>>.success(<Passenger>[passenger]),
      );
      selectedDepartCubit.select(depart);
      final cubit = ManifesteCubit(
        selectedDepartCubit: selectedDepartCubit,
        syncCubit: syncCubit,
        repository: repository,
      );
      await cubit.stream.firstWhere(
          (ManifesteState s) => s.status == ManifesteStatus.success);

      selectedDepartCubit.clear();
      await Future<void>.delayed(const Duration(milliseconds: 10));

      expect(cubit.state.status, ManifesteStatus.noDepart);
    },
  );
}
