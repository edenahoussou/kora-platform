// Le code promo sur le récapitulatif : saisi, appliqué, refusé, retiré — et
// le montant envoyé au paiement toujours celui du devis rebloqué.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/passenger.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/group_quote_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/summary_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_args.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  late StubReservationRepository repository;
  late SeatHoldBloc holdBloc;
  late Departure departure;
  late Seat seat;

  setUpAll(() async {
    await AppLocalization.preloadAll();
    await initializeDateFormatting(KoraDateFormats.locale);
  });

  setUp(() {
    repository = StubReservationRepository();
    holdBloc = SeatHoldBloc(repository);
    addTearDown(holdBloc.close);

    final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
    final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');
    departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;
    seat = mockSeatsFor(
      departure,
    ).firstWhere((seat) => seat.status == SeatStatus.free);
  });

  String money(int amount) => Price(amount).formatted;

  PaymentArgs? sentToPayment;

  Future<void> pumpSummary(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 1400 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    sentToPayment = null;

    // Le code s'applique à une réservation bloquée : on bloque d'abord. En
    // temps réel, pour que le décompte du blocage ne reste pas suspendu
    // dans le temps simulé du test.
    await tester.runAsync(() async {
      holdBloc.add(SeatHoldRequested(departure: departure, seats: [seat]));
      await holdBloc.stream.firstWhere((state) => state.hold != null);
    });

    final router = GoRouter(
      routes: [
        GoRoute(
          path: '/',
          builder: (context, state) => withSeatHold(
            SummaryPage(
              departure: departure,
              passengers: [
                Passenger(
                  firstName: 'Awa',
                  lastName: 'KORA',
                  phone: '+229 01 97 42 18 60',
                  seat: seat,
                ),
              ],
            ),
            bloc: holdBloc,
            groupQuote: GroupQuoteCubit(repository),
          ),
        ),
        GoRoute(
          path: AppRouter.paymentMethodPath,
          builder: (context, state) {
            sentToPayment = state.extra as PaymentArgs?;
            return const Scaffold(body: Text('paiement'));
          },
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      MaterialApp.router(
        theme: AppTheme.light,
        locale: const Locale('fr', 'FR'),
        supportedLocales: const [Locale('fr', 'FR')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        routerConfig: router,
      ),
    );
    await tester.pumpAndSettle();
  }

  /// Saisit et applique un code. Le recalcul (devis, libération, blocage,
  /// voyageurs) tourne en temps réel, comme le blocage initial.
  Future<void> applyCode(WidgetTester tester, String code) async {
    await tester.enterText(find.byType(TextField), code);
    await tester.tap(find.text('Appliquer le code'));
    await tester.pump();
    await tester.runAsync(
      () => holdBloc.stream.firstWhere((state) => !state.isRepricing),
    );
    await tester.pumpAndSettle();
  }

  Future<void> goToPayment(WidgetTester tester) async {
    await tester.tap(find.text('Continuer vers le paiement'));
    await tester.pumpAndSettle();
  }

  testWidgets('un code valide remise le total, et c’est lui qu’on paie', (
    tester,
  ) async {
    await pumpSummary(tester);

    await applyCode(tester, 'test2000');

    // La remise et le total viennent du devis rebloqué.
    expect(find.text('Code TEST2000 appliqué'), findsOneWidget);
    expect(find.text('Code promo TEST2000'), findsOneWidget);
    expect(find.text('−${money(1250)}'), findsOneWidget);
    expect(find.text(money(11250)), findsOneWidget);
    expect(repository.calls, contains('groupQuote:1:promo=TEST2000'));

    await goToPayment(tester);
    expect(sentToPayment?.total, const Price(11250));
  });

  testWidgets('un code refusé le dit, et le tarif normal reste payable', (
    tester,
  ) async {
    repository.promoFailure = const ValidationFailure(
      'Ce code promotionnel est inconnu.',
    );
    await pumpSummary(tester);

    await applyCode(tester, 'NOPE');

    expect(find.text('Ce code promotionnel est inconnu.'), findsOneWidget);
    expect(find.text('Vous pouvez continuer au tarif normal.'), findsOneWidget);
    // Le siège n'a jamais été lâché.
    expect(repository.calls, isNot(contains(startsWith('release:'))));

    await goToPayment(tester);
    expect(sentToPayment?.total, const Price(12500));
  });

  testWidgets('retirer le code rend le tarif normal', (tester) async {
    await pumpSummary(tester);
    await applyCode(tester, 'TEST2000');

    await tester.tap(find.text('Retirer'));
    await tester.pump();
    await tester.runAsync(
      () => holdBloc.stream.firstWhere((state) => !state.isRepricing),
    );
    await tester.pumpAndSettle();

    expect(find.text('Code TEST2000 appliqué'), findsNothing);
    expect(find.text('Code promo TEST2000'), findsNothing);

    await goToPayment(tester);
    expect(sentToPayment?.total, const Price(12500));
  });
}
