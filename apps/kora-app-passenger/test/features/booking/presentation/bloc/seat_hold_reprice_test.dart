// Le code promo passe par le devis (MEZ-73).
//
// Avant le blocage — le cas normal, le récapitulatif précédant le
// paiement — c'est un simple devis, que le checkout bloque ensuite tel
// quel. Après le blocage — le voyageur revient du paiement — il faut
// libérer puis rebloquer les mêmes sièges.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/passenger.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  late StubReservationRepository repository;
  late SeatHoldBloc bloc;
  late Departure departure;
  late Passenger passenger;

  setUp(() {
    repository = StubReservationRepository();
    bloc = SeatHoldBloc(repository);
    addTearDown(bloc.close);

    final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
    final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');
    departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;
    final seat = mockSeatsFor(
      departure,
    ).firstWhere((seat) => seat.status == SeatStatus.free);
    passenger = Passenger(
      firstName: 'Awa',
      lastName: 'KORA',
      phone: '+229 01 97 42 18 60',
      seat: seat,
    );
  });

  Future<SeatHoldState> reprice({required String code}) async {
    bloc.add(
      SeatHoldRepriceRequested(
        departure: departure,
        passengers: [passenger],
        promoCode: code,
      ),
    );
    // Le passage par « en cours », puis le retour au repos.
    await bloc.stream.firstWhere((state) => state.isRepricing);
    return bloc.stream.firstWhere((state) => !state.isRepricing);
  }

  /// « Continuer vers le paiement », sur le devis affiché.
  Future<SeatHoldState> checkout() async {
    bloc.add(
      SeatHoldCheckoutRequested(
        departure: departure,
        passengers: [passenger],
        quote: bloc.state.quote,
      ),
    );
    await bloc.stream.firstWhere((state) => state.isCheckingOut);
    return bloc.stream.firstWhere((state) => !state.isCheckingOut);
  }

  group('avant le blocage', () {
    test('un code valide ne fait qu’un devis, sans rien bloquer', () async {
      final state = await reprice(code: ' test2000 ');

      // Le code part normalisé, et seul le devis part.
      expect(repository.calls, ['groupQuote:1:promo=TEST2000']);
      expect(state.hold, isNull);
      expect(state.quote?.promotion?.code, 'TEST2000');
      // Le prix vient du serveur : 12 500 moins 10 %.
      expect(state.quote?.total, const Price(11250));
      expect(state.promoError, isNull);
    });

    test('le checkout bloque ensuite ce devis remisé', () async {
      await reprice(code: 'TEST2000');
      repository.calls.clear();

      final state = await checkout();

      // Aucune libération : le code est arrivé avant le blocage.
      expect(repository.calls, ['holdGroup:1', 'passengers:1']);
      expect(state.isHeld, isTrue);
      expect(state.hold?.total, const Price(11250));
      expect(state.quote?.promotion?.code, 'TEST2000');
    });

    test('un code refusé laisse le prix tel quel', () async {
      repository.promoFailure = const ValidationFailure(
        'Ce code promotionnel est inconnu.',
      );

      final state = await reprice(code: 'NOPE');

      expect(repository.calls, ['groupQuote:1:promo=NOPE']);
      expect(state.quote, isNull);
      expect(state.promoError, 'Ce code promotionnel est inconnu.');
    });

    test('un champ vide ne touche à rien', () async {
      bloc.add(
        SeatHoldRepriceRequested(
          departure: departure,
          passengers: [passenger],
          promoCode: '   ',
        ),
      );
      await Future<void>.delayed(Duration.zero);

      expect(repository.calls, isEmpty);
      expect(bloc.state.isRepricing, isFalse);
    });
  });

  group('après le blocage', () {
    setUp(() async {
      await checkout();
      repository.calls.clear();
    });

    test('un code valide : devis remisé, puis on rebloque et on renvoie les '
        'voyageurs', () async {
      final state = await reprice(code: 'TEST2000');

      // Le devis d'abord, avant qu'on lâche quoi que ce soit.
      expect(repository.calls, [
        'groupQuote:1:promo=TEST2000',
        'release:hold-groupe-1',
        'holdGroup:1',
        'passengers:1',
      ]);
      expect(state.status, SeatHoldStatus.held);
      expect(state.quote?.promotion?.code, 'TEST2000');
      expect(state.quote?.total, const Price(11250));
    });

    test('un code refusé ne lâche pas le siège', () async {
      repository.promoFailure = const ValidationFailure(
        'Ce code promotionnel est inconnu.',
      );
      final before = bloc.state.hold;

      final state = await reprice(code: 'NOPE');

      // Seul le devis est parti : ni libération, ni nouveau blocage.
      expect(repository.calls, ['groupQuote:1:promo=NOPE']);
      expect(state.hold, before);
      expect(state.quote?.promotion, isNull);
      expect(state.promoError, 'Ce code promotionnel est inconnu.');
    });

    test(
      'un siège pris entre-temps fait perdre le blocage, et le dit',
      () async {
        repository.nextHoldFailures.add(
          const SeatUnavailableFailure('Le siège 5C vient d’être pris.'),
        );

        bloc.add(
          SeatHoldRepriceRequested(
            departure: departure,
            passengers: [passenger],
            promoCode: 'TEST2000',
          ),
        );
        final state = await bloc.stream.firstWhere(
          (state) => state.status == SeatHoldStatus.expired,
        );

        // L'écran de fin de délai prend le relais pour rechoisir un siège.
        expect(state.hold, isNull);
        expect(state.failure?.message, 'Le siège 5C vient d’être pris.');
      },
    );

    test(
      'une libération refusée garde la réservation au tarif normal',
      () async {
        repository.releaseFailure = const AuthFailure(
          'This action is unauthorized.',
        );
        final before = bloc.state.hold;

        final state = await reprice(code: 'TEST2000');

        // Pas de nouveau blocage : il serait refusé, l'ancien tenant encore
        // le siège, et le voyageur perdrait tout.
        expect(repository.calls, [
          'groupQuote:1:promo=TEST2000',
          'release:hold-groupe-1',
        ]);
        expect(state.status, SeatHoldStatus.held);
        expect(state.hold, before);
        expect(state.quote?.promotion, isNull);
        expect(state.promoUnavailable, isTrue);
      },
    );
  });
}
