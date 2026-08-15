// Tests d'écran de la liste des départs : les quatre états visuels
// (recherche en cours, résultats, aucun départ, erreur) et le tri.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/usecases/usecase.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_cities_usecase.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/search_departures_usecase.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/departures_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/departures_page.dart';
import 'package:kora_app_passenger/features/booking/presentation/widgets/departure_skeleton.dart';
import 'package:mocktail/mocktail.dart';

class MockGetCitiesUseCase extends Mock implements GetCitiesUseCase {}

class MockSearchDeparturesUseCase extends Mock
    implements SearchDeparturesUseCase {}

class _FakeCriteria extends Fake implements SearchCriteria {}

void main() {
  late MockGetCitiesUseCase getCities;
  late MockSearchDeparturesUseCase searchDepartures;

  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');
  final departures = mockDeparturesFor(
    SearchCriteria(
      origin: cotonou,
      destination: malanville,
      date: DateTime(2026, 8, 14),
    ),
  );

  setUpAll(() async {
    registerFallbackValue(const NoParams());
    registerFallbackValue(_FakeCriteria());
    await initializeDateFormatting(KoraDateFormats.locale);
  });

  setUp(() {
    getCities = MockGetCitiesUseCase();
    searchDepartures = MockSearchDeparturesUseCase();

    when(() => getCities(any())).thenAnswer((_) async => Right(kMockCities));
    when(
      () => searchDepartures(any()),
    ).thenAnswer((_) async => Right(departures));

    getIt.registerFactory<DeparturesBloc>(
      () => DeparturesBloc(getCities, searchDepartures),
    );
  });

  tearDown(() => getIt.reset());

  Future<void> pumpPage(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr', 'FR'),
        supportedLocales: const [Locale('fr', 'FR')],
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: const DeparturesPage(
          originId: 'ctn',
          destinationId: 'mlv',
          date: '2026-08-14',
          passengers: '1',
        ),
      ),
    );
  }

  /// Les heures de départ affichées, dans l'ordre des cartes.
  List<String> visibleTimes(WidgetTester tester) => tester
      .widgetList<DepartureCard>(find.byType(DepartureCard))
      .map((card) => card.departureTime)
      .toList();

  testWidgets('affiche l’écran de recherche et ses squelettes', (tester) async {
    // Recherche volontairement lente : sans ça l'écran serait déjà sur les
    // résultats à la première frame.
    when(() => searchDepartures(any())).thenAnswer(
      (_) => Future.delayed(
        const Duration(milliseconds: 400),
        () => Right(departures),
      ),
    );

    await pumpPage(tester);
    // Pas de `pumpAndSettle` ici : la barre de progression et le reflet des
    // squelettes tournent en boucle, il ne se stabiliserait jamais.
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.text('Recherche en cours'), findsOneWidget);
    expect(find.text('Nous trouvons votre meilleur départ'), findsOneWidget);
    expect(find.byType(DepartureSkeleton), findsNWidgets(3));
    expect(find.byType(DepartureCard), findsNothing);

    // Le trajet est annoncé dès qu'il est résolu, avant les résultats.
    expect(find.text('COTONOU → MALANVILLE'), findsOneWidget);

    await tester.pumpAndSettle();
    expect(find.byType(DepartureSkeleton), findsNothing);
  });

  testWidgets('affiche les résultats et le rappel des critères', (
    tester,
  ) async {
    await pumpPage(tester);
    await tester.pumpAndSettle();

    // 4 départs mockés, dont un complet : le titre ne compte que les
    // départs réservables.
    expect(find.text('3 départs disponibles'), findsOneWidget);
    expect(find.text('VEN. 14 AOÛT · 1 PASSAGER'), findsOneWidget);
    expect(find.byType(DepartureCard), findsNWidgets(4));
    expect(find.text('Prix affichés définitifs'), findsOneWidget);
    expect(find.text('COMPLET'), findsOneWidget);
  });

  testWidgets('trie par prix au clic sur la puce', (tester) async {
    await pumpPage(tester);
    await tester.pumpAndSettle();

    // Par défaut : tri par heure.
    expect(visibleTimes(tester), ['07:30', '09:00', '20:00', '22:30']);

    await tester.tap(find.text('PRIX'));
    await tester.pumpAndSettle();

    // Les deux départs à 12 500 FCFA passent devant celui à 14 500, et le
    // départ complet reste en dernier.
    expect(visibleTimes(tester).last, '22:30');
    expect(visibleTimes(tester).take(2), ['07:30', '09:00']);
  });

  testWidgets('affiche l’état « aucun départ »', (tester) async {
    when(
      () => searchDepartures(any()),
    ).thenAnswer((_) async => const Right<Failure, List<Departure>>([]));

    await pumpPage(tester);
    await tester.pumpAndSettle();

    expect(find.text('Aucun départ'), findsOneWidget);
    expect(find.text('Aucun voyage ce jour-là'), findsOneWidget);
    expect(find.byType(DepartureCard), findsNothing);
  });

  testWidgets('affiche l’erreur remontée par le domaine', (tester) async {
    when(
      () => searchDepartures(any()),
    ).thenAnswer((_) async => const Left(NetworkFailure()));

    await pumpPage(tester);
    await tester.pumpAndSettle();

    expect(find.text('La recherche a échoué'), findsOneWidget);
    expect(
      find.text('Vérifie ta connexion internet et réessaie.'),
      findsOneWidget,
    );
  });
}
