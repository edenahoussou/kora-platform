// Tests du Bloc des départs : résolution des paramètres d'URL, recherche,
// tri (critère et sens), filtres par créneau, actualisation, liaison non
// desservie et erreur.

import 'package:bloc_test/bloc_test.dart';
import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/usecases/usecase.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/city.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/line.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
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

      final sorted = state.visibleDepartures;
      expect(sorted.first.departsAt.hour, 7);
      expect(sorted.map((d) => d.departsAt.hour).take(3), [7, 9, 20]);
      expect(sorted.last.isSoldOut, isTrue);
    });

    test('trie par prix croissant, les complets toujours en dernier', () {
      final state = DeparturesState(
        status: DeparturesStatus.success,
        departures: departures,
        sortField: DepartureSortField.price,
      );

      final sorted = state.visibleDepartures;
      final prices = sorted.map((d) => d.price.amount).toList();
      expect(prices.take(3), [12500, 12500, 14500]);
      expect(sorted.last.isSoldOut, isTrue);
    });

    test('trie par prix décroissant sans faire remonter les complets', () {
      final state = DeparturesState(
        status: DeparturesStatus.success,
        departures: departures,
        sortField: DepartureSortField.price,
        sortDescending: true,
      );

      final sorted = state.visibleDepartures;
      // Le départ complet est l'un des plus chers : le tri décroissant le
      // remonterait en tête s'il n'était pas relégué en fin de liste.
      expect(sorted.map((d) => d.price.amount).take(3), [14500, 12500, 12500]);
      expect(sorted.last.isSoldOut, isTrue);
    });

    test('trie par durée, le trajet le plus court d’abord', () {
      // Les départs mockés partagent la même ligne, donc la même durée :
      // il faut deux lignes pour que le tri ait quelque chose à départager.
      final longue = kMockCotonouMalanville;
      final courte = kMockLines.reduce(
        (a, b) => a.duration <= b.duration ? a : b,
      );

      Departure departure(Line line, int hour) => Departure(
        id: '${line.id}-$hour',
        line: line,
        departsAt: DateTime(2026, 8, 14, hour),
        comfort: BusComfort.confort,
        price: const Price(12500),
        seatsTotal: 55,
        seatsAvailable: 12,
      );

      final state = DeparturesState(
        status: DeparturesStatus.success,
        // La plus longue en premier : sans tri, l'ordre resterait celui-ci.
        departures: [departure(longue, 7), departure(courte, 9)],
        sortField: DepartureSortField.duration,
      );

      expect(state.visibleDepartures.first.line, courte);
      expect(state.visibleDepartures.last.line, longue);

      final inverse = state.copyWith(sortDescending: true);
      expect(inverse.visibleDepartures.first.line, longue);
    });

    blocTest<DeparturesBloc, DeparturesState>(
      'changer de tri ne relance pas la recherche',
      build: buildBloc,
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesSortChanged(DepartureSortField.price));
      },
      verify: (bloc) {
        expect(bloc.state.sortField, DepartureSortField.price);
        expect(bloc.state.sortDescending, isFalse);
        verify(() => searchDepartures(any())).called(1);
      },
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'retaper la puce active inverse le sens',
      build: buildBloc,
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesSortChanged(DepartureSortField.price));
        bloc.add(const DeparturesSortChanged(DepartureSortField.price));
      },
      verify: (bloc) {
        expect(bloc.state.sortField, DepartureSortField.price);
        expect(bloc.state.sortDescending, isTrue);
      },
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'un nouveau critère de tri repart en croissant',
      build: buildBloc,
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesSortChanged(DepartureSortField.price));
        bloc.add(const DeparturesSortChanged(DepartureSortField.price));
        // Hériter du sens précédent afficherait « le plus tard d'abord »
        // alors que l'utilisateur vient seulement de demander l'heure.
        bloc.add(const DeparturesSortChanged(DepartureSortField.hour));
      },
      verify: (bloc) {
        expect(bloc.state.sortField, DepartureSortField.hour);
        expect(bloc.state.sortDescending, isFalse);
      },
    );
  });

  group('filtres par créneau', () {
    test('sans filtre, tous les départs sont visibles', () {
      final state = DeparturesState(
        status: DeparturesStatus.success,
        departures: departures,
      );

      expect(state.hasFilters, isFalse);
      expect(state.visibleDepartures, hasLength(departures.length));
    });

    test('« Matin » ne garde que les départs d’avant midi', () {
      final state = DeparturesState(
        status: DeparturesStatus.success,
        departures: departures,
        timeSlots: const {DepartureTimeSlot.morning},
      );

      // 07:30 et 09:00 ; 20:00 et 22:30 sont écartés.
      expect(state.visibleDepartures.map((d) => d.departsAt.hour), [7, 9]);
      expect(state.availableCount, 2);
    });

    test('deux créneaux s’additionnent', () {
      final state = DeparturesState(
        status: DeparturesStatus.success,
        departures: departures,
        timeSlots: const {DepartureTimeSlot.morning, DepartureTimeSlot.evening},
      );

      expect(state.visibleDepartures, hasLength(4));
    });

    test('un créneau sans départ donne « aucun départ ne correspond »', () {
      final state = DeparturesState(
        status: DeparturesStatus.success,
        departures: departures,
        timeSlots: const {DepartureTimeSlot.afternoon},
      );

      expect(state.visibleDepartures, isEmpty);
      // Pas `isEmpty` : la liaison est desservie, ce sont les filtres qui
      // sont trop stricts — l'écran affiché n'est pas le même.
      expect(state.isEmpty, isFalse);
      expect(state.isFilteredEmpty, isTrue);
    });

    test('le compteur du titre ne compte que les départs visibles', () {
      final all = DeparturesState(
        status: DeparturesStatus.success,
        departures: departures,
      );
      // 4 départs mockés dont un complet.
      expect(all.availableCount, 3);

      final evening = all.copyWith(
        timeSlots: const {DepartureTimeSlot.evening},
      );
      // 20:00 et 22:30, dont le complet : un seul réservable.
      expect(evening.availableCount, 1);
    });

    blocTest<DeparturesBloc, DeparturesState>(
      'la puce s’ajoute puis se retire, sans relancer la recherche',
      build: buildBloc,
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesTimeSlotToggled(DepartureTimeSlot.morning));
        bloc.add(const DeparturesTimeSlotToggled(DepartureTimeSlot.evening));
        bloc.add(const DeparturesTimeSlotToggled(DepartureTimeSlot.morning));
      },
      verify: (bloc) {
        expect(bloc.state.timeSlots, {DepartureTimeSlot.evening});
        verify(() => searchDepartures(any())).called(1);
      },
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'DeparturesFiltersCleared rend la liste complète',
      build: buildBloc,
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesTimeSlotToggled(DepartureTimeSlot.afternoon));
        bloc.add(const DeparturesFiltersCleared());
      },
      verify: (bloc) {
        expect(bloc.state.hasFilters, isFalse);
        expect(bloc.state.visibleDepartures, hasLength(4));
      },
    );
  });

  group('DeparturesRefreshed', () {
    blocTest<DeparturesBloc, DeparturesState>(
      'rejoue la recherche sans repasser par la liste des villes',
      build: buildBloc,
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesRefreshed());
      },
      verify: (bloc) {
        expect(bloc.state.status, DeparturesStatus.success);
        expect(bloc.state.isRefreshing, isFalse);
        verify(() => searchDepartures(any())).called(2);
        // Les villes n'ont été résolues qu'une fois : l'actualisation part
        // des critères déjà connus.
        verify(() => getCities(any())).called(1);
      },
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'garde les filtres et le tri en place',
      build: buildBloc,
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesSortChanged(DepartureSortField.price));
        bloc.add(const DeparturesTimeSlotToggled(DepartureTimeSlot.morning));
        bloc.add(const DeparturesRefreshed());
      },
      verify: (bloc) {
        expect(bloc.state.sortField, DepartureSortField.price);
        expect(bloc.state.timeSlots, {DepartureTimeSlot.morning});
      },
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'un échec d’actualisation laisse les résultats à l’écran',
      build: () {
        var attempt = 0;
        when(() => searchDepartures(any())).thenAnswer((_) async {
          attempt += 1;
          return attempt == 1
              ? Right(departures)
              : const Left(NetworkFailure());
        });
        return buildBloc();
      },
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesRefreshed());
      },
      verify: (bloc) {
        // Des places légèrement périmées valent mieux qu'un écran d'erreur
        // à la place de résultats corrects.
        expect(bloc.state.status, DeparturesStatus.success);
        expect(bloc.state.departures, departures);
        expect(bloc.state.isRefreshing, isFalse);
        expect(
          bloc.state.refreshError,
          'Vérifie ta connexion internet et réessaie.',
        );
      },
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'le message d’échec ne survit pas au geste suivant',
      build: () {
        var attempt = 0;
        when(() => searchDepartures(any())).thenAnswer((_) async {
          attempt += 1;
          return attempt == 1
              ? Right(departures)
              : const Left(NetworkFailure());
        });
        return buildBloc();
      },
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesRefreshed());
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesSortChanged(DepartureSortField.price));
      },
      verify: (bloc) => expect(bloc.state.refreshError, isNull),
    );

    blocTest<DeparturesBloc, DeparturesState>(
      'sans critères résolus, il n’y a rien à actualiser',
      build: buildBloc,
      act: (bloc) => bloc.add(const DeparturesRefreshed()),
      expect: () => const <DeparturesState>[],
      verify: (_) => verifyNever(() => searchDepartures(any())),
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

    blocTest<DeparturesBloc, DeparturesState>(
      'un échec réseau ne fait pas perdre les filtres réglés',
      build: () {
        var attempt = 0;
        when(() => searchDepartures(any())).thenAnswer((_) async {
          attempt += 1;
          return attempt == 1
              ? Right(departures)
              : const Left(NetworkFailure());
        });
        return buildBloc();
      },
      act: (bloc) async {
        bloc.add(requested);
        await Future<void>.delayed(Duration.zero);
        bloc.add(const DeparturesTimeSlotToggled(DepartureTimeSlot.morning));
        bloc.add(const DeparturesSortChanged(DepartureSortField.duration));
        bloc.add(const DeparturesRetried());
      },
      verify: (bloc) {
        expect(bloc.state.status, DeparturesStatus.failure);
        expect(bloc.state.timeSlots, {DepartureTimeSlot.morning});
        expect(bloc.state.sortField, DepartureSortField.duration);
      },
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
