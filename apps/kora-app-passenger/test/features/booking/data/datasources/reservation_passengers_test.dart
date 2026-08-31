// Ce que l'app envoie vraiment à `POST bookings/{uuid}/passengers`.
//
// Le contrat se casse en silence : un champ mal nommé, une date au mauvais
// format, et le serveur répond 422 sans que rien ne l'ait annoncé côté
// app. Ce test lit la requête telle qu'elle part.

import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/reservation_remote_data_source.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/passenger.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';

/// Retient la dernière requête et rend une réservation rafraîchie.
class _RecordingAdapter implements HttpClientAdapter {
  RequestOptions? request;
  Object? body;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    request = options;
    body = options.data;

    return ResponseBody.fromString(
      '{"data":{"uuid":"res-1","status":"held",'
      '"expires_at":"2026-08-20T08:15:00Z","total_amount":25000,'
      '"currency":"XOF","passengers":['
      '{"seat_code":"4A"},{"seat_code":"4B"}]}}',
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  late _RecordingAdapter adapter;
  late ReservationRemoteDataSource dataSource;

  const seatA = Seat(id: 'uuid-4a', label: '4A', row: 4, column: 1);
  const seatB = Seat(id: 'uuid-4b', label: '4B', row: 4, column: 2);

  setUp(() {
    adapter = _RecordingAdapter();
    dataSource = ReservationRemoteDataSource(
      Dio()..httpClientAdapter = adapter,
    );
  });

  List<Map<String, dynamic>> sentPassengers() =>
      ((adapter.body! as Map)['passengers'] as List)
          .cast<Map<String, dynamic>>();

  test('frappe la bonne route, avec l’identifiant de réservation', () async {
    await dataSource.savePassengers(
      reservationId: 'res-1',
      passengers: [
        const Passenger(
          firstName: 'Aïcha',
          lastName: 'HOUNKPÈ',
          phone: '+22901974218',
          seat: seatA,
        ),
      ],
    );

    expect(adapter.request!.method, 'POST');
    expect(adapter.request!.path, '/api/v1/bookings/res-1/passengers');
  });

  test('rattache chaque voyageur à son siège', () async {
    await dataSource.savePassengers(
      reservationId: 'res-1',
      passengers: [
        const Passenger(
          firstName: 'Aïcha',
          lastName: 'HOUNKPÈ',
          phone: '+22901974218',
          seat: seatA,
        ),
        const Passenger(
          firstName: 'Kofi',
          lastName: 'MENSAH',
          phone: '+22890000000',
          seat: seatB,
        ),
      ],
    );

    // `seat_uuid`, pas le libellé : c'est l'identifiant que connaît le
    // serveur.
    expect(sentPassengers().map((p) => p['seat_uuid']), ['uuid-4a', 'uuid-4b']);
  });

  test('envoie le manifeste complet d’un voyageur international', () async {
    await dataSource.savePassengers(
      reservationId: 'res-1',
      passengers: [
        Passenger(
          firstName: 'Kofi',
          lastName: 'MENSAH',
          phone: '+22890000000',
          seat: seatB,
          email: 'kofi@example.com',
          documentType: TravelDocumentType.consularCard,
          documentNumber: 'BJ0427851',
          nationality: 'TG',
          birthDate: DateTime(1994, 4, 18),
        ),
      ],
    );

    final sent = sentPassengers().single;

    expect(sent['first_name'], 'Kofi');
    expect(sent['email'], 'kofi@example.com');
    // La valeur du serveur, pas le nom Dart.
    expect(sent['document_type'], 'consular_card');
    expect(sent['nationality'], 'TG');
    // `date_format:Y-m-d` : une date seule, jamais un ISO complet.
    expect(sent['birth_date'], '1994-04-18');
  });

  test('tait les champs du manifeste sur une liaison intérieure', () async {
    // Envoyer `null` là où le serveur accepte l'absence ferait passer un
    // formulaire national pour une saisie incomplète.
    await dataSource.savePassengers(
      reservationId: 'res-1',
      passengers: [
        const Passenger(
          firstName: 'Aïcha',
          lastName: 'HOUNKPÈ',
          phone: '+22901974218',
          seat: seatA,
        ),
      ],
    );

    final sent = sentPassengers().single;

    expect(sent.containsKey('document_type'), isFalse);
    expect(sent.containsKey('nationality'), isFalse);
    expect(sent.containsKey('birth_date'), isFalse);
    expect(sent.containsKey('email'), isFalse);
  });

  test('porte la déclaration de vaccination de chaque voyageur', () async {
    // Revue Kora du 29 août 2026 : la case est indépendante et propre à
    // chaque voyageur. Deux personnes du même billet n'ont pas le même
    // carnet, et la réponse du premier ne doit rien décider pour le
    // second.
    await dataSource.savePassengers(
      reservationId: 'res-1',
      passengers: [
        const Passenger(
          firstName: 'Aïcha',
          lastName: 'HOUNKPÈ',
          phone: '+22901974218',
          seat: seatA,
        ),
        const Passenger(
          firstName: 'Kofi',
          lastName: 'MENSAH',
          phone: '+22890000000',
          seat: seatB,
          yellowFeverVaccinated: true,
        ),
      ],
    );

    expect(sentPassengers().map((p) => p['yellow_fever_vaccinated']), [
      false,
      true,
    ]);
  });

  test('envoie « non vacciné » plutôt que de taire la question', () async {
    // Contrairement aux champs du manifeste, celui-ci part toujours : une
    // absence se lirait « pas encore demandé », alors que le voyageur a
    // répondu.
    await dataSource.savePassengers(
      reservationId: 'res-1',
      passengers: [
        const Passenger(
          firstName: 'Aïcha',
          lastName: 'HOUNKPÈ',
          phone: '+22901974218',
          seat: seatA,
        ),
      ],
    );

    expect(
      sentPassengers().single.containsKey('yellow_fever_vaccinated'),
      isTrue,
    );
  });

  test('tait un document laissé vide, sans le remplacer', () async {
    // Type et numéro sont facultatifs depuis la revue Kora : le voyageur
    // dont la pièce est expirée passe sans, et le serveur ne doit pas
    // recevoir une pièce déclarée sans référence.
    await dataSource.savePassengers(
      reservationId: 'res-1',
      passengers: [
        Passenger(
          firstName: 'Kofi',
          lastName: 'MENSAH',
          phone: '+22890000000',
          seat: seatB,
          nationality: 'TG',
          birthDate: DateTime(1994, 4, 18),
        ),
      ],
    );

    final sent = sentPassengers().single;

    expect(sent.containsKey('document_type'), isFalse);
    expect(sent.containsKey('document_number'), isFalse);
    // Le reste du manifeste part quand même.
    expect(sent['nationality'], 'TG');
    expect(sent['birth_date'], '1994-04-18');
  });

  test('rend la réservation rafraîchie par le serveur', () async {
    final hold = await dataSource.savePassengers(
      reservationId: 'res-1',
      passengers: [
        const Passenger(
          firstName: 'Aïcha',
          lastName: 'HOUNKPÈ',
          phone: '+22901974218',
          seat: seatA,
        ),
      ],
    );

    // `expires_at` vient de la réponse : c'est le serveur qui fait
    // autorité sur le temps restant.
    expect(hold.id, 'res-1');
    expect(hold.expiresAt, DateTime.utc(2026, 8, 20, 8, 15));
    // Les sièges sont nommés par `passengers`, pas par `segments`, sur ce
    // point d'entrée.
    expect(hold.seatLabels, ['4A', '4B']);
  });
}
