// Tests du Bloc de l'accueil : chargement initial, pré-remplissage du
// trajet, saisie du formulaire et remontée d'erreur.

import 'package:bloc_test/bloc_test.dart';
import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/usecases/usecase.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/upcoming_trip.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_cities_usecase.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_next_trip_usecase.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_recent_cities_usecase.dart';
import 'package:kora_app_passenger/features/home/presentation/bloc/home_bloc.dart';
import 'package:mocktail/mocktail.dart';

class MockGetCitiesUseCase extends Mock implements GetCitiesUseCase {}

class MockGetRecentCitiesUseCase extends Mock
    implements GetRecentCitiesUseCase {}

class MockGetNextTripUseCase extends Mock implements GetNextTripUseCase {}

void main() {
  late MockGetCitiesUseCase getCities;
  late MockGetRecentCitiesUseCase getRecentCities;
  late MockGetNextTripUseCase getNextTrip;

  /// Horloge figée : les tests ne doivent pas dépendre du jour où on les
  /// lance.
  final now = DateTime(2026, 8, 13, 10, 30);
  DateTime fixedNow() => now;

  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');
  final parakou = kMockCities.firstWhere((city) => city.id == 'pku');
  final recentCities = mockRecentCities();
  final trip = mockUpcomingTrip(now);

  setUpAll(() {
    registerFallbackValue(const NoParams());
  });

  setUp(() {
    getCities = MockGetCitiesUseCase();
    getRecentCities = MockGetRecentCitiesUseCase();
    getNextTrip = MockGetNextTripUseCase();

    when(() => getCities(any())).thenAnswer((_) async => Right(kMockCities));
    when(
      () => getRecentCities(any()),
    ).thenAnswer((_) async => Right(recentCities));
    when(() => getNextTrip(any())).thenAnswer((_) async => Right(trip));
  });

  HomeBloc buildBloc() =>
      HomeBloc(getCities, getRecentCities, getNextTrip, now: fixedNow);

  group('HomeStarted', () {
    blocTest<HomeBloc, HomeState>(
      'charge les villes et la date du jour, sans pré-remplir le trajet',
      build: buildBloc,
      act: (bloc) => bloc.add(const HomeStarted()),
      expect: () => [
        const HomeState(),
        HomeState(
          status: HomeStatus.success,
          cities: kMockCities,
          recentCities: recentCities,
          // Départ et arrivée restent à choisir : depuis le branchement
          // de l'API, l'app ne devine plus un trajet de démonstration.
          date: DateTime(2026, 8, 13),
          today: DateTime(2026, 8, 13),
          nextTrip: trip,
        ),
      ],
    );

    blocTest<HomeBloc, HomeState>(
      'ne propose pas de recherche tant que le trajet est vide',
      build: buildBloc,
      act: (bloc) => bloc.add(const HomeStarted()),
      verify: (bloc) {
        expect(bloc.state.canSearch, isFalse);
        expect(bloc.state.criteria, isNull);
      },
    );

    blocTest<HomeBloc, HomeState>(
      'passe en erreur si les villes ne peuvent pas être chargées',
      build: () {
        when(
          () => getCities(any()),
        ).thenAnswer((_) async => const Left(ServerFailure('API injoignable')));
        return buildBloc();
      },
      act: (bloc) => bloc.add(const HomeStarted()),
      expect: () => [
        const HomeState(),
        const HomeState(
          status: HomeStatus.failure,
          errorMessage: 'API injoignable',
        ),
      ],
    );

    blocTest<HomeBloc, HomeState>(
      'traite un prochain voyage absent comme un succès, pas comme un vide',
      build: () {
        when(
          () => getNextTrip(any()),
        ).thenAnswer((_) async => const Right<Failure, UpcomingTrip?>(null));
        return buildBloc();
      },
      act: (bloc) => bloc.add(const HomeStarted()),
      verify: (bloc) {
        expect(bloc.state.status, HomeStatus.success);
        expect(bloc.state.nextTrip, isNull);
      },
    );
  });

  group('formulaire de recherche', () {
    blocTest<HomeBloc, HomeState>(
      'intervertit départ et arrivée',
      build: buildBloc,
      seed: () => HomeState(
        status: HomeStatus.success,
        cities: kMockCities,
        origin: cotonou,
        destination: malanville,
      ),
      act: (bloc) => bloc.add(const HomeRouteSwapped()),
      verify: (bloc) {
        expect(bloc.state.origin, malanville);
        expect(bloc.state.destination, cotonou);
      },
    );

    blocTest<HomeBloc, HomeState>(
      "choisir comme départ la ville d'arrivée intervertit le trajet",
      build: buildBloc,
      seed: () => HomeState(
        status: HomeStatus.success,
        cities: kMockCities,
        origin: cotonou,
        destination: malanville,
      ),
      act: (bloc) => bloc.add(HomeOriginSelected(malanville)),
      verify: (bloc) {
        expect(bloc.state.origin, malanville);
        expect(bloc.state.destination, cotonou);
      },
    );

    blocTest<HomeBloc, HomeState>(
      'enregistre la date et le nombre de voyageurs choisis',
      build: buildBloc,
      seed: () => HomeState(
        status: HomeStatus.success,
        cities: kMockCities,
        origin: cotonou,
        destination: parakou,
      ),
      act: (bloc) => bloc
        ..add(HomeDateSelected(DateTime(2026, 9, 1)))
        ..add(const HomePassengersSelected(3)),
      verify: (bloc) {
        expect(bloc.state.date, DateTime(2026, 9, 1));
        expect(bloc.state.passengers, 3);
        expect(bloc.state.criteria?.passengers, 3);
      },
    );

    group('aller-retour', () {
      HomeState roundTrip({DateTime? returnDate}) => HomeState(
        status: HomeStatus.success,
        origin: cotonou,
        destination: parakou,
        date: DateTime(2026, 9, 1),
        tripType: TripType.roundtrip,
        returnDate: returnDate,
      );

      test('sans date de retour, la recherche reste bloquée', () {
        // Le serveur la réclame, et en deviner une ferait chercher un
        // retour que personne n'a demandé.
        expect(roundTrip().canSearch, isFalse);
        expect(roundTrip().criteria, isNull);
      });

      test('avec sa date, les critères la portent', () {
        final state = roundTrip(returnDate: DateTime(2026, 9, 8));

        expect(state.canSearch, isTrue);
        expect(state.criteria!.returnDate, DateTime(2026, 9, 8));
        expect(state.criteria!.isRoundTrip, isTrue);
      });

      test('un aller simple ne transmet aucune date de retour', () {
        // Même si une date traîne dans l'état : c'est le type de voyage qui
        // décide, pas un reste de saisie.
        final state = HomeState(
          status: HomeStatus.success,
          origin: cotonou,
          destination: parakou,
          date: DateTime(2026, 9, 1),
          returnDate: DateTime(2026, 9, 8),
        );

        expect(state.criteria!.returnDate, isNull);
        expect(state.criteria!.isRoundTrip, isFalse);
      });

      blocTest<HomeBloc, HomeState>(
        'revenir à l’aller simple oublie le retour',
        build: buildBloc,
        seed: () => roundTrip(returnDate: DateTime(2026, 9, 8)),
        act: (bloc) => bloc.add(const HomeTripTypeChanged(TripType.oneway)),
        verify: (bloc) {
          // Le garder le ferait réapparaître tel quel à la bascule
          // suivante, alors qu'entre-temps le départ a pu passer devant lui.
          expect(bloc.state.returnDate, isNull);
          expect(bloc.state.tripType, TripType.oneway);
        },
      );

      blocTest<HomeBloc, HomeState>(
        'un départ repoussé après le retour efface le retour',
        build: buildBloc,
        seed: () => roundTrip(returnDate: DateTime(2026, 9, 8)),
        act: (bloc) => bloc.add(HomeDateSelected(DateTime(2026, 9, 20))),
        verify: (bloc) {
          // Le serveur refuserait un retour antérieur à l'aller : mieux vaut
          // redemander la date que partir sur un voyage impossible.
          expect(bloc.state.date, DateTime(2026, 9, 20));
          expect(bloc.state.returnDate, isNull);
          expect(bloc.state.canSearch, isFalse);
        },
      );

      blocTest<HomeBloc, HomeState>(
        'un départ avancé laisse le retour en place',
        build: buildBloc,
        seed: () => roundTrip(returnDate: DateTime(2026, 9, 8)),
        act: (bloc) => bloc.add(HomeDateSelected(DateTime(2026, 9, 3))),
        verify: (bloc) {
          expect(bloc.state.returnDate, DateTime(2026, 9, 8));
          expect(bloc.state.canSearch, isTrue);
        },
      );

      blocTest<HomeBloc, HomeState>(
        'partir le jour du retour reste possible',
        build: buildBloc,
        seed: () => roundTrip(returnDate: DateTime(2026, 9, 8)),
        act: (bloc) => bloc.add(HomeDateSelected(DateTime(2026, 9, 8))),
        verify: (bloc) => expect(bloc.state.returnDate, DateTime(2026, 9, 8)),
      );
    });

    test('canSearch reste faux tant que le trajet est incomplet', () {
      const empty = HomeState(status: HomeStatus.success);
      expect(empty.canSearch, isFalse);
      expect(empty.criteria, isNull);

      final sameCity = HomeState(
        status: HomeStatus.success,
        origin: cotonou,
        destination: cotonou,
      );
      expect(sameCity.canSearch, isFalse);
    });
  });
}
