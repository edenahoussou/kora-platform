// L'écran « Réserver un voyage » : c'est ici — et non sur l'accueil — que
// se compose un aller-retour.
//
// L'accueil sert à partir vite sur un aller simple ; ce formulaire-ci a la
// place de demander une seconde date, et c'est lui qui porte la bascule.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/usecases/usecase.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_cities_usecase.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_next_trip_usecase.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_recent_cities_usecase.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/bus_search_page.dart';
import 'package:kora_app_passenger/features/home/presentation/bloc/home_bloc.dart';
import 'package:kora_app_passenger/features/home/presentation/pages/city_picker_page.dart';
import 'package:kora_app_passenger/features/home/presentation/pages/date_picker_page.dart';
import 'package:mocktail/mocktail.dart';

class MockGetCitiesUseCase extends Mock implements GetCitiesUseCase {}

class MockGetRecentCitiesUseCase extends Mock
    implements GetRecentCitiesUseCase {}

class MockGetNextTripUseCase extends Mock implements GetNextTripUseCase {}

void main() {
  late MockGetCitiesUseCase getCities;
  late MockGetRecentCitiesUseCase getRecentCities;
  late MockGetNextTripUseCase getNextTrip;

  /// Les liens ouverts par le formulaire : c'est ce qu'il produit vraiment.
  late List<String> pushed;

  final now = DateTime(2026, 8, 13, 10, 30);

  setUpAll(() async {
    registerFallbackValue(const NoParams());
    await initializeDateFormatting(KoraDateFormats.locale);
    await AppLocalization.preloadAll();
  });

  setUp(() {
    pushed = [];
    getCities = MockGetCitiesUseCase();
    getRecentCities = MockGetRecentCitiesUseCase();
    getNextTrip = MockGetNextTripUseCase();

    when(() => getCities(any())).thenAnswer((_) async => Right(kMockCities));
    when(
      () => getRecentCities(any()),
    ).thenAnswer((_) async => Right(mockRecentCities()));
    when(() => getNextTrip(any())).thenAnswer((_) async => const Right(null));

    getIt.registerFactory<HomeBloc>(
      () => HomeBloc(getCities, getRecentCities, getNextTrip, now: () => now),
    );
  });

  tearDown(() => getIt.reset());

  /// Choisit Cotonou puis Bohicon, comme le ferait le voyageur : le
  /// formulaire ne devine plus aucun trajet.
  Future<void> pickRoute(WidgetTester tester) async {
    await tester.tap(find.text('DÉPART'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Cotonou · St Michel'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('ARRIVÉE'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Bohicon · Gare centrale'));
    await tester.pumpAndSettle();
  }

  /// Laisse le toast finir sa course : il porte un timer, et le laisser
  /// pendre ferait échouer le test à la destruction de l'arbre.
  Future<void> settleToast(WidgetTester tester) =>
      tester.pump(const Duration(seconds: 4));

  Future<void> pumpSearch(
    WidgetTester tester, {
    TripType tripType = TripType.oneway,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final router = GoRouter(
      routes: [
        GoRoute(
          path: '/',
          builder: (_, _) => BusSearchPage(tripType: tripType),
        ),
        // Le centre d'aide n'est pas monté : seul le fait d'y aller compte.
        GoRoute(
          path: AppRouter.helpPath,
          builder: (context, state) {
            pushed.add(state.uri.toString());
            return const Scaffold(body: Text('centre d’aide'));
          },
        ),
        GoRoute(
          path: AppRouter.returnDatePath,
          builder: (context, state) {
            final args = state.extra! as DatePickerArgs;
            return DatePickerPage(
              selected: args.selected,
              today: args.today,
              title: context.l10n.homeReturnDateTitle,
            );
          },
        ),
        for (final path in [AppRouter.originPath, AppRouter.destinationPath])
          GoRoute(
            path: path,
            builder: (context, state) {
              final args = state.extra! as CityPickerArgs;
              return CityPickerPage(
                field: path == AppRouter.originPath
                    ? CityField.origin
                    : CityField.destination,
                cities: args.cities,
                recentCities: args.recentCities,
                selected: args.selected,
              );
            },
          ),
        // La liste des départs n'est pas montée : seul le lien compte.
        GoRoute(
          path: AppRouter.departuresPath,
          builder: (context, state) {
            pushed.add(state.uri.toString());
            return const Scaffold(body: Text('résultats'));
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

  testWidgets('ouvre sur l’aller simple', (tester) async {
    await pumpSearch(tester);

    expect(find.text('Aller simple'), findsOneWidget);
    expect(find.text('Aller-retour'), findsOneWidget);
    expect(find.text('Date du retour'), findsNothing);
    expect(find.text('Rechercher les départs'), findsOneWidget);
  });

  testWidgets('la bascule demande une date de retour', (tester) async {
    await pumpSearch(tester);

    await tester.tap(find.text('Aller-retour'));
    await tester.pumpAndSettle();

    expect(find.text('Date du retour'), findsOneWidget);
    expect(find.text('Choisir une date'), findsOneWidget);
    expect(find.text('Rechercher l’aller et le retour'), findsOneWidget);
  });

  testWidgets('sans date de retour, le formulaire nomme ce qui manque', (
    tester,
  ) async {
    // Le piège : les critères rendent `null` sur un aller-retour incomplet,
    // et parler de villes manquantes désignerait le mauvais champ.
    await pumpSearch(tester);

    await tester.tap(find.text('Aller-retour'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Rechercher l’aller et le retour'));
    await tester.pumpAndSettle();

    expect(find.text('Choisissez la date de votre retour.'), findsOneWidget);
    expect(pushed, isEmpty);

    await settleToast(tester);
  });

  testWidgets('le calendrier du retour ne remonte pas avant le départ', (
    tester,
  ) async {
    await pumpSearch(tester);

    await tester.tap(find.text('Aller-retour'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Date du retour'));
    await tester.pumpAndSettle();

    expect(find.text('Quand rentrez-vous ?'), findsOneWidget);
    // On ne rentre pas avant d'être parti.
    final calendar = tester.widget<DatePickerPage>(find.byType(DatePickerPage));
    expect(calendar.today, DateTime(2026, 8, 13));
  });

  testWidgets('la date du retour part dans le lien de recherche', (
    tester,
  ) async {
    // Le maillon fragile : les critères traversent une URL. Sans la date
    // dedans, l'aller-retour redeviendrait un aller simple.
    await pumpSearch(tester);
    await pickRoute(tester);

    await tester.tap(find.text('Aller-retour'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Date du retour'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('20'));
    await tester.pumpAndSettle();
    await tester.tap(find.textContaining('Choisir le'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Rechercher l’aller et le retour'));
    await tester.pumpAndSettle();

    expect(pushed, hasLength(1));
    expect(
      Uri.parse(pushed.single).queryParameters['return_date'],
      '2026-08-20',
    );

    await settleToast(tester);
  });

  testWidgets('un aller simple ne met aucune date de retour dans le lien', (
    tester,
  ) async {
    await pumpSearch(tester);
    await pickRoute(tester);

    await tester.tap(find.text('Rechercher les départs'));
    await tester.pumpAndSettle();

    expect(pushed, hasLength(1));
    expect(
      Uri.parse(pushed.single).queryParameters.containsKey('return_date'),
      isFalse,
    );
  });

  testWidgets('l’accueil peut ouvrir le formulaire sur l’aller-retour', (
    tester,
  ) async {
    // La carte « Réserver un aller-retour » de l'accueil mène ici. Y
    // déposer le voyageur sur « Aller simple » lui ferait recliquer sur
    // l'onglet d'à côté — le détour serait absurde.
    await pumpSearch(tester, tripType: TripType.roundtrip);

    expect(find.text('Date du retour'), findsOneWidget);
    expect(find.text('Rechercher l’aller et le retour'), findsOneWidget);
  });

  testWidgets('sans historique, « Trajets récents » ne coiffe pas le vide', (
    tester,
  ) async {
    when(() => getRecentCities(any())).thenAnswer((_) async => const Right([]));

    await pumpSearch(tester);

    expect(find.text('Trajets récents'), findsNothing);
  });

  testWidgets('avec un historique, la section paraît', (tester) async {
    await pumpSearch(tester);

    expect(find.text('Trajets récents'), findsOneWidget);
  });

  testWidgets('le bouton d’aide ouvre le centre d’aide', (tester) async {
    // Il annonçait « Aide » dans un toast, et c'est tout : le voyageur
    // bloqué sur ce formulaire y restait.
    await pumpSearch(tester);

    await tester.tap(find.byIcon(Icons.help_outline_rounded));
    await tester.pumpAndSettle();

    expect(pushed, [AppRouter.helpPath]);
  });
}
