import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:kora_ops_mobile/features/ops/domain/repositories/departure_repository.dart';
import 'package:kora_ops_mobile/features/ops/presentation/cubit/departure_list_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockDepartureRepository extends Mock implements DepartureRepository {}

/// Même format que `DepartureListCubit._isoDate` (privée) — dupliqué ici
/// pour affirmer la date exacte envoyée au repository plutôt que
/// n'importe laquelle (`any(named: 'date')`).
String _isoDate(DateTime date) {
  String two(int n) => n.toString().padLeft(2, '0');
  return '${date.year}-${two(date.month)}-${two(date.day)}';
}

void main() {
  late MockDepartureRepository repository;

  const Depart depart = Depart(
    id: 'dep-uuid-1',
    route: 'Cotonou → Porto-Novo',
    date: '16/08/2026',
    heure: '07:30',
    vehiculeImmat: 'AB-1234-RB',
    totalPlaces: 45,
    placesOccupees: 0,
  );

  final String today = _isoDate(DateTime.now());
  final DateTime otherDay = DateTime(2026, 10, 1);
  const String otherDayIso = '2026-10-01';

  setUp(() {
    repository = MockDepartureRepository();
  });

  group('loadUpcomingToday (vue par défaut)', () {
    blocTest<DepartureListCubit, DepartureListState>(
      'succès avec départs : loading puis success, date du jour transmise',
      setUp: () {
        when(() => repository.listAssigned(date: today, status: null))
            .thenAnswer((_) async => const Result<List<Depart>>.success(
                  <Depart>[depart],
                ));
      },
      build: () => DepartureListCubit(repository),
      act: (cubit) => cubit.loadUpcomingToday(),
      expect: () => <dynamic>[
        predicate<DepartureListState>((s) =>
            s.status == DepartureListStatus.loading && s.selectedDate == null),
        predicate<DepartureListState>((s) =>
            s.status == DepartureListStatus.success &&
            s.departs.length == 1 &&
            s.selectedDate == null),
      ],
      verify: (_) {
        verify(() => repository.listAssigned(date: today, status: null))
            .called(1);
      },
    );

    blocTest<DepartureListCubit, DepartureListState>(
      'aucun départ affecté aujourd\'hui : loading puis empty',
      setUp: () {
        when(() => repository.listAssigned(date: today, status: null))
            .thenAnswer(
                (_) async => const Result<List<Depart>>.success(<Depart>[]));
      },
      build: () => DepartureListCubit(repository),
      act: (cubit) => cubit.loadUpcomingToday(),
      expect: () => <dynamic>[
        predicate<DepartureListState>(
            (s) => s.status == DepartureListStatus.loading),
        predicate<DepartureListState>(
            (s) => s.status == DepartureListStatus.empty),
      ],
    );

    blocTest<DepartureListCubit, DepartureListState>(
      'échec réseau : loading puis error avec message',
      setUp: () {
        when(() => repository.listAssigned(date: today, status: null))
            .thenAnswer((_) async => const Result<List<Depart>>.failure(
                  NetworkFailure('Pas de connexion.'),
                ));
      },
      build: () => DepartureListCubit(repository),
      act: (cubit) => cubit.loadUpcomingToday(),
      expect: () => <dynamic>[
        predicate<DepartureListState>(
            (s) => s.status == DepartureListStatus.loading),
        predicate<DepartureListState>((s) =>
            s.status == DepartureListStatus.error &&
            s.errorMessage == 'Pas de connexion.'),
      ],
    );

    blocTest<DepartureListCubit, DepartureListState>(
      'efface un filtre déjà actif (selectedDate repasse à null)',
      setUp: () {
        when(() => repository.listAssigned(date: today, status: null))
            .thenAnswer((_) async => const Result<List<Depart>>.success(
                  <Depart>[depart],
                ));
      },
      build: () => DepartureListCubit(repository),
      seed: () => DepartureListState(
        status: DepartureListStatus.success,
        departs: const <Depart>[depart],
        selectedDate: otherDay,
      ),
      act: (cubit) => cubit.loadUpcomingToday(),
      expect: () => <dynamic>[
        predicate<DepartureListState>((s) =>
            s.status == DepartureListStatus.loading && s.selectedDate == null),
        predicate<DepartureListState>(
            (s) => s.status == DepartureListStatus.success),
      ],
    );
  });

  group('load (filtre explicite du FAB)', () {
    blocTest<DepartureListCubit, DepartureListState>(
      'la date choisie est transmise au repository et mémorisée dans le state',
      setUp: () {
        when(() => repository.listAssigned(date: otherDayIso, status: null))
            .thenAnswer((_) async => const Result<List<Depart>>.success(
                  <Depart>[depart],
                ));
      },
      build: () => DepartureListCubit(repository),
      act: (cubit) => cubit.load(otherDay),
      expect: () => <dynamic>[
        predicate<DepartureListState>((s) =>
            s.status == DepartureListStatus.loading &&
            s.selectedDate == otherDay),
        predicate<DepartureListState>((s) =>
            s.status == DepartureListStatus.success &&
            s.selectedDate == otherDay),
      ],
      verify: (_) {
        verify(() => repository.listAssigned(date: otherDayIso, status: null))
            .called(1);
      },
    );

    blocTest<DepartureListCubit, DepartureListState>(
      'aucun départ ce jour-là : loading puis empty',
      setUp: () {
        when(() => repository.listAssigned(date: otherDayIso, status: null))
            .thenAnswer(
                (_) async => const Result<List<Depart>>.success(<Depart>[]));
      },
      build: () => DepartureListCubit(repository),
      act: (cubit) => cubit.load(otherDay),
      expect: () => <dynamic>[
        predicate<DepartureListState>(
            (s) => s.status == DepartureListStatus.loading),
        predicate<DepartureListState>(
            (s) => s.status == DepartureListStatus.empty),
      ],
    );
  });

  group('retry (bouton "Réessayer")', () {
    blocTest<DepartureListCubit, DepartureListState>(
      'sans filtre actif : rejoue loadUpcomingToday (date du jour)',
      setUp: () {
        when(() => repository.listAssigned(date: today, status: null))
            .thenAnswer((_) async => const Result<List<Depart>>.success(
                  <Depart>[depart],
                ));
      },
      build: () => DepartureListCubit(repository),
      seed: () => const DepartureListState(
        status: DepartureListStatus.error,
        errorMessage: 'Pas de connexion.',
      ),
      act: (cubit) => cubit.retry(),
      expect: () => <dynamic>[
        predicate<DepartureListState>(
            (s) => s.status == DepartureListStatus.loading),
        predicate<DepartureListState>(
            (s) => s.status == DepartureListStatus.success),
      ],
      verify: (_) {
        verify(() => repository.listAssigned(date: today, status: null))
            .called(1);
      },
    );

    blocTest<DepartureListCubit, DepartureListState>(
      'avec un filtre actif : rejoue load(date) sur la même date',
      setUp: () {
        when(() => repository.listAssigned(date: otherDayIso, status: null))
            .thenAnswer((_) async => const Result<List<Depart>>.success(
                  <Depart>[depart],
                ));
      },
      build: () => DepartureListCubit(repository),
      seed: () => DepartureListState(
        status: DepartureListStatus.error,
        errorMessage: 'Pas de connexion.',
        selectedDate: otherDay,
      ),
      act: (cubit) => cubit.retry(),
      expect: () => <dynamic>[
        predicate<DepartureListState>((s) =>
            s.status == DepartureListStatus.loading &&
            s.selectedDate == otherDay),
        predicate<DepartureListState>((s) =>
            s.status == DepartureListStatus.success &&
            s.selectedDate == otherDay),
      ],
      verify: (_) {
        verify(() => repository.listAssigned(date: otherDayIso, status: null))
            .called(1);
      },
    );
  });

  blocTest<DepartureListCubit, DepartureListState>(
    'clearDateFilter revient à la vue par défaut (loadUpcomingToday)',
    setUp: () {
      when(() => repository.listAssigned(date: today, status: null)).thenAnswer(
          (_) async => const Result<List<Depart>>.success(<Depart>[depart]));
    },
    build: () => DepartureListCubit(repository),
    seed: () => DepartureListState(
      status: DepartureListStatus.success,
      departs: const <Depart>[depart],
      selectedDate: otherDay,
    ),
    act: (cubit) => cubit.clearDateFilter(),
    expect: () => <dynamic>[
      predicate<DepartureListState>((s) =>
          s.status == DepartureListStatus.loading && s.selectedDate == null),
      predicate<DepartureListState>((s) =>
          s.status == DepartureListStatus.success && s.selectedDate == null),
    ],
    verify: (_) {
      verify(() => repository.listAssigned(date: today, status: null))
          .called(1);
    },
  );
}
