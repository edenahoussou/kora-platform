// Le blocage d'un aller-retour.
//
// Les deux trajets tiennent dans un seul devis et un seul blocage : le
// serveur réclame un siège pour **chaque tronçon** du devis
// (`CreateItineraryHoldAction`), et c'est ce qui donne au voyageur un
// paiement unique. Taire les sièges du retour ferait refuser le blocage
// entier.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  late Departure outbound;
  late Departure inbound;
  late List<Seat> outboundSeats;
  late List<Seat> inboundSeats;

  setUp(() {
    final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
    final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');

    outbound = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;

    inbound = mockDeparturesFor(
      SearchCriteria(
        origin: malanville,
        destination: cotonou,
        date: DateTime(2026, 8, 20),
      ),
    ).first;

    List<Seat> freeSeats(Departure departure) => mockSeatsFor(
      departure,
    ).where((seat) => seat.status == SeatStatus.free).take(2).toList();

    outboundSeats = freeSeats(outbound);
    inboundSeats = freeSeats(inbound);
  });

  test('demande un devis qui couvre les deux trajets', () async {
    // Un devis sur le seul aller ferait payer la moitié du voyage.
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(
      SeatHoldRequested(
        departure: outbound,
        seats: outboundSeats,
        returnDeparture: inbound,
        returnSeats: inboundSeats,
      ),
    );
    await bloc.stream.firstWhere((state) => state.isHeld);

    expect(repository.calls.first, 'groupQuote:2:aller-retour');
  });

  test('retient les sièges des deux sens en un seul blocage', () async {
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(
      SeatHoldRequested(
        departure: outbound,
        seats: outboundSeats,
        returnDeparture: inbound,
        returnSeats: inboundSeats,
      ),
    );
    await bloc.stream.firstWhere((state) => state.isHeld);

    expect(repository.calls, contains('holdGroup:2+2'));
    expect(repository.groupReturnSeats, inboundSeats);
  });

  test('un aller simple ne déclare aucun retour', () async {
    // La forme éprouvée en ligne : ni second tronçon au devis, ni sièges
    // de retour au blocage.
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(SeatHoldRequested(departure: outbound, seats: outboundSeats));
    await bloc.stream.firstWhere((state) => state.isHeld);

    expect(repository.calls.first, 'groupQuote:2');
    expect(repository.calls, contains('holdGroup:2'));
    expect(repository.groupReturnSeats, isNull);
  });

  test('le départ retenu reste l’aller', () async {
    // Les écrans suivants — formulaire, récapitulatif, billet — parlent du
    // voyage au départ de l'aller. Retenir le retour les ferait tous
    // mentir sur la date et l'heure.
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(
      SeatHoldRequested(
        departure: outbound,
        seats: outboundSeats,
        returnDeparture: inbound,
        returnSeats: inboundSeats,
      ),
    );
    await bloc.stream.firstWhere((state) => state.isHeld);

    expect(bloc.state.departure, outbound);
  });
}
