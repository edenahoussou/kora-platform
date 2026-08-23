// Tests du Bloc des départs : résolution des paramètres d'URL, recherche,
// tri, liaison non desservie et erreur.

import 'package:bloc_test/bloc_test.dart';
import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/usecases/usecase.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/city.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_cities_usecase.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/search_departures_usecase.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/departures_bloc.dart';
import 'package:mocktail/mocktail.dart';

class MockGetCitiesUseCase extends Mock implements GetCitiesUseCase {}

class MockSearchDeparturesUseCase extends Mock
    implements SearchDeparturesUseCase {}

class _FakeCriteria extends Fake implements SearchCriteria {}

void main() {
  late MockGetCitiesUseCase getCities;
  late MockSearchDeparturesUseCase searchDepartures;

  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');

  /// Les critères tels que le Bloc doit les reconstruire depuis l'URL
  /// utilisée par les tests ci-dessous.
  final criteria = SearchCriteria(
    origin: cotonou,
    destination: malanville,
    date: DateTime(2026, 8, 14),
    passengers: 2,
  );
  final departures = mockDeparturesFor(criteria);

  const requested = DeparturesRequested(
    originId: 'ctn',
    destinationId: 'mlv',
    date: '2026-08-14',
    passengers: '2',
  );

  setUpAll(() {
    registerFallbackValue(const NoParams());
    registerFallbackValue(_FakeCriteria());
  });

  setUp(() {
    getCities = MockGetCitiesUseCase();
    searchDepartures = MockSearchDeparturesUseCase();

    when(() => getCities(any())).thenAnswer((_) async => Right(kMockCities));
    when(
      () => searchDepartures(any()),
    ).thenAnswer((_) async => Right(departures));
  });

  DeparturesBloc buildBloc() => DeparturesBloc(getCities, searchDepartures);

  group('DeparturesRequested', () {
    blocTest<DeparturesBloc, DeparturesState>(
      'résout les paramètres d’URL puis affiche les départs trouvés',
      build: buildBloc,
      act: (bloc) => bloc.add(requested),
      verify: (bloc) {
        expect(bloc.state.status, DeparturesStatus.success);
        expect(bloc.state.departures, departures);

        // Les critères sont reconstruits depuis l'URL, passagers compris.
        expect(bloc.state.criteria?.origin, cotonou);
        expect(bloc.state.criteria?.destination, malanville);
        expect(bloc.state.criteria?.date, DateTime(2026, 8, 14));
        expect(bloc.state.criteria?.passengers, 2);

        // 4 départs mockés, dont un complet.
        expect(bloc.state.availableCount, 3);
      },
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'annonce le trajet avant même d’avoir les résultats',
      build: buildBloc,
      act: (bloc) => bloc.add(requested),
      expect: () => [
        // 1. chargement, 2. trajet résolu (toujours en chargement),
        // 3. résultats.
        const DeparturesState(),
        DeparturesState(criteria: criteria),
        DeparturesState(
          status: DeparturesStatus.success,
          criteria: criteria,
          departures: departures,
        ),
      ],
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'traite une liaison non desservie comme un résultat vide',
      build: () {
        when(
          () => searchDepartures(any()),
        ).thenAnswer((_) async => const Right<Failure, List<Departure>>([]));
        return buildBloc();
      },
      act: (bloc) => bloc.add(requested),
      verify: (bloc) {
        expect(bloc.state.status, DeparturesStatus.success);
        expect(bloc.state.isEmpty, isTrue);
      },
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'refuse une recherche dont un paramètre est inconnu',
      build: buildBloc,
      act: (bloc) => bloc.add(
        const DeparturesRequested(
          originId: 'inconnue',
          destinationId: 'mlv',
          date: '2026-08-14',
        ),
      ),
      verify: (bloc) {
        expect(bloc.state.status, DeparturesStatus.failure);
        expect(bloc.state.errorMessage, contains('incomplète'));
        verifyNever(() => searchDepartures(any()));
      },
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'remonte l’échec de la recherche',
      build: () {
        when(
          () => searchDepartures(any()),
        ).thenAnswer((_) async => const Left(NetworkFailure()));
        return buildBloc();
      },
      act: (bloc) => bloc.add(requested),
      verify: (bloc) {
        expect(bloc.state.status, DeparturesStatus.failure);
        expect(
          bloc.state.errorMessage,
          'Vérifie ta connexion internet et réessaie.',
        );
      },
    );
  });

  group('tri', () {
    test('trie par heure de départ, les complets en dernier', () {
      final state = DeparturesState(
        status: DeparturesStatus.success,
        departures: departures,
      );

      final sorted = state.sortedDepartures;
      expect(sorted.first.departsAt.hour, 7);
      expect(sorted.map((d) => d.departsAt.hour).take(3), [7, 9, 20]);
      expect(sorted.last.isSoldOut, isTrue);
    });

    test('trie par prix croissant, les complets toujours en dernier', () {
      final state = DeparturesState(
        status: DeparturesStatus.success,
        departures: departures,
        sort: DepartureSort.price,
      );

      final sorted = state.sortedDepartures;
      final prices = sorted.map((d) => d.price.amount).toList();
      expect(prices.take(3), [12500, 12500, 14500]);
      expect(sorted.last.isSoldOut, isTrue);
    });

    blocTest<DeparturesBloc, DeparturesState>(
      'changer de tri ne relance pas la recherche',
      build: buildBloc,
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesSortChanged(DepartureSort.price));
      },
      verify: (bloc) {
        expect(bloc.state.sort, DepartureSort.price);
        verify(() => searchDepartures(any())).called(1);
      },
    );
  });

  group('DeparturesRetried', () {
    blocTest<DeparturesBloc, DeparturesState>(
      'rejoue la dernière recherche après un échec réseau',
      build: () {
        // Premier appel en échec, second en succès : exactement ce que
        // vit quelqu'un dont la connexion revient.
        var attempt = 0;
        when(() => searchDepartures(any())).thenAnswer((_) async {
          attempt += 1;
          return attempt == 1
              ? const Left(NetworkFailure())
              : Right(departures);
        });
        return buildBloc();
      },
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesRetried());
      },
      verify: (bloc) {
        expect(bloc.state.status, DeparturesStatus.success);
        expect(bloc.state.departures, departures);
        // La recherche est bien relancée, avec les mêmes critères.
        verify(() => searchDepartures(any())).called(2);
      },
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'sans recherche précédente, il n’y a rien à rejouer',
      build: buildBloc,
      act: (bloc) => bloc.add(const DeparturesRetried()),
      expect: () => const <DeparturesState>[],
      verify: (_) => verifyNever(() => searchDepartures(any())),
    );
  });

  group('identifiants venus de l’API', () {
    // Depuis le branchement de l'API, l'identifiant d'une ville est son
    // **nom** (voir `CityModel.toEntity`) — donc potentiellement avec
    // espaces et accents. Ce test vérifie que l'aller-retour par l'URL des
    // résultats les rend intacts au Bloc.
    const porto = City(id: 'Porto-Novo', name: 'Porto-Novo', code: 'POR');
    const save = City(id: 'Savè', name: 'Savè', code: 'SAV');

    blocTest<DeparturesBloc, DeparturesState>(
      'résout des villes dont l’identifiant porte accents et tirets',
      build: () {
        when(
          () => getCities(any()),
        ).thenAnswer((_) async => const Right([porto, save]));
        return buildBloc();
      },
      act: (bloc) {
        final url = Uri.parse(
          AppRouter.departuresUrlFor(
            SearchCriteria(
              origin: porto,
              destination: save,
              date: DateTime(2026, 8, 14),
            ),
          ),
        );

        bloc.add(
          DeparturesRequested(
            originId: url.queryParameters['origin'],
            destinationId: url.queryParameters['destination'],
            date: url.queryParameters['date'],
            passengers: url.queryParameters['passengers'],
          ),
        );
      },
      verify: (bloc) {
        expect(bloc.state.status, DeparturesStatus.success);
        expect(bloc.state.criteria?.origin, porto);
        expect(bloc.state.criteria?.destination, save);
      },
    );
  });
}
