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
import 'package:kora_app_passenger/features/booking/domain/entities/ticket.dart';
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
        // Rendues dans l'ordre alphabétique, pas dans celui de l'API, et
        // une seule fois chacune — même Cotonou, que le catalogue
        // dessert par plusieurs arrêts.
        'Abidjan',
        'Bohicon',
        'Cotonou',
      ]);
      expect(cities.first.id, 'Abidjan');
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
        // La ville, pas l'arrêt : c'est le serveur qui explore les points
        // d'arrêt, et c'est ce qui rend une correspondance trouvable
        // entre deux lignes qui ne s'arrêtent pas au même endroit.
        'from_city': 'Cotonou',
        'to_city': 'Abidjan',
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

    test('une ville relue d’un billet se cherche comme les autres', () async {
      // Elle n'a que son nom, et c'est tout ce qu'il faut désormais :
      // avant, faute d'arrêt physique, « Repartir » menait à une erreur.
      stubGet(const {'data': <dynamic>[]});

      await dataSource.searchDepartures(
        SearchCriteria(
          origin: City.named('Cotonou'),
          destination: abidjan,
          date: DateTime(2026, 8, 20),
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

      expect(captured['from_city'], 'Cotonou');
    });

    test('une ville sans nom n’est jamais envoyée', () async {
      // Le serveur répondrait 422. Échouer ici donne un message que
      // l'écran sait afficher, plutôt qu'une erreur de validation brute.
      await expectLater(
        dataSource.searchDepartures(
          SearchCriteria(
            origin: City.named(''),
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
      expect(
        search.outbound.single.first.id,
        '56f674c2-7795-4427-9839-ff5de7345ade',
      );
      expect(search.outbound.single.total.amount, 31600);
      // Aucun retour demandé : il n'y a pas d'étape de retour du tout.
      expect(search.inbound, isNull);
      expect(search.isRoundTrip, isFalse);
    });

    test('aucun itinéraire n’est un résultat, pas une erreur', () async {
      stubGet(const {'data': <dynamic>[]});

      expect((await dataSource.searchDepartures(criteria)).outbound, isEmpty);
    });

    test('rend les correspondances avec les directs', () async {
      // Le serveur rend les deux dans la même liste. Les écarter — ce que
      // faisait l'app — répondait « Aucun voyage ce jour-là » sur une
      // liaison qu'il savait composer.
      stubGet({
        'data': [kItinerary, kConnectedItinerary],
      });

      final outbound = (await dataSource.searchDepartures(criteria)).outbound;

      expect(outbound, hasLength(2));
      expect(outbound.first.isDirect, isTrue);
      expect(outbound.last.connectionsCount, 1);
      expect(outbound.last.legs, hasLength(2));
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
        'from_city': 'Cotonou',
        'to_city': 'Abidjan',
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
      expect(search.outbound.single.total.amount, 31600);
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

    test('un aller-retour sans date déclare quand même son type', () async {
      // Le serveur accepte `trip_type` seul : il rend alors les retours
      // des jours qui suivent. Ne l'envoyer qu'avec une date ramenait la
      // recherche à un aller simple, et jetait les retours rendus.
      stubGet(roundTripResponse());

      await dataSource.searchDepartures(
        SearchCriteria(
          origin: cotonou,
          destination: abidjan,
          date: DateTime(2026, 8, 20),
          roundTrip: true,
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

      expect(captured['trip_type'], 'round_trip');
      expect(captured.containsKey('return_date'), isFalse);
    });

    test('un aller-retour sans date lit bien les deux sens', () async {
      // Le piège : la forme de la réponse suit `trip_type`, pas la date.
      // S'y fier rendait « aucun départ » alors que le serveur avait
      // renvoyé les allers sous `outbound`.
      stubGet(roundTripResponse());

      final search = await dataSource.searchDepartures(
        SearchCriteria(
          origin: cotonou,
          destination: abidjan,
          date: DateTime(2026, 8, 20),
          roundTrip: true,
        ),
      );

      expect(search.outbound, hasLength(1));
      expect(search.inbound, hasLength(1));
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

    test('les correspondances passent dans les deux sens', () async {
      stubGet(
        roundTripResponse(
          outbound: [kItinerary, kConnectedItinerary],
          inbound: [kConnectedItinerary],
        ),
      );

      final search = await dataSource.searchDepartures(roundTrip());

      expect(search.outbound, hasLength(2));
      // Un retour à correspondance se réserve comme un autre : c'est le
      // même devis, le même blocage et le même paiement.
      expect(search.inbound, hasLength(1));
      expect(search.inbound!.single.isDirect, isFalse);
    });
  });

  group('getTickets', () {
    /// Un billet tel que `GET /api/v1/tickets` le rend.
    Map<String, dynamic> ticketJson({
      required String uuid,
      String? reservation = 'res-1',
      String status = 'confirmed',
      String seat = '4A',
      String departsAt = '2099-08-04T07:30:00+00:00',
    }) => {
      'uuid': uuid,
      'short_code': 'K${uuid.toUpperCase()}',
      'qr_code': 'payload.$uuid',
      'status': status,
      'issued_at': '2026-08-01T10:00:00+00:00',
      'reservation_uuid': reservation,
      'amount': 25200,
      'currency': 'XOF',
      'passenger': {
        'uuid': 'p-$uuid',
        'first_name': 'Aïcha',
        'last_name': 'HOUNKPÈ',
      },
      'origin': {
        'city': 'Cotonou',
        'city_code': 'CTN',
        'stop_name': 'Cotonou Dantokpa',
      },
      'destination': {
        'city': 'Bohicon',
        'city_code': 'BOH',
        'stop_name': 'Bohicon Centre',
      },
      'departure': {
        'uuid': 'dep-1',
        'departs_at': departsAt,
        'line_name': 'Cotonou-Bohicon',
        'line_code': 'L1',
      },
      'seat': {'uuid': 'seat-$seat', 'code': seat},
    };

    Map<String, dynamic> page(
      List<Map<String, dynamic>> data, {
      int current = 1,
      int last = 1,
    }) => {
      'data': data,
      'meta': {
        'current_page': current,
        'last_page': last,
        'per_page': 15,
        'total': data.length,
      },
    };

    test('lit l’endpoint du serveur, jamais le jeu de démonstration', () async {
      stubGet(page([ticketJson(uuid: 't1')]));

      await dataSource.getTickets();

      final captured = verify(
        () => dio.get<dynamic>(
          ApiConstants.tickets,
          queryParameters: captureAny(named: 'queryParameters'),
        ),
      ).captured;
      expect(captured.single, {'page': 1});
      verifyNever(() => pending.getTickets());
    });

    test('un voyage par réservation, ses sièges bout à bout', () async {
      // Le serveur rend un billet par voyageur. Sans regroupement, un
      // groupe de cinq remplissait l'onglet de cinq cartes identiques.
      stubGet(
        page([
          ticketJson(uuid: 't1', seat: '4A'),
          ticketJson(uuid: 't2', seat: '4B'),
          ticketJson(uuid: 't3', reservation: 'res-2', seat: '7C'),
        ]),
      );

      final tickets = await dataSource.getTickets();

      expect(tickets, hasLength(2));
      expect(tickets.first.seat.label, '4A, 4B');
      expect(tickets.first.reservationId, 'res-1');
      // Le code imprimé sur le billet, pas l'identifiant de réservation.
      expect(tickets.first.reference, 'KT1');
      // La charge signée, que la douchette vérifie hors ligne.
      expect(tickets.first.qrCode, 'payload.t1');
      expect(tickets.first.departure.line.origin.code, 'CTN');
      expect(
        tickets.first.departure.line.destination.station,
        'Bohicon Centre',
      );
      expect(tickets.first.departure.price.amount, 25200);
      expect(tickets.last.seat.label, '7C');
    });

    test('toutes les pages sont lues', () async {
      // Quinze billets par page : un voyage à venir peut dormir en page
      // deux derrière des billets récemment émis.
      when(
        () => dio.get<dynamic>(
          ApiConstants.tickets,
          queryParameters: any(named: 'queryParameters'),
        ),
      ).thenAnswer((invocation) async {
        final query = invocation.namedArguments[#queryParameters] as Map;
        return responseWith(
          query['page'] == 1
              ? page([ticketJson(uuid: 't1')], last: 2)
              : page(
                  [ticketJson(uuid: 't2', reservation: 'res-2')],
                  current: 2,
                  last: 2,
                ),
        );
      });

      final tickets = await dataSource.getTickets();

      expect(tickets.map((ticket) => ticket.reservationId).toList(), [
        'res-1',
        'res-2',
      ]);
    });

    test('un départ passé ne reste pas « à venir »', () async {
      // Le serveur laisse `issued` — donc `confirmed` — un billet que
      // personne n'a scanné. Ce voyage-là n'est plus à venir.
      stubGet(
        page([
          ticketJson(uuid: 't1', departsAt: '2020-01-01T07:30:00+00:00'),
          ticketJson(
            uuid: 't2',
            reservation: 'res-2',
            status: 'cancelled',
            departsAt: '2020-01-01T07:30:00+00:00',
          ),
          ticketJson(uuid: 't3', reservation: 'res-3'),
        ]),
      );

      final tickets = await dataSource.getTickets();

      expect(tickets.map((ticket) => ticket.status).toList(), [
        TicketStatus.completed,
        TicketStatus.cancelled,
        TicketStatus.confirmed,
      ]);
    });

    test('le prochain voyage est le départ confirmé le plus proche', () async {
      stubGet(
        page([
          ticketJson(uuid: 't1', departsAt: '2099-09-01T07:30:00+00:00'),
          ticketJson(
            uuid: 't2',
            reservation: 'res-2',
            seat: '7C',
            departsAt: '2099-08-04T07:30:00+00:00',
          ),
          // Passé, puis annulé : ni l'un ni l'autre n'est « à venir ».
          ticketJson(
            uuid: 't3',
            reservation: 'res-3',
            departsAt: '2020-01-01T07:30:00+00:00',
          ),
          ticketJson(
            uuid: 't4',
            reservation: 'res-4',
            status: 'cancelled',
            departsAt: '2099-08-01T07:30:00+00:00',
          ),
        ]),
      );

      final trip = await dataSource.getNextTrip();

      expect(trip, isNotNull);
      expect(trip!.seat.label, '7C');
      expect(
        trip.departure.departsAt,
        DateTime.parse('2099-08-04T07:30:00+00:00').toLocal(),
      );
      verifyNever(() => pending.getNextTrip());
    });

    test('aucun voyage à venir : pas de carte de démonstration', () async {
      stubGet(
        page([ticketJson(uuid: 't1', departsAt: '2020-01-01T07:30:00+00:00')]),
      );

      expect(await dataSource.getNextTrip(), isNull);
      verifyNever(() => pending.getNextTrip());
    });

    test('un voyageur annulé ne fait pas un voyage annulé', () async {
      stubGet(
        page([
          ticketJson(uuid: 't1', status: 'cancelled'),
          ticketJson(uuid: 't2'),
        ]),
      );

      final tickets = await dataSource.getTickets();

      expect(tickets.single.status, TicketStatus.confirmed);
    });
  });

  group('écrans pas encore servis par l’API', () {
    test('les recherches récentes sont vides, jamais mockées', () async {
      expect(await dataSource.getRecentCities(), isEmpty);
      verifyNever(() => pending.getRecentCities());
    });
  });
}
