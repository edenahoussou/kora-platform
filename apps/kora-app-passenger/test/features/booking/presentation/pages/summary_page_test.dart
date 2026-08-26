// Tests du récapitulatif : rappel du voyage, liste des voyageurs avec
// leur siège, et détail du prix.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/passenger.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/summary_page.dart';

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
    freeSeats = mockSeatsFor(
      departure,
    ).where((seat) => seat.status == SeatStatus.free).take(2).toList();
  });

  List<Passenger> passengersOf(int count) => [
    for (var i = 0; i < count; i++)
      Passenger(
        firstName: i == 0 ? 'Aïcha' : 'Kofi',
        lastName: i == 0 ? 'HOUNKPÈ' : 'MENSAH',
        phone: '+229 01 97 42 18 6$i',
        seat: freeSeats[i],
      ),
  ];

  Future<void> pumpSummary(WidgetTester tester, {int passengers = 1}) async {
    tester.view.physicalSize = const Size(390 * 3, 1000 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr', 'FR'),
        supportedLocales: const [Locale('fr', 'FR')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: withSeatHold(
          SummaryPage(
            departure: departure,
            passengers: passengersOf(passengers),
          ),
          bloc: holdBloc,
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

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
}
