import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:kora_ops_mobile/features/boarding/domain/repositories/boarding_repository.dart';
import 'package:kora_ops_mobile/features/incidents/domain/repositories/incident_repository.dart';
import 'package:kora_ops_mobile/features/manifeste/domain/entities/manifest_snapshot.dart';
import 'package:kora_ops_mobile/features/manifeste/domain/repositories/manifeste_repository.dart';
import 'package:kora_ops_mobile/features/manifeste/presentation/cubit/manifeste_cubit.dart';
import 'package:kora_ops_mobile/features/ops/data/datasources/selected_departure_local_datasource.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';
import 'package:kora_ops_mobile/features/ops/presentation/cubit/selected_depart_cubit.dart';
import 'package:kora_ops_mobile/features/seat_release/domain/repositories/seat_release_repository.dart';
import 'package:kora_ops_mobile/features/synchro/domain/repositories/sync_repository.dart';
import 'package:kora_ops_mobile/features/synchro/presentation/cubit/sync_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockManifesteRepository extends Mock implements ManifesteRepository {}

class MockSyncRepository extends Mock implements SyncRepository {}

class MockSeatReleaseRepository extends Mock implements SeatReleaseRepository {}

class MockBoardingRepository extends Mock implements BoardingRepository {}

class MockIncidentRepository extends Mock implements IncidentRepository {}

class MockSelectedDepartureLocalDataSource extends Mock
    implements SelectedDepartureLocalDataSource {}

class MockAuthCubit extends Mock implements AuthCubit {}

