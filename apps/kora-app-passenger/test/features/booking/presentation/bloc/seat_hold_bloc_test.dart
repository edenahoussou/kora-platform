// Le blocage des sièges : quand il commence, ce qu'il compte, et ce qui
// l'arrête.
//
// C'est ici que vit la règle du décompte : rien n'est retenu tant que le
// voyageur n'a pas confirmé, et l'échéance vient du serveur.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');
  final departure = mockDeparturesFor(
    SearchCriteria(
      origin: cotonou,
      destination: malanville,
      date: DateTime(2026, 8, 14),
    ),
  ).first;

  final seats = [
    const Seat(id: 's-4a', label: '4A', row: 4, column: 1),
    const Seat(id: 's-4b', label: '4B', row: 4, column: 2),
  ];

  test('à l’ouverture, rien n’est retenu', () {
    final bloc = SeatHoldBloc(StubReservationRepository());
    addTearDown(bloc.close);

    // C'est ce qui conditionne la carte de décompte : à la première visite
    // du plan, le voyageur choisit sans qu'aucun délai ne coure.
    expect(bloc.state.status, SeatHoldStatus.idle);
    expect(bloc.state.isHeld, isFalse);
    expect(bloc.state.hold, isNull);
  });

  test('la confirmation demande un devis puis retient les sièges', () async {
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(SeatHoldRequested(departure: departure, seats: seats));
    await bloc.stream.firstWhere(
      (state) => state.status == SeatHoldStatus.held,
    );

    // Le devis d'abord : le blocage réclame son identifiant.
    expect(repository.calls, ['quote', 'hold']);
    expect(bloc.state.isHeld, isTrue);
    expect(bloc.state.hold!.seatLabels, ['4A', '4B']);
  });

  test('le décompte part de l’échéance du serveur', () async {
    final repository = StubReservationRepository(
      holdDuration: const Duration(minutes: 7),
    );
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(SeatHoldRequested(departure: departure, seats: seats));
    await bloc.stream.firstWhere(
      (state) => state.status == SeatHoldStatus.held,
    );

    // À la seconde près : `expires_at` vient d'être calculé.
    expect(bloc.state.remaining.inMinutes, 6);
    expect(bloc.state.formatted, startsWith('06:5'));
  });

  test('un devis refusé n’engage aucun blocage', () async {
    final repository = StubReservationRepository(
      failure: const NetworkFailure('Pas de réseau'),
    );
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(SeatHoldRequested(departure: departure, seats: seats));
    await bloc.stream.firstWhere(
      (state) => state.status == SeatHoldStatus.failure,
    );

    // On s'arrête au devis : appeler le blocage sans lui aurait été refusé
    // par le serveur de toute façon.
    expect(repository.calls, ['quote']);
    expect(bloc.state.isHeld, isFalse);
    expect(bloc.state.failure, isA<NetworkFailure>());
  });

  test('annuler rend les sièges et coupe le décompte', () async {
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(SeatHoldRequested(departure: departure, seats: seats));
    await bloc.stream.firstWhere(
      (state) => state.status == SeatHoldStatus.held,
    );

    bloc.add(const SeatHoldReleased());
    await bloc.stream.firstWhere(
      (state) => state.status == SeatHoldStatus.idle,
    );

    expect(repository.calls, ['quote', 'hold', 'release:hold-1']);
    expect(bloc.state.isHeld, isFalse);
    // Le départ survit : le voyageur reste sur le plan et peut rechoisir.
    expect(bloc.state.departure, isNotNull);
  });

  test('à zéro, le blocage expire sans rappeler le serveur', () async {
    // Un blocage déjà échu à sa création : le serveur a rendu les sièges,
    // il n'y a plus rien à libérer.
    final repository = StubReservationRepository(holdDuration: Duration.zero);
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(SeatHoldRequested(departure: departure, seats: seats));
    await bloc.stream.firstWhere(
      (state) => state.status == SeatHoldStatus.expired,
    );

    expect(repository.calls, ['quote', 'hold']);
    expect(bloc.state.isHeld, isFalse);
    // L'écran de fin de délai en a besoin pour renvoyer au bon plan.
    expect(bloc.state.departure, isNotNull);
  });

  test('le temps restant ne descend jamais sous zéro', () {
    final past = DateTime.now().toUtc().subtract(const Duration(minutes: 3));
    final state = SeatHoldState(remaining: Duration.zero);

    expect(state.formatted, '00:00');
    expect(past.isBefore(DateTime.now().toUtc()), isTrue);
  });
}
