// Le blocage atomique d'un groupe.
//
// Ce qui distingue un groupe : un seul appel retient les N sièges, il
// s'appuie sur le devis déjà montré au voyageur, et il porte le payeur —
// qui n'est pas forcément l'un des voyageurs.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_payer.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_quote.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/itinerary.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/journey.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/passenger.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';

import '../../../../support/seat_hold_harness.dart';

/// Un voyageur par siège, dans l'ordre du plan.
List<Passenger> travellersFor(List<Seat> seats) => [
  for (final (index, seat) in seats.indexed)
    Passenger(
      firstName: 'Voyageur',
      lastName: 'N${index + 1}',
      phone: '+2290197421${index}0',
      seat: seat,
    ),
];

/// Le checkout terminé : blocage et voyageurs.
bool _done(SeatHoldState state) => state.isHeld && !state.isCheckingOut;

void main() {
  late Departure departure;
  late List<Seat> seats;

  const quote = GroupQuote(
    id: 'iq-1',
    subtotal: Price(62500),
    total: Price(56250),
    passengerCount: 5,
    discount: GroupDiscount(
      applied: true,
      threshold: 5,
      percentage: 10,
      amount: Price(6250),
    ),
  );

  setUp(() {
    departure = mockDeparturesFor(
      SearchCriteria(
        origin: kMockCities.firstWhere((city) => city.id == 'ctn'),
        destination: kMockCities.firstWhere((city) => city.id == 'mlv'),
        date: DateTime(2026, 8, 14),
      ),
    ).first;

    seats = mockSeatsFor(
      departure,
    ).where((seat) => seat.status == SeatStatus.free).take(5).toList();
  });

  test('retient les cinq sièges en un seul appel', () async {
    // Atomique : retenir la moitié d'un groupe laisserait une réservation
    // inutilisable et des places retirées de la vente pour rien.
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(
      SeatHoldCheckoutRequested(
        journey: Journey(
          outbound: Itinerary.direct(departure),
          seatsByLeg: [seats],
        ),
        quote: quote,
        passengers: travellersFor(seats),
      ),
    );
    await bloc.stream.firstWhere(_done);

    expect(repository.calls, ['holdGroup:5', 'passengers:5']);
    expect(bloc.state.hold!.seatLabels, hasLength(5));
    expect(bloc.state.passengers, 5);
  });

  test('ne redemande pas de devis', () async {
    // Le groupe l'a déjà obtenu, et c'est ce montant-là qu'on lui a montré.
    // En redemander un exposerait à le faire payer un prix qu'il n'a pas vu,
    // le barème pouvant bouger entre deux appels.
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(
      SeatHoldCheckoutRequested(
        journey: Journey(
          outbound: Itinerary.direct(departure),
          seatsByLeg: [seats],
        ),
        quote: quote,
        passengers: travellersFor(seats),
      ),
    );
    await bloc.stream.firstWhere(_done);

    expect(repository.calls, isNot(contains('quote')));
    expect(repository.calls, isNot(contains('groupQuote:5')));
  });

  test('reprend le total du devis, remise comprise', () async {
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(
      SeatHoldCheckoutRequested(
        journey: Journey(
          outbound: Itinerary.direct(departure),
          seatsByLeg: [seats],
        ),
        quote: quote,
        passengers: travellersFor(seats),
      ),
    );
    await bloc.stream.firstWhere(_done);

    expect(bloc.state.hold!.total, const Price(56250));
  });

  test('un paiement personnel ne déclare pas de payeur', () async {
    // `payer` est facultatif côté serveur : le taire vaut mieux qu'envoyer
    // un identifiant que l'app devine.
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(
      SeatHoldCheckoutRequested(
        journey: Journey(
          outbound: Itinerary.direct(departure),
          seatsByLeg: [seats],
        ),
        quote: quote,
        passengers: travellersFor(seats),
      ),
    );
    await bloc.stream.firstWhere(_done);

    expect(repository.groupPayer!.isCompany, isFalse);
    expect(repository.groupPayer!.toJson(), isNull);
  });

  test('un compte entreprise part comme payeur', () async {
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(
      SeatHoldCheckoutRequested(
        journey: Journey(
          outbound: Itinerary.direct(departure),
          seatsByLeg: [seats],
        ),
        quote: quote,
        passengers: travellersFor(seats),
        payer: const GroupPayer.company(accountId: 'ca-1', name: 'STB SARL'),
      ),
    );
    await bloc.stream.firstWhere(_done);

    expect(repository.groupPayer!.toJson(), {
      'type': 'company',
      'company_account_uuid': 'ca-1',
    });
  });

  test('un refus du serveur laisse le groupe sans blocage', () async {
    final bloc = SeatHoldBloc(
      StubReservationRepository(
        failure: const ServerFailure('Sièges indisponibles.'),
      ),
    );
    addTearDown(bloc.close);

    bloc.add(
      SeatHoldCheckoutRequested(
        journey: Journey(
          outbound: Itinerary.direct(departure),
          seatsByLeg: [seats],
        ),
        quote: quote,
        passengers: travellersFor(seats),
      ),
    );
    await bloc.stream.firstWhere(
      (state) => state.status == SeatHoldStatus.failure,
    );

    expect(bloc.state.hold, isNull);
    expect(bloc.state.failure?.message, 'Sièges indisponibles.');
  });
}
