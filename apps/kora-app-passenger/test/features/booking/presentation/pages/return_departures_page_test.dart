// Le choix du retour, deuxième temps d'un aller-retour.
//
// La recherche rapporte les deux sens d'un coup ; cet écran montre la
// moitié que la liste de l'aller n'a pas affichée. Sans lui, le voyageur
// aurait payé un aller après avoir demandé un aller-retour.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/itinerary.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/return_departures_page.dart';

void main() {
  late Itinerary outbound;
  late List<Itinerary> inbound;

  setUpAll(() async {
    await initializeDateFormatting(KoraDateFormats.locale);
    await AppLocalization.preloadAll();
  });

  setUp(() {
    final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
    final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');

    outbound = Itinerary.direct(
      mockDeparturesFor(
        SearchCriteria(
          origin: cotonou,
          destination: malanville,
          date: DateTime(2026, 8, 14),
        ),
      ).first,
    );

    // Le retour, c'est la même liaison prise à l'envers.
    inbound = [
      for (final departure in mockDeparturesFor(
        SearchCriteria(
          origin: malanville,
          destination: cotonou,
          date: DateTime(2026, 8, 20),
        ),
      ).take(2))
        Itinerary.direct(departure),
    ];
  });

  Future<void> pump(WidgetTester tester, ReturnDeparturesArgs? args) async {
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
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
        home: ReturnDeparturesPage(args: args),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('liste les retours rapportés par la recherche', (tester) async {
    await pump(
      tester,
      ReturnDeparturesArgs(
        outbound: outbound,
        itineraries: inbound,
        returnDate: DateTime(2026, 8, 20),
      ),
    );

    expect(find.text('2 retours disponibles'), findsOneWidget);
    expect(find.byType(DepartureCard), findsNWidgets(2));
  });

  testWidgets('garde l’aller sous les yeux', (tester) async {
    // On choisit un retour *par rapport* à un aller : le masquer
    // obligerait à se souvenir de ce qu'on vient de prendre.
    await pump(
      tester,
      ReturnDeparturesArgs(outbound: outbound, itineraries: inbound),
    );

    expect(find.text('ALLER CHOISI'), findsOneWidget);
    expect(
      find.textContaining(
        '${outbound.origin.name} → ${outbound.destination.name}',
      ),
      findsOneWidget,
    );
  });

  testWidgets('un seul retour se dit au singulier', (tester) async {
    await pump(
      tester,
      ReturnDeparturesArgs(outbound: outbound, itineraries: [inbound.first]),
    );

    expect(find.text('1 retour disponible'), findsOneWidget);
  });

  testWidgets('aucun retour ce jour-là mène au changement de date', (
    tester,
  ) async {
    // Distinct d'un aller simple : l'étape existe, et c'est elle qui n'a
    // rien à proposer. Laisser une liste muette n'apprendrait rien.
    await pump(
      tester,
      ReturnDeparturesArgs(outbound: outbound, itineraries: const []),
    );

    expect(find.text('Aucun retour ce jour-là'), findsOneWidget);
    // L'en-tête ne répète pas la phrase du corps.
    expect(find.text('Aucun retour'), findsOneWidget);
    expect(find.text('Changer la date du retour'), findsOneWidget);
    expect(find.byType(DepartureCard), findsNothing);
    // L'aller reste rappelé : c'est lui qui donne son sens à la date.
    expect(find.text('ALLER CHOISI'), findsOneWidget);
  });

  testWidgets('sans données, l’écran ne fabrique rien', (tester) async {
    await pump(tester, null);

    expect(find.byType(DepartureCard), findsNothing);
    expect(find.text('Aucun retour ce jour-là'), findsOneWidget);
  });
}
