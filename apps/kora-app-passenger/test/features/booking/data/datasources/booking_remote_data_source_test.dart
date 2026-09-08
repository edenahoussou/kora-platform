// Le data source réseau du parcours Bus : ce qu'il demande à l'API, ce
// qu'il en fait, et ce qu'il continue de déléguer au jeu de démonstration.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/constants/api_constants.dart';
import 'package:kora_app_passenger/core/error/exceptions.dart';
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

  const cotonou = City(
    id: 'Cotonou',
    name: 'Cotonou',
    code: 'COT',
    physicalStopId: '74703fce-3358-429d-9994-6b323664a419',
  );
  const abidjan = City(
    id: 'Abidjan',
    name: 'Abidjan',
    code: 'ABI',
    physicalStopId: 'f2b528f4-3c99-461f-ac4e-a10f0721b388',
  );
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
        // Bohicon manque à l'appel : le catalogue ne lui rattache aucun
        // arrêt physique, et la recherche n'aurait rien à interroger.
        'Abidjan',
        'Cotonou',
      ]);
      expect(cities.first.id, 'Abidjan');
      expect(
        cities.first.physicalStopId,
        'f2b528f4-3c99-461f-ac4e-a10f0721b388',
      );
    });

    test('écarte une entrée sans nom de ville', () async {
      stubGet(const {
        'data': [
          {
            'city': 'Cotonou',
            'country': 'BJ',
            'stops_count': 3,
            'physical_stops': [kCotonouPhysicalStop],
          },
          {
            'country': 'BJ',
            'stops_count': 0,
            'physical_stops': [kAbidjanPhysicalStop],
          },
        ],
      });

      expect(await dataSource.getCities(), hasLength(1));
    });

    test('écarte une ville qu'
        "'"
        'aucun arrêt ne dessert', () async {
      // Elle apparaîtrait dans le sélecteur pour mener à une impasse :
      // `bus/itineraries` ne sait interroger que des arrêts.
      stubGet(const {
        'data': [
          {
            'city': 'Cotonou',
            'country': 'BJ',
            'stops_count': 3,
            'physical_stops': [kCotonouPhysicalStop],
          },
          {'city': 'Savè', 'country': 'BJ', 'stops_count': 0},
        ],
      });

      expect((await dataSource.getCities()).map((city) => city.name), [
        'Cotonou',
      ]);
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
    test('interroge les arrêts physiques, plus les noms de villes', () async {
      // Le changement de contrat de MEZ-63 : `bus/itineraries` valide ses
      // deux paramètres contre la table `physical_stops`. Un nom de ville
      // y est refusé net.
      stubGet(const {
        'data': [kItinerary],
      });

      await dataSource.searchDepartures(criteria);

      final captured = verify(
        () => dio.get<dynamic>(
          ApiConstants.busItineraries,
          queryParameters: captureAny(named: 'queryParameters'),
        ),
      ).captured.single;

      expect(captured, {
        'from_physical_stop_uuid': '74703fce-3358-429d-9994-6b323664a419',
        'to_physical_stop_uuid': 'f2b528f4-3c99-461f-ac4e-a10f0721b388',
        'date': '2026-08-20',
        'passenger_count': 1,
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

    test('transmet le nombre de voyageurs demandé', () async {
      stubGet(const {'data': <dynamic>[]});

      await dataSource.searchDepartures(
        SearchCriteria(
          origin: cotonou,
          destination: abidjan,
          date: DateTime(2026, 8, 20),
          passengers: 5,
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

      expect(captured['passenger_count'], 5);
    });

    test('une ville sans arrêt desservi n’est jamais envoyée', () async {
      // Le serveur répondrait 422. Échouer ici donne un message que
      // l'écran sait afficher, plutôt qu'une erreur de validation brute.
      await expectLater(
        dataSource.searchDepartures(
          SearchCriteria(
            origin: City.named('Cotonou'),
            destination: abidjan,
            date: DateTime(2026, 8, 20),
          ),
        ),
        throwsA(isA<ServerException>()),
      );

      verifyNever(
        () => dio.get<dynamic>(
          any(),
          queryParameters: any(named: 'queryParameters'),
        ),
      );
    });

    test('rend les départs lus depuis la réponse', () async {
      stubGet(const {
        'data': [kItinerary],
      });

      final search = await dataSource.searchDepartures(criteria);

      expect(search.outbound, hasLength(1));
      expect(search.outbound.single.id, '56f674c2-7795-4427-9839-ff5de7345ade');
      expect(search.outbound.single.price.amount, 31600);
      // Aucun retour demandé : il n'y a pas d'étape de retour du tout.
      expect(search.inbound, isNull);
      expect(search.isRoundTrip, isFalse);
    });

    test('aucun itinéraire n’est un résultat, pas une erreur', () async {
      stubGet(const {'data': <dynamic>[]});

      expect((await dataSource.searchDepartures(criteria)).outbound, isEmpty);
    });

    test('écarte les correspondances sans perdre les directs', () async {
      // L'app ne sait afficher qu'un trajet direct ; le serveur, lui, rend
      // les deux dans la même liste.
      stubGet({
        'data': [
          kItinerary,
          {
            ...kItinerary,
            'connections_count': 1,
            'legs': [kItineraryLeg, kItineraryLeg],
          },
        ],
      });

      expect(
        (await dataSource.searchDepartures(criteria)).outbound,
        hasLength(1),
      );
    });
  });

  group('aller-retour', () {
    /// La réponse d'un aller-retour : deux listes nommées, et pas
    /// d'enveloppe `data`.
    Map<String, dynamic> roundTripResponse({
      List<dynamic> outbound = const [kItinerary],
      List<dynamic> inbound = const [kItinerary],
    }) => {'outbound': outbound, 'inbound': inbound};

    SearchCriteria roundTrip({DateTime? returnDate}) => SearchCriteria(
      origin: cotonou,
      destination: abidjan,
      date: DateTime(2026, 8, 20),
      returnDate: returnDate ?? DateTime(2026, 8, 24),
    );

    test('déclare le type de trajet et la date de retour', () async {
      stubGet(roundTripResponse());

      await dataSource.searchDepartures(roundTrip());

      final captured = verify(
        () => dio.get<dynamic>(
          ApiConstants.busItineraries,
          queryParameters: captureAny(named: 'queryParameters'),
        ),
      ).captured.single;

      expect(captured, {
        'from_physical_stop_uuid': '74703fce-3358-429d-9994-6b323664a419',
        'to_physical_stop_uuid': 'f2b528f4-3c99-461f-ac4e-a10f0721b388',
        'date': '2026-08-20',
        'passenger_count': 1,
        'trip_type': 'round_trip',
        'return_date': '2026-08-24',
      });
    });

    test('lit les deux sens, que le serveur nomme', () async {
      // Un aller-retour ne rend pas `{data}` mais `{outbound, inbound}` :
      // lire l'enveloppe habituelle rendrait deux listes vides.
      stubGet(roundTripResponse());

      final search = await dataSource.searchDepartures(roundTrip());

      expect(search.isRoundTrip, isTrue);
      expect(search.outbound, hasLength(1));
      expect(search.inbound, hasLength(1));
      expect(search.outbound.single.price.amount, 31600);
    });

    test('un retour sans départ reste un aller-retour, mais vide', () async {
      // Distinct d'un aller simple : l'étape de retour existe, et c'est
      // elle qui n'a rien à proposer. Le voyageur doit l'apprendre.
      stubGet(roundTripResponse(inbound: const []));

      final search = await dataSource.searchDepartures(roundTrip());

      expect(search.isRoundTrip, isTrue);
      expect(search.inbound, isEmpty);
      expect(search.isEmpty, isTrue);
    });

    test('sans date de retour, rien n’est déclaré au serveur', () async {
      // L'aller simple garde la requête vérifiée sur la staging : ni
      // `trip_type` ni `return_date`.
      stubGet(const {'data': <dynamic>[]});

      await dataSource.searchDepartures(criteria);

      final captured =
          verify(
                () => dio.get<dynamic>(
                  any(),
                  queryParameters: captureAny(named: 'queryParameters'),
                ),
              ).captured.single
              as Map<String, dynamic>;

      expect(captured.containsKey('trip_type'), isFalse);
      expect(captured.containsKey('return_date'), isFalse);
    });

    test('écarte les correspondances dans les deux sens', () async {
      final connecting = {
        ...kItinerary,
        'connections_count': 1,
        'legs': [kItineraryLeg, kItineraryLeg],
      };
      stubGet(
        roundTripResponse(
          outbound: [kItinerary, connecting],
          inbound: [connecting],
        ),
      );

      final search = await dataSource.searchDepartures(roundTrip());

      expect(search.outbound, hasLength(1));
      expect(search.inbound, isEmpty);
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
