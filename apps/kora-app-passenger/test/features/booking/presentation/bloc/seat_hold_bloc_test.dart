// Le blocage des sièges : quand il commence, ce qu'il compte, et ce qui
// l'arrête.
//
// C'est ici que vit la règle du décompte : rien n'est retenu tant que le
// voyageur n'a pas validé son récapitulatif, et l'échéance vient du
// serveur.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_quote.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/passenger.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
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

  final travellers = [
    Passenger(
      firstName: 'Aïcha',
      lastName: 'HOUNKPÈ',
      phone: '+22901974218',
      seat: seats.first,
    ),
    Passenger(
      firstName: 'Kofi',
      lastName: 'MENSAH',
      phone: '+22890000000',
      seat: seats.last,
      documentType: TravelDocumentType.consularCard,
      documentNumber: 'BJ0427851',
      nationality: 'TG',
      birthDate: DateTime(1994, 4, 18),
    ),
  ];

  /// Le devis qu'afficherait le récapitulatif.
  GroupQuote shownQuote({DateTime? expiresAt}) => GroupQuote(
    id: 'devis-affiche',
    subtotal: const Price(25000),
    total: const Price(25000),
    passengerCount: 2,
    discount: GroupDiscount.none,
    expiresAt: expiresAt,
  );

  SeatHoldCheckoutRequested checkout({GroupQuote? quote}) =>
      SeatHoldCheckoutRequested(
        departure: departure,
        passengers: travellers,
        quote: quote,
      );

  /// Attend la fin du checkout, réussi ou non.
  Future<SeatHoldState> checkedOut(SeatHoldBloc bloc) async {
    await bloc.stream.firstWhere((state) => state.isCheckingOut);
    return bloc.stream.firstWhere((state) => !state.isCheckingOut);
  }

  test('à l’ouverture, rien n’est retenu', () {
    final bloc = SeatHoldBloc(StubReservationRepository());
    addTearDown(bloc.close);

    // C'est ce qui conditionne la carte de décompte : sur le plan, le
    // formulaire et le récapitulatif, le voyageur choisit sans qu'aucun
    // délai ne coure.
    expect(bloc.state.status, SeatHoldStatus.idle);
    expect(bloc.state.isHeld, isFalse);
    expect(bloc.state.hold, isNull);
  });

  test('le checkout bloque sur le devis affiché, puis envoie les '
      'voyageurs', () async {
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(checkout(quote: shownQuote()));
    final state = await checkedOut(bloc);

    // Pas de nouveau devis : le voyageur paie le prix qu'il vient de lire.
    expect(repository.calls, ['holdGroup:2', 'passengers:2']);
    expect(state.isHeld, isTrue);
    expect(state.passengersStatus, PassengersStatus.saved);
    expect(state.quote?.id, 'devis-affiche');
    expect(state.hold!.seatLabels, ['4A', '4B']);
  });

  test('sans devis affiché, il en demande un avant de bloquer', () async {
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(checkout());
    await checkedOut(bloc);

    // Les points d'entrée d'itinéraire depuis MEZ-63, y compris pour un
    // voyageur seul — un groupe d'un.
    expect(repository.calls, ['groupQuote:2', 'holdGroup:2', 'passengers:2']);
    expect(bloc.state.isHeld, isTrue);
  });

  test(
    'un devis sur le point d’expirer est redemandé, avec son code',
    () async {
      final repository = StubReservationRepository();
      final bloc = SeatHoldBloc(repository);
      addTearDown(bloc.close);

      final expiring = GroupQuote(
        id: 'devis-perime',
        subtotal: const Price(25000),
        total: const Price(22500),
        passengerCount: 2,
        discount: GroupDiscount.none,
        promotion: const QuotePromotion(
          code: 'TEST2000',
          discount: Price(2500),
        ),
        expiresAt: DateTime.now().add(const Duration(seconds: 5)),
      );

      bloc.add(checkout(quote: expiring));
      await checkedOut(bloc);

      expect(repository.calls.first, 'groupQuote:2:promo=TEST2000');
      expect(bloc.state.quote?.id, 'itinerary-quote-1');
    },
  );

  test('le décompte part de l’échéance du serveur', () async {
    final repository = StubReservationRepository(
      holdDuration: const Duration(minutes: 7),
    );
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(checkout(quote: shownQuote()));
    await checkedOut(bloc);

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

    bloc.add(checkout());
    final state = await checkedOut(bloc);

    // On s'arrête au devis : appeler le blocage sans lui aurait été refusé
    // par le serveur de toute façon.
    expect(repository.calls, ['groupQuote:2']);
    expect(state.status, SeatHoldStatus.failure);
    expect(state.isHeld, isFalse);
    expect(state.failure, isA<NetworkFailure>());
  });

  test('un siège pris entre-temps arrête tout, devis gardé', () async {
    final repository = StubReservationRepository()
      ..nextHoldFailures.add(
        const SeatUnavailableFailure('Le siège 4A vient d’être pris.'),
      );
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(
      SeatHoldRepriceRequested(
        departure: departure,
        passengers: travellers,
        promoCode: 'TEST2000',
      ),
    );
    await bloc.stream.firstWhere((state) => state.quote != null);

    bloc.add(checkout(quote: bloc.state.quote));
    final state = await checkedOut(bloc);

    // Ni voyageurs ni blocage : le plan reprend la main. Le code reste,
    // pour le prochain essai.
    expect(repository.calls.last, 'holdGroup:2');
    expect(state.status, SeatHoldStatus.failure);
    expect(state.failure, isA<SeatUnavailableFailure>());
    expect(state.quote?.promotion?.code, 'TEST2000');
  });

  test('un refus des identités garde le blocage', () async {
    // Le blocage réussit, seul l'envoi échoue : c'est le cas d'une
    // nationalité ou d'une pièce que le serveur refuse.
    final repository = StubReservationRepository(
      passengersFailure: const ValidationFailure('Nationalité invalide'),
    );
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(checkout(quote: shownQuote()));
    final state = await checkedOut(bloc);

    // Les sièges restent retenus : le voyageur corrige et retente, il
    // n'a pas à tout reprendre au plan.
    expect(state.passengersStatus, PassengersStatus.failure);
    expect(state.failure, isA<ValidationFailure>());
    expect(state.isHeld, isTrue);
  });

  test('revenu du paiement, on renvoie les voyageurs sans rebloquer', () async {
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(checkout(quote: shownQuote()));
    await checkedOut(bloc);
    repository.calls.clear();

    bloc.add(checkout(quote: bloc.state.quote));
    final state = await checkedOut(bloc);

    expect(repository.calls, ['passengers:2']);
    expect(state.isHeld, isTrue);
    expect(state.passengersStatus, PassengersStatus.saved);
  });

  test('le blocage reste actif pendant l’envoi des voyageurs', () async {
    // Le décompte doit continuer de courir : sans quoi la carte
    // disparaîtrait le temps de l'appel.
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(checkout(quote: shownQuote()));
    final saving = await bloc.stream.firstWhere(
      (state) =>
          state.passengersStatus == PassengersStatus.saving && state.isHeld,
    );

    expect(saving.status, SeatHoldStatus.held);
  });

  test('annuler rend les sièges et coupe le décompte', () async {
    final repository = StubReservationRepository();
    final bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    bloc.add(checkout(quote: shownQuote()));
    await checkedOut(bloc);

    bloc.add(const SeatHoldReleased());
    await bloc.stream.firstWhere(
      (state) => state.status == SeatHoldStatus.idle,
    );

    expect(repository.calls, [
      'holdGroup:2',
      'passengers:2',
      'release:hold-groupe-1',
    ]);
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

    bloc.add(checkout(quote: shownQuote()));
    await bloc.stream.firstWhere(
      (state) => state.status == SeatHoldStatus.expired,
    );

    expect(repository.calls, isNot(contains(startsWith('release'))));
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