void main() {
  setUpAll(() {
    registerFallbackValue(const Depart(
      id: 'fallback',
      route: '—',
      date: '—',
      heure: '—',
      vehiculeImmat: '—',
      totalPlaces: 0,
      placesOccupees: 0,
    ));
    registerFallbackValue(BoardingStatus.enAttente);
  });

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

  const Depart autreDepart = Depart(
    id: 'dep-uuid-2',
    route: 'Cotonou → Lomé',
    date: '16/08/2026',
    heure: '09:00',
    vehiculeImmat: 'CD-5678-RB',
    totalPlaces: 30,
    placesOccupees: 0,
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

  const Passenger autrePassenger = Passenger(
    id: 'ticket-2',
    nomComplet: 'Koffi Adjovi',
    siege: '05B',
    statut: BoardingStatus.enAttente,
    segmentOrigine: 'Cotonou',
    segmentDestination: 'Lomé',
    ticketId: 'EF34GH',
  );

  ManifestSnapshot snapshotOf(List<Passenger> passengers,
          {bool isStale = false, bool isFromCache = false}) =>
      ManifestSnapshot(
        passengers: passengers,
        version: 'v1',
        fetchedAt: DateTime(2026, 8, 16, 7),
        isStale: isStale,
        isFromCache: isFromCache,
      );

  setUp(() {
    final MockSelectedDepartureLocalDataSource local =
        MockSelectedDepartureLocalDataSource();
    when(() => local.saveSnapshot(any())).thenAnswer((_) async {});
    when(local.clear).thenAnswer((_) async {});
    final MockAuthCubit authCubit = MockAuthCubit();
    when(() => authCubit.stream).thenAnswer((_) => const Stream.empty());
    selectedDepartCubit = SelectedDepartCubit(local, authCubit);
    // Mocks jamais stubbés : ManifesteCubit n'exerce que `enqueue()` (via
    // `updateStatut`), qui ne les appelle pas — voir SyncCubit.enqueue.
    syncCubit = SyncCubit(MockSyncRepository(), MockBoardingRepository(),
        MockSeatReleaseRepository(), MockIncidentRepository());
    repository = MockManifesteRepository();
    when(() => repository.persistStatusUpdate(
          departureUuid: any(named: 'departureUuid'),
          ticketUuid: any(named: 'ticketUuid'),
          statut: any(named: 'statut'),
        )).thenAnswer((_) async {});
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
      when(() => repository.load(depart.id))
          .thenAnswer((_) async => Result<ManifestSnapshot>.success(
                snapshotOf(<Passenger>[passenger]),
              ));
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
    'un manifeste servi hors ligne (cache) se reflète en isStale/isFromCache',
    setUp: () {
      when(() => repository.load(depart.id))
          .thenAnswer((_) async => Result<ManifestSnapshot>.success(
                snapshotOf(<Passenger>[passenger],
                    isStale: true, isFromCache: true),
              ));
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
      predicate<ManifesteState>(
          (s) => s.status == ManifesteStatus.success && s.isStale == true),
    ],
  );

  blocTest<ManifesteCubit, ManifesteState>(
    'un échec réseau sans repli possible passe en erreur',
    setUp: () {
      when(() => repository.load(depart.id)).thenAnswer(
        (_) async => const Result<ManifestSnapshot>.failure(
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

  test(
      'markBoarded met à jour le statut, persiste, sans enfiler de synchro '
      'mock', () async {
    when(() => repository.load(depart.id))
        .thenAnswer((_) async => Result<ManifestSnapshot>.success(
              snapshotOf(<Passenger>[passenger]),
            ));
    await selectedDepartCubit.select(depart);
    final cubit = ManifesteCubit(
      selectedDepartCubit: selectedDepartCubit,
      syncCubit: syncCubit,
      repository: repository,
    );
    await cubit.stream
        .firstWhere((ManifesteState s) => s.status == ManifesteStatus.success);

    await cubit.markBoarded('ticket-1');

    expect(cubit.byId('ticket-1')?.statut, BoardingStatus.embarque);
    expect(syncCubit.state.pending, isEmpty);
    verify(() => repository.persistStatusUpdate(
          departureUuid: depart.id,
          ticketUuid: 'ticket-1',
          statut: BoardingStatus.embarque,
        )).called(1);
  });

  test(
      'markSeatReleaseAccepted met à jour le statut, persiste, et efface '
      "l'indicateur \"en attente de sync\"", () async {
    when(() => repository.load(depart.id))
        .thenAnswer((_) async => Result<ManifestSnapshot>.success(
              snapshotOf(<Passenger>[passenger]),
            ));
    await selectedDepartCubit.select(depart);
    final cubit = ManifesteCubit(
      selectedDepartCubit: selectedDepartCubit,
      syncCubit: syncCubit,
      repository: repository,
    );
    await cubit.stream
        .firstWhere((ManifesteState s) => s.status == ManifesteStatus.success);
    cubit.markSeatReleasePending('ticket-1');
    expect(cubit.state.pendingReleaseTicketUuids, contains('ticket-1'));

    await cubit.markSeatReleaseAccepted(
        'ticket-1', BoardingStatus.descenduAnticipe);

    expect(cubit.byId('ticket-1')?.statut, BoardingStatus.descenduAnticipe);
    expect(cubit.state.pendingReleaseTicketUuids, isNot(contains('ticket-1')));
    verify(() => repository.persistStatusUpdate(
          departureUuid: depart.id,
          ticketUuid: 'ticket-1',
          statut: BoardingStatus.descenduAnticipe,
        )).called(1);
  });

  test('markSeatReleasePending ne modifie jamais le statut affiché', () async {
    when(() => repository.load(depart.id))
        .thenAnswer((_) async => Result<ManifestSnapshot>.success(
              snapshotOf(<Passenger>[passenger]),
            ));
    await selectedDepartCubit.select(depart);
    final cubit = ManifesteCubit(
      selectedDepartCubit: selectedDepartCubit,
      syncCubit: syncCubit,
      repository: repository,
    );
    await cubit.stream
        .firstWhere((ManifesteState s) => s.status == ManifesteStatus.success);

    cubit.markSeatReleasePending('ticket-1');

    expect(cubit.byId('ticket-1')?.statut, BoardingStatus.enAttente);
    expect(cubit.state.pendingReleaseTicketUuids, <String>{'ticket-1'});
  });

  test('markBoardingReversed repasse le passager "en attente" et persiste',
      () async {
    const Passenger boarded = Passenger(
      id: 'ticket-1',
      nomComplet: 'Aïcha Hounkpé',
      siege: '12A',
      statut: BoardingStatus.embarque,
      segmentOrigine: 'Cotonou',
      segmentDestination: 'Porto-Novo',
      ticketId: 'AB12CD',
      boardingEventUuid: 'event-uuid-1',
    );
    when(() => repository.load(depart.id))
        .thenAnswer((_) async => Result<ManifestSnapshot>.success(
              snapshotOf(<Passenger>[boarded]),
            ));
    await selectedDepartCubit.select(depart);
    final cubit = ManifesteCubit(
      selectedDepartCubit: selectedDepartCubit,
      syncCubit: syncCubit,
      repository: repository,
    );
    await cubit.stream
        .firstWhere((ManifesteState s) => s.status == ManifesteStatus.success);

    await cubit.markBoardingReversed('ticket-1');

    expect(cubit.byId('ticket-1')?.statut, BoardingStatus.enAttente);
    verify(() => repository.persistStatusUpdate(
          departureUuid: depart.id,
          ticketUuid: 'ticket-1',
          statut: BoardingStatus.enAttente,
        )).called(1);
  });

  test('searchByShortCode filtre localement, sans appel réseau', () async {
    when(() => repository.load(depart.id))
        .thenAnswer((_) async => Result<ManifestSnapshot>.success(
              snapshotOf(<Passenger>[passenger]),
            ));
    await selectedDepartCubit.select(depart);
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
      when(() => repository.load(depart.id))
          .thenAnswer((_) async => Result<ManifestSnapshot>.success(
                snapshotOf(<Passenger>[passenger]),
              ));
      await selectedDepartCubit.select(depart);
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

  test(
    'changement de départ : recharge le manifeste du nouveau départ',
    () async {
      when(() => repository.load(depart.id))
          .thenAnswer((_) async => Result<ManifestSnapshot>.success(
                snapshotOf(<Passenger>[passenger]),
              ));
      when(() => repository.load(autreDepart.id))
          .thenAnswer((_) async => Result<ManifestSnapshot>.success(
                snapshotOf(<Passenger>[autrePassenger]),
              ));

      await selectedDepartCubit.select(depart);
      final cubit = ManifesteCubit(
        selectedDepartCubit: selectedDepartCubit,
        syncCubit: syncCubit,
        repository: repository,
      );
      await cubit.stream.firstWhere(
          (ManifesteState s) => s.status == ManifesteStatus.success);
      expect(cubit.state.passengers.single.id, 'ticket-1');

      await selectedDepartCubit.select(autreDepart);
      await cubit.stream.firstWhere((ManifesteState s) =>
          s.status == ManifesteStatus.success &&
          s.passengers.any((p) => p.id == 'ticket-2'));

      expect(cubit.state.passengers.single.id, 'ticket-2');
      verify(() => repository.load(depart.id)).called(1);
      verify(() => repository.load(autreDepart.id)).called(1);
    },
  );

  group('indicateur international (MEZ-94)', () {
    ManifestSnapshot snapshotWithFlag(List<Passenger> passengers,
            {bool? isInternational}) =>
        ManifestSnapshot(
          passengers: passengers,
          version: 'v1',
          fetchedAt: DateTime(2026, 8, 16, 7),
          isStale: false,
          isFromCache: false,
          isInternational: isInternational,
        );

    test('le flag du snapshot est exposé dans l\'état', () async {
      when(() => repository.load(depart.id)).thenAnswer((_) async =>
          Result<ManifestSnapshot>.success(
              snapshotWithFlag(<Passenger>[passenger], isInternational: true)));

      await selectedDepartCubit.select(depart);
      final ManifesteCubit cubit = ManifesteCubit(
        selectedDepartCubit: selectedDepartCubit,
        syncCubit: syncCubit,
        repository: repository,
      );
      await cubit.stream.firstWhere(
          (ManifesteState s) => s.status == ManifesteStatus.success);

      expect(cubit.state.isInternational, isTrue);
      await cubit.close();
    });

    test(
        'un flag inconnu (null) remplace la valeur du départ précédent au lieu '
        'de la conserver', () async {
      when(() => repository.load(depart.id)).thenAnswer((_) async =>
          Result<ManifestSnapshot>.success(
              snapshotWithFlag(<Passenger>[passenger], isInternational: true)));
      when(() => repository.load(autreDepart.id)).thenAnswer((_) async =>
          Result<ManifestSnapshot>.success(
              snapshotWithFlag(<Passenger>[autrePassenger])));

      await selectedDepartCubit.select(depart);
      final ManifesteCubit cubit = ManifesteCubit(
        selectedDepartCubit: selectedDepartCubit,
        syncCubit: syncCubit,
        repository: repository,
      );
      await cubit.stream.firstWhere(
          (ManifesteState s) => s.status == ManifesteStatus.success);
      expect(cubit.state.isInternational, isTrue);

      await selectedDepartCubit.select(autreDepart);
      await cubit.stream.firstWhere((ManifesteState s) =>
          s.status == ManifesteStatus.success &&
          s.passengers.any((Passenger p) => p.id == 'ticket-2'));

      expect(cubit.state.isInternational, isNull);
      await cubit.close();
    });

    test('copyWith conserve le flag quand il n\'est pas fourni', () {
      const ManifesteState state = ManifesteState(isInternational: true);

      expect(state.copyWith(status: ManifesteStatus.loading).isInternational,
          isTrue);
      expect(state.copyWith(isInternational: null).isInternational, isNull);
      expect(state.copyWith(isInternational: false).isInternational, isFalse);
    });
  });
}
