import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:kora_ops_mobile/features/ops/domain/repositories/departure_repository.dart';
import 'package:kora_ops_mobile/features/ops/presentation/cubit/departure_list_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockDepartureRepository extends Mock implements DepartureRepository {}

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

  setUp(() {
    repository = MockDepartureRepository();
  });

  blocTest<DepartureListCubit, DepartureListState>(
    'succès avec départs : loading puis success',
    setUp: () {
      when(() => repository.listAssigned(date: null, status: null))
          .thenAnswer((_) async => const Result<List<Depart>>.success(
                <Depart>[depart],
              ));
    },
    build: () => DepartureListCubit(repository),
    act: (cubit) => cubit.load(),
    expect: () => <dynamic>[
      predicate<DepartureListState>(
          (s) => s.status == DepartureListStatus.loading),
      predicate<DepartureListState>((s) =>
          s.status == DepartureListStatus.success && s.departs.length == 1),
    ],
  );

  blocTest<DepartureListCubit, DepartureListState>(
    'aucun départ affecté : loading puis empty',
    setUp: () {
      when(() => repository.listAssigned(date: null, status: null)).thenAnswer(
          (_) async => const Result<List<Depart>>.success(<Depart>[]));
    },
    build: () => DepartureListCubit(repository),
    act: (cubit) => cubit.load(),
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
      when(() => repository.listAssigned(date: null, status: null))
          .thenAnswer((_) async => const Result<List<Depart>>.failure(
                NetworkFailure('Pas de connexion.'),
              ));
    },
    build: () => DepartureListCubit(repository),
    act: (cubit) => cubit.load(),
    expect: () => <dynamic>[
      predicate<DepartureListState>(
          (s) => s.status == DepartureListStatus.loading),
      predicate<DepartureListState>((s) =>
          s.status == DepartureListStatus.error &&
          s.errorMessage == 'Pas de connexion.'),
    ],
  );

  blocTest<DepartureListCubit, DepartureListState>(
    'filtres date/status transmis au repository',
    setUp: () {
      when(() =>
              repository.listAssigned(date: '2026-10-01', status: 'scheduled'))
          .thenAnswer((_) async => const Result<List<Depart>>.success(
                <Depart>[depart],
              ));
    },
    build: () => DepartureListCubit(repository),
    act: (cubit) => cubit.load(date: '2026-10-01', status: 'scheduled'),
    verify: (_) {
      verify(() =>
              repository.listAssigned(date: '2026-10-01', status: 'scheduled'))
          .called(1);
    },
  );
}
