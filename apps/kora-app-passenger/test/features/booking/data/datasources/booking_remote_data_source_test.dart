// Le data source réseau du parcours Bus : ce qu'il demande à l'API, ce
// qu'il en fait, et ce qu'il continue de déléguer au jeu de démonstration.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/constants/api_constants.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_remote_data_source.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/city.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:mocktail/mocktail.dart';

import '../../../../support/bus_api_fixtures.dart';

class MockDio extends Mock implements Dio {}

class MockBookingDataSource extends Mock implements BookingDataSource {}

void main() {
  late MockDio dio;
  late MockBookingDataSource pending;
  late BookingRemoteDataSourceImpl dataSource;

  const cotonou = City(id: 'Cotonou', name: 'Cotonou', code: 'COT');
  const abidjan = City(id: 'Abidjan', name: 'Abidjan', code: 'ABI');
  final criteria = SearchCriteria(
    origin: cotonou,
    destination: abidjan,
    date: DateTime(2026, 8, 20),
  );

  Response<dynamic> responseWith(Object? data) => Response<dynamic>(
    requestOptions: RequestOptions(path: '/'),
    statusCode: 200,
    data: data,
  );

  void stubGet(Object? data) {
    when(
      () => dio.get<dynamic>(
        any(),
        queryParameters: any(named: 'queryParameters'),
      ),
    ).thenAnswer((_) async => responseWith(data));
  }

  setUp(() {
    dio = MockDio();
    pending = MockBookingDataSource();
    dataSource = BookingRemoteDataSourceImpl(dio, pending: pending);
  });

  group('getCities', () {
    test('appelle l’endpoint des villes et rend des City du domaine', () async {
      stubGet(kCitiesResponse);

      final cities = await dataSource.getCities();

      verify(
        () => dio.get<dynamic>(
          ApiConstants.busCities,
          queryParameters: any(named: 'queryParameters'),
        ),
      ).called(1);
      expect(cities.map((city) => city.name), [
        // Rendues dans l'ordre alphabétique, pas dans celui de l'API.
        'Abidjan',
        'Bohicon',
        'Cotonou',
      ]);
      expect(cities.first.id, 'Abidjan');
    });

    test('écarte une entrée sans nom de ville', () async {
      stubGet(const {
        'data': [
          {'city': 'Cotonou', 'country': 'BJ', 'stops_count': 3},
          {'country': 'BJ', 'stops_count': 0},
        ],
      });

      expect(await dataSource.getCities(), hasLength(1));
    });

    test('une réponse vide est une liste vide, pas une erreur', () async {
      stubGet(const {'data': <dynamic>[]});

      expect(await dataSource.getCities(), isEmpty);
    });

    test('laisse remonter l’erreur réseau au repository', () async {
      when(
        () => dio.get<dynamic>(
          any(),
          queryParameters: any(named: 'queryParameters'),
        ),
      ).thenThrow(
        DioException(
          requestOptions: RequestOptions(path: ApiConstants.busCities),
          type: DioExceptionType.connectionError,
        ),
      );

      expect(dataSource.getCities(), throwsA(isA<DioException>()));
    });
  });

  group('searchDepartures', () {
    test('envoie la date au format de l’API et les noms de villes', () async {
      stubGet(const {
        'data': [kDeparture],
      });

      await dataSource.searchDepartures(criteria);

      final captured = verify(
        () => dio.get<dynamic>(
          ApiConstants.busDepartures,
          queryParameters: captureAny(named: 'queryParameters'),
        ),
      ).captured.single;

      expect(captured, {
        'date': '2026-08-20',
        'from': 'Cotonou',
        'to': 'Abidjan',
      });
    });

    test('complète la date sur deux chiffres', () async {
      stubGet(const {'data': <dynamic>[]});

      await dataSource.searchDepartures(
        SearchCriteria(
          origin: cotonou,
          destination: abidjan,
          date: DateTime(2026, 9, 5),
        ),
      );

      final captured =
          verify(
                () => dio.get<dynamic>(
                  any(),
                  queryParameters: captureAny(named: 'queryParameters'),
                ),
              ).captured.single
              as Map<String, dynamic>;

      expect(captured['date'], '2026-09-05');
    });

    test('rend les départs lus depuis la réponse', () async {
      stubGet(const {
        'data': [kDeparture],
      });

      final departures = await dataSource.searchDepartures(criteria);

      expect(departures, hasLength(1));
      expect(departures.single.id, 'a3091549-9179-454e-8440-58179f2208df');
      expect(departures.single.departsAt, DateTime(2026, 8, 20, 6));
    });

    test('aucun départ n’est un résultat, pas une erreur', () async {
      stubGet(const {'data': <dynamic>[]});

      expect(await dataSource.searchDepartures(criteria), isEmpty);
    });

    test('ignore un départ non datable sans perdre les autres', () async {
      stubGet({
        'data': [
          kDeparture,
          Map<String, dynamic>.from(kDeparture)..['departure_date'] = '',
        ],
      });

      expect(await dataSource.searchDepartures(criteria), hasLength(1));
    });
  });

  group('écrans pas encore servis par l’API', () {
    test('le prochain voyage et les billets passent par le repli', () async {
      when(() => pending.getNextTrip()).thenAnswer((_) async => null);
      when(() => pending.getTickets()).thenAnswer((_) async => const []);

      await dataSource.getNextTrip();
      await dataSource.getTickets();

      verify(() => pending.getNextTrip()).called(1);
      verify(() => pending.getTickets()).called(1);
      verifyNever(
        () => dio.get<dynamic>(
          any(),
          queryParameters: any(named: 'queryParameters'),
        ),
      );
    });

    test('les recherches récentes sont vides, jamais mockées', () async {
      expect(await dataSource.getRecentCities(), isEmpty);
      verifyNever(() => pending.getRecentCities());
    });
  });
}
