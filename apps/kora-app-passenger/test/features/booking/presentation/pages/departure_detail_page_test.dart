// Tests de l'écran de détail d'un départ : bandeau du trajet, services,
// étapes horodatées, prix par tronçon, et cas du départ manquant.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/departure_detail_page.dart';

void main() {
  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');
  final portoNovo = kMockCities.firstWhere((city) => city.id == 'pnv');

  late Departure departure;

  setUpAll(() async {
    await AppLocalization.preloadAll();
    await initializeDateFormatting(KoraDateFormats.locale);
  });

  setUp(() {
    departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;
  });

  Future<void> pumpDetail(WidgetTester tester, {Departure? value}) async {
    tester.view.physicalSize = const Size(390 * 3, 1400 * 3);
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
        home: DepartureDetailPage(departure: value ?? departure),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('affiche le trajet, la date et le prix', (tester) async {
    await pumpDetail(tester);

    expect(find.text('Votre départ'), findsOneWidget);
    expect(find.text('KORA CONFORT'), findsOneWidget); // surtitre en capitales
    expect(find.text('12 PLACES RESTANTES'), findsOneWidget);
    expect(find.text('07:30'), findsWidgets);
    expect(find.text('17:15'), findsWidgets);
    expect(find.text('Vendredi 14 août 2026'), findsOneWidget);

    // On compare au montant formaté par l'entité : le séparateur de
    // milliers français est une espace fine insécable, qu'une chaîne
    // recopiée à la main ne reproduit pas.
    expect(departure.price.amount, 12500);
    expect(find.text(departure.price.formatted), findsOneWidget);
  });

  testWidgets('liste les services à bord', (tester) async {
    await pumpDetail(tester);

    expect(find.text('Votre confort à bord'), findsOneWidget);
    for (final service in ['Wi-Fi', 'Climatisation', 'USB']) {
      expect(find.text(service), findsOneWidget);
    }
  });

  testWidgets('horodate chaque étape à partir de l’heure de départ', (
    tester,
  ) async {
    await pumpDetail(tester);

    expect(find.text('Les étapes'), findsOneWidget);

    // La ligne dessert Bohicon, Parakou et Kandi entre les deux terminus.
    for (final stop in [
      'Cotonou',
      'Bohicon',
      'Parakou',
      'Kandi',
      'Malanville',
    ]) {
      expect(find.text(stop), findsWidgets, reason: '$stop absent des étapes');
    }

    // Seul le départ est ferme, le reste est estimé.
    expect(find.text('07:30 · départ confirmé'), findsOneWidget);
    expect(find.textContaining('· estimée'), findsNWidgets(4));
  });

  testWidgets('affiche le prix de chaque tronçon', (tester) async {
    await pumpDetail(tester);

    expect(find.text('Prix par tronçon'), findsOneWidget);
    expect(find.text('Cotonou → Bohicon'), findsOneWidget);
    expect(find.text('Bohicon → Parakou'), findsOneWidget);
    expect(find.text('Kandi → Malanville'), findsOneWidget);

    // Cotonou → Bohicon : 128 km × 17 ≈ 2 176, arrondi aux 500 FCFA.
    final firstSegment = departure.line.segments.first;
    expect(firstSegment.distanceKm, 128);
    expect(firstSegment.price.amount, 2000);
    expect(find.text(firstSegment.price.formatted), findsWidgets);
  });

  testWidgets('masque les tronçons sur une liaison directe', (tester) async {
    final direct = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: portoNovo,
        date: DateTime(2026, 8, 14),
      ),
    ).first;

    await pumpDetail(tester, value: direct);

    // Un seul tronçon : la section ferait doublon avec le prix du trajet.
    expect(find.text('Prix par tronçon'), findsNothing);
    expect(find.text('Les étapes'), findsOneWidget);
  });

  testWidgets('annonce un départ manquant plutôt que de planter', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    // Cet écran lit ses textes comme les autres : sans le délégué, il
    // ne peut pas se construire.
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
        home: const DepartureDetailPage(departure: null),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Ce départ n’est plus disponible'), findsOneWidget);
  });
}
