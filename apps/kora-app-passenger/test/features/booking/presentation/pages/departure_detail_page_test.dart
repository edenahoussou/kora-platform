// Tests de l'écran de détail d'un départ : bandeau du trajet, services,
// étapes horodatées, prix par tronçon, et cas du départ manquant.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_data_source.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/data/repositories/booking_repository_impl.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/itinerary.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/line.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/repositories/booking_repository.dart';
import 'package:kora_app_passenger/features/booking/domain/repositories/departure_repository.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/departure_detail_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/departure_detail_page.dart';

import '../../../../support/stub_departure_repository.dart';

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
    // Le détail se rafraîchit sur `GET bus/departures/{uuid}` : sans ce
    // dépôt, la construction du cubit échoue. Le double échoue par
    // défaut, donc l'écran garde le départ transmis — ce que vérifient
    // les tests ci-dessous.
    getIt.registerLazySingleton<DepartureRepository>(
      StubDepartureRepository.new,
    );

    // L'écran charge les arrêts de la ligne — `GET bus/lines/{uuid}` —
    // par le dépôt de réservation. Le jeu de démonstration les déduit de
    // ses tronçons, ce qui donne à la frise de quoi s'afficher.
    getIt.registerLazySingleton<BookingRepository>(
      () =>
          BookingRepositoryImpl(BookingMockDataSource(latency: Duration.zero)),
    );

    departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;
  });

  tearDown(() => getIt.reset());

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
        home: DepartureDetailPage(
          itinerary: Itinerary.direct(value ?? departure),
        ),
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
    // Le serveur envoie des codes ; l'écran montre les libellés traduits.
    for (final service in ['Wi-Fi', 'Climatisation', 'Prises USB']) {
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

    // Les deux terminus portent une heure — celle du départ, qu'on
    // connaît. Les escales, elles, n'en portent aucune : le serveur ne
    // publie pas d'heure de passage, et en interpoler une sur la distance
    // tromperait le voyageur qui descend en route.
    expect(find.text('07:30 · départ confirmé'), findsOneWidget);
    expect(find.textContaining('arrivée prévue'), findsOneWidget);
    expect(find.textContaining('· estimée'), findsNothing);
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

  testWidgets('masque les étapes quand la ligne n’en publie aucune', (
    tester,
  ) async {
    // `bus/itineraries` ne renvoie pas les tronçons d'une ligne : une
    // ligne sans tronçon n'a donc aucun arrêt à montrer. L'intertitre
    // « Les étapes » coiffait pourtant une carte vide, suivie d'une note
    // qui parlait d'heures que rien n'affichait.
    final bare = Departure(
      id: departure.id,
      line: Line(
        // Un identifiant que le catalogue ne connaît pas : `bus/lines`
        // ne rend alors aucun arrêt.
        id: 'ligne-absente-du-catalogue',
        code: departure.line.code,
        origin: departure.line.origin,
        destination: departure.line.destination,
        duration: departure.line.duration,
        distanceKm: departure.line.distanceKm,
      ),
      departsAt: departure.departsAt,
      comfort: departure.comfort,
      price: departure.price,
      seatsTotal: departure.seatsTotal,
      seatsAvailable: departure.seatsAvailable,
    );

    await pumpDetail(tester, value: bare);

    expect(find.text('Les étapes'), findsNothing);
    expect(find.text('Prix par tronçon'), findsNothing);
    expect(
      find.textContaining('Les heures de passage sont des estimations'),
      findsNothing,
    );
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

    // Un seul tronçon : la section des prix ferait doublon avec le prix
    // du trajet, et la frise se réduirait aux deux terminus — déjà dans
    // le bandeau du haut.
    expect(find.text('Prix par tronçon'), findsNothing);
    expect(find.text('Les étapes'), findsNothing);
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
        home: const DepartureDetailPage(itinerary: null),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Ce départ n’est plus disponible'), findsOneWidget);
  });

  group('rafraîchissement', () {
    test('ne rappelle plus le serveur pour un départ isolé', () async {
      // MEZ-63 a retiré `GET bus/departures/{uuid}` : il n'existe plus de
      // route pour relire un départ seul. Continuer à l'appeler marquerait
      // l'écran « périmé » en permanence, ce qui est pire que de ne pas
      // rafraîchir.
      final cubit = DepartureDetailCubit(
        itinerary: Itinerary.direct(departure),
        repository: BookingRepositoryImpl(BookingMockDataSource()),
      );
      addTearDown(cubit.close);

      await cubit.refresh();

      expect(cubit.state.itinerary.first, departure);
      expect(cubit.state.isStale, isFalse);
      expect(cubit.state.isRefreshing, isFalse);
    });

    testWidgets('l’écran n’annonce plus de données vieillies', (tester) async {
      // L'avertissement n'avait de sens que face à un rafraîchissement qui
      // échoue. Sans rafraîchissement, il inquiéterait pour rien.
      await pumpDetail(tester);

      expect(find.textContaining('non actualisés'), findsNothing);
    });
  });
}
