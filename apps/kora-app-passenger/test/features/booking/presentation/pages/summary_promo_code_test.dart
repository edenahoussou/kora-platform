// Le code promo sur le récapitulatif : saisi, appliqué, refusé, remplacé — et
// « Continuer vers le paiement » qui bloque les sièges sur le devis affiché.

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

  /// Le récapitulatif, ouvert depuis un plan des sièges : c'est là qu'il
  /// ramène quand le siège vient d'être pris.
  Future<void> pumpSummary(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 1400 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    sentToPayment = null;

    final router = GoRouter(
      initialLocation: AppRouter.seatsPath,
      routes: [
        GoRoute(
          path: AppRouter.seatsPath,
          builder: (context, state) => Scaffold(
            body: TextButton(
              onPressed: () => context.push(AppRouter.summaryPath),
              child: const Text('plan'),
            ),
          ),
        ),
        GoRoute(
          path: AppRouter.summaryPath,
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
    await tester.tap(find.text('plan'));
    await tester.pumpAndSettle();
  }

  /// Laisse le Bloc finir en temps réel : ses appels et son décompte
  /// tournent hors du temps simulé du test.
  Future<void> settle(WidgetTester tester) async {
    await tester.pump();
    await tester.runAsync(() async {
      for (var i = 0; i < 100; i++) {
        final state = holdBloc.state;
        if (!state.isRepricing && !state.isCheckingOut) break;
        await Future<void>.delayed(const Duration(milliseconds: 10));
      }
    });
    await tester.pumpAndSettle();
  }

  Future<void> applyCode(WidgetTester tester, String code) async {
    await tester.enterText(find.byType(TextField), code);
    // Le bouton suit la saisie : il faut le laisser se redessiner.
    await tester.pump();
    await tester.tap(find.text('Appliquer le code'));
    await settle(tester);
  }

  Future<void> goToPayment(WidgetTester tester) async {
    await tester.tap(find.text('En ligne'));
    await settle(tester);
  }

  testWidgets('un code valide remise le total, et c’est lui qu’on bloque puis '
      'paie', (tester) async {
    await pumpSummary(tester);

    await applyCode(tester, 'test2000');

    // La remise et le total viennent du devis, sans rien bloquer encore.
    expect(find.text('Code TEST2000 appliqué'), findsOneWidget);
    expect(find.text('Code promo TEST2000'), findsOneWidget);
    expect(find.text('−${money(1250)}'), findsOneWidget);
    expect(find.text(money(11250)), findsOneWidget);
    expect(repository.calls, contains('groupQuote:1:promo=TEST2000'));
    expect(repository.calls, isNot(contains('holdGroup:1')));

    await goToPayment(tester);

    // Bloqué sur le devis remisé, sans libération ni nouveau devis.
    expect(repository.calls.sublist(repository.calls.length - 2), [
      'holdGroup:1',
      'passengers:1',
    ]);
    expect(repository.calls, isNot(contains(startsWith('release:'))));
    expect(holdBloc.state.hold?.total, const Price(11250));
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

    await goToPayment(tester);
    expect(sentToPayment?.total, const Price(12500));
  });

  testWidgets('un second code refusé garde le premier, et le dit', (
    tester,
  ) async {
    await pumpSummary(tester);
    await applyCode(tester, 'TEST2000');

    repository.promoFailure = const ValidationFailure(
      'Ce code promotionnel ne s’applique pas à cet itinéraire.',
    );
    await applyCode(tester, 'DINO2000');

    expect(
      find.text('Ce code promotionnel ne s’applique pas à cet itinéraire.'),
      findsOneWidget,
    );
    expect(find.text('Le code TEST2000 reste appliqué.'), findsOneWidget);
    expect(find.text('Vous pouvez continuer au tarif normal.'), findsNothing);
    expect(find.text(money(11250)), findsOneWidget);
  });

  testWidgets('le code appliqué se retire, et le tarif normal revient', (
    tester,
  ) async {
    await pumpSummary(tester);
    await applyCode(tester, 'TEST2000');
    expect(find.text(money(11250)), findsOneWidget);

    await tester.tap(find.text('Retirer'));
    await settle(tester);

    // Un devis sans code : plus de ligne de remise, et le tarif normal.
    expect(repository.calls.last, 'groupQuote:1');
    expect(find.text('Code TEST2000 appliqué'), findsNothing);
    expect(find.text('Code promo TEST2000'), findsNothing);
    expect(find.text(money(12500)), findsWidgets);
  });

  testWidgets('le code appliqué grise le bouton, un autre code le rend', (
    tester,
  ) async {
    await pumpSummary(tester);
    await applyCode(tester, 'TEST2000');

    KoraButton applyButton() => tester.widget<KoraButton>(
      find.widgetWithText(KoraButton, 'Appliquer le code'),
    );

    // Le champ reprend le code appliqué : un second appui ne ferait rien.
    expect(applyButton().onPressed, isNull);

    await tester.enterText(find.byType(TextField), 'DINO2000');
    await tester.pump();
    expect(applyButton().onPressed, isNotNull);

    // Revenir au code appliqué, même en minuscules, regrise le bouton.
    await tester.enterText(find.byType(TextField), 'test2000');
    await tester.pump();
    expect(applyButton().onPressed, isNull);
  });

  testWidgets('un siège pris entre-temps ramène au plan', (tester) async {
    repository.nextHoldFailures.add(
      const SeatUnavailableFailure('Le siège 4A vient d’être pris.'),
    );
    await pumpSummary(tester);

    await goToPayment(tester);

    expect(sentToPayment, isNull);
    expect(find.text('plan'), findsOneWidget);
    expect(holdBloc.state.isHeld, isFalse);
  });

  testWidgets('un autre refus laisse sur le récapitulatif', (tester) async {
    repository.nextHoldFailures.add(
      const ServerFailure('La vente est fermée pour ce départ.'),
    );
    await pumpSummary(tester);

    await goToPayment(tester);

    expect(sentToPayment, isNull);
    expect(find.text('En ligne'), findsOneWidget);
    // Le toast de KoraToaster : entrée, pause, sortie.
    await tester.pump(const Duration(milliseconds: 300));
    expect(find.text('La vente est fermée pour ce départ.'), findsOneWidget);
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
  });
}
