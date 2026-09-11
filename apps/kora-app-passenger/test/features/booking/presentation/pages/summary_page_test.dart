// Tests du récapitulatif : rappel du voyage, liste des voyageurs avec
// leur siège, et détail du prix.

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
  late SeatHoldBloc holdBloc;

  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');

  late Departure departure;
  late List<Seat> freeSeats;

  setUpAll(() async {
    await AppLocalization.preloadAll();
    await initializeDateFormatting(KoraDateFormats.locale);
  });

  setUp(() {
    holdBloc = SeatHoldBloc(StubReservationRepository());
    addTearDown(holdBloc.close);

    departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;
    // Assez de sièges pour un groupe : le détail du prix se vérifie à
    // cinq voyageurs, le seuil de la remise.
    freeSeats = mockSeatsFor(
      departure,
    ).where((seat) => seat.status == SeatStatus.free).take(10).toList();
  });

  List<Passenger> passengersOf(int count) => [
    for (var i = 0; i < count; i++)
      Passenger(
        firstName: i == 0 ? 'Aïcha' : 'Kofi',
        lastName: i == 0 ? 'HOUNKPÈ' : 'MENSAH',
        phone: '+229 01 97 42 18 ${60 + i}',
        seat: freeSeats[i],
      ),
  ];

  /// Ce que le récapitulatif transmet au paiement, saisi par le routeur.
  PaymentArgs? sentToPayment;

  Future<void> pumpSummary(
    WidgetTester tester, {
    int passengers = 1,
    StubReservationRepository? repository,
    Departure? returnDeparture,
    List<Seat>? returnSeats,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1000 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    sentToPayment = null;

    final quote = GroupQuoteCubit(repository ?? StubReservationRepository());
    final router = GoRouter(
      routes: [
        GoRoute(
          path: '/',
          builder: (context, state) => withSeatHold(
            SummaryPage(
              departure: departure,
              passengers: passengersOf(passengers),
              returnDeparture: returnDeparture,
              returnSeats: returnSeats,
            ),
            bloc: holdBloc,
            groupQuote: quote,
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

  group('détail du prix', () {
    /// Les montants passent par `Price.formatted`, qui sépare les milliers
    /// d'une espace fine insécable — pas celle du clavier.
    String money(int amount) => Price(amount).formatted;

    testWidgets('un voyageur seul ne voit ni tarif unitaire ni remise', (
      tester,
    ) async {
      // Répéter « tarif par voyageur » sous un total identique n'apprend
      // rien à qui voyage seul.
      await pumpSummary(tester);

      expect(find.text('Tarif par voyageur'), findsNothing);
      expect(find.textContaining('Remise groupe'), findsNothing);
    });

    testWidgets('un groupe voit le tarif unitaire, la remise et le total', (
      tester,
    ) async {
      // Le barème du stub : 12 500 par voyageur, 10 % dès cinq.
      await pumpSummary(tester, passengers: 5);

      expect(find.text('Tarif par voyageur'), findsOneWidget);
      expect(find.text(money(12500)), findsOneWidget);
      expect(find.text('Remise groupe (dès 5 voyageurs)'), findsOneWidget);
      expect(find.text('−${money(6250)}'), findsOneWidget);
      // 62 500 moins la remise.
      expect(find.text(money(56250)), findsOneWidget);
    });

    testWidgets('sous le seuil, aucune ligne de remise', (tester) async {
      await pumpSummary(tester, passengers: 3);

      expect(find.text('Tarif par voyageur'), findsOneWidget);
      expect(find.textContaining('Remise groupe'), findsNothing);
      expect(find.text(money(37500)), findsWidgets);
    });

    testWidgets('un devis refusé laisse le tarif de la recherche', (
      tester,
    ) async {
      // Barrer le récapitulatif parce qu'une ligne de détail manque
      // coûterait plus au voyageur que de lui montrer un total sans détail.
      await pumpSummary(
        tester,
        passengers: 2,
        repository: StubReservationRepository(
          failure: const ServerFailure('Devis indisponible.'),
        ),
      );

      expect(find.text('Vérifiez votre voyage'), findsOneWidget);
      expect(find.textContaining('Remise groupe'), findsNothing);
      // Le prix du départ, multiplié localement.
      expect(find.text((departure.price * 2).formatted), findsWidgets);
    });
  });

  group('montant transmis au paiement', () {
    testWidgets('un groupe transmet le total remisé, pas le produit', (
      tester,
    ) async {
      // Le défaut corrigé ici : le récapitulatif affichait 56 250 puis
      // envoyait 62 500 au paiement. Deux écrans consécutifs montrant deux
      // prix différents suffisent à faire abandonner un achat, même si le
      // serveur débite ensuite le bon montant.
      await pumpSummary(tester, passengers: 5);

      await tester.tap(find.text('Continuer vers le paiement'));
      await tester.pumpAndSettle();

      // Barème du stub : 12 500 par voyageur, 10 % dès cinq.
      expect(sentToPayment!.total, const Price(56250));
    });

    testWidgets('un voyageur seul transmet le tarif du départ', (tester) async {
      await pumpSummary(tester);

      await tester.tap(find.text('Continuer vers le paiement'));
      await tester.pumpAndSettle();

      expect(sentToPayment!.total, departure.price);
    });

    testWidgets('sans devis, le produit local prend le relais', (tester) async {
      // Un devis refusé ne doit pas barrer le paiement : le prix reste
      // juste à la remise près, et le serveur tranchera de toute façon.
      await pumpSummary(
        tester,
        passengers: 3,
        repository: StubReservationRepository(
          failure: const ServerFailure('Devis indisponible.'),
        ),
      );

      await tester.tap(find.text('Continuer vers le paiement'));
      await tester.pumpAndSettle();

      expect(sentToPayment!.total, departure.price * 3);
    });
  });

  testWidgets('rappelle le voyage choisi et le point d’embarquement', (
    tester,
  ) async {
    await pumpSummary(tester);

    expect(find.text('Vérifiez votre voyage'), findsOneWidget);
    expect(find.text('Cotonou → Malanville'), findsOneWidget);
    expect(find.text('Ven. 14 août · 07:30'), findsOneWidget);
    expect(find.text('Gare KORA St Michel'), findsOneWidget);
    expect(find.text('Siège ${freeSeats[0].label}'), findsOneWidget);
  });

  testWidgets('liste chaque voyageur avec son téléphone et son siège', (
    tester,
  ) async {
    await pumpSummary(tester, passengers: 2);

    expect(find.text('Aïcha HOUNKPÈ'), findsOneWidget);
    expect(find.text('Kofi MENSAH'), findsOneWidget);
    expect(find.text('+229 01 97 42 18 60'), findsOneWidget);

    // Le siège de chaque voyageur, en pastille dorée (mise en capitales
    // par `KoraTag`).
    expect(find.text(freeSeats[0].label.toUpperCase()), findsOneWidget);
    expect(find.text(freeSeats[1].label.toUpperCase()), findsOneWidget);

    // Deux sièges rappelés ensemble en tête d'écran.
    expect(
      find.text('Sièges ${freeSeats[0].label}, ${freeSeats[1].label}'),
      findsOneWidget,
    );
  });

  testWidgets('multiplie le prix par le nombre de billets', (tester) async {
    await pumpSummary(tester, passengers: 2);

    final total = departure.price * 2;

    expect(find.text('2 billets KORA Confort'), findsOneWidget);
    expect(find.text('Frais de service'), findsOneWidget);
    expect(find.text('Inclus'), findsOneWidget);
    expect(find.text('Total à payer'), findsOneWidget);

    // Le montant apparaît deux fois : sur la ligne des billets et sur le
    // total, puisque les frais sont inclus.
    expect(find.text(total.formatted), findsNWidgets(2));
  });

  testWidgets('annonce une réservation vide plutôt qu’un écran blanc', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: withSeatHold(SummaryPage(departure: departure), bloc: holdBloc),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Rien à récapituler'), findsOneWidget);
  });

  testWidgets('un aller-retour montre ses deux trajets et ses deux sièges', (
    tester,
  ) async {
    // Le voyageur vérifie ici ce qu'il paie. Ne montrer que l'aller
    // laisserait croire que le retour n'a ni bus ni place attribuée, alors
    // qu'il est déjà retenu et compté dans le total.
    final inbound = mockDeparturesFor(
      SearchCriteria(
        origin: kMockCities.firstWhere((city) => city.id == 'mlv'),
        destination: kMockCities.firstWhere((city) => city.id == 'ctn'),
        date: DateTime(2026, 8, 20),
      ),
    ).first;

    await pumpSummary(
      tester,
      returnDeparture: inbound,
      returnSeats: [freeSeats[3]],
    );

    expect(find.text('RETOUR'), findsOneWidget);
    expect(
      find.text(
        '${inbound.line.origin.name} → ${inbound.line.destination.name}',
      ),
      findsOneWidget,
    );
    // Les deux sièges du voyageur, l'aller puis le retour.
    expect(
      find.textContaining('${freeSeats[0].label} · ${freeSeats[3].label}'),
      findsWidgets,
    );
  });

  testWidgets('un aller simple ne parle d’aucun retour', (tester) async {
    await pumpSummary(tester);

    expect(find.text('RETOUR'), findsNothing);
  });
}
