// Tests d'écran de l'accueil : les 4 états visuels demandés par le ticket
// (chargement, succès, vide, erreur) et le pré-remplissage du formulaire.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/usecases/usecase.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/upcoming_trip.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_cities_usecase.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_next_trip_usecase.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_recent_cities_usecase.dart';
import 'package:kora_app_passenger/features/home/presentation/bloc/home_bloc.dart';
import 'package:kora_app_passenger/features/home/presentation/pages/city_picker_page.dart';
import 'package:kora_app_passenger/features/home/presentation/pages/home_page.dart';
import 'package:mocktail/mocktail.dart';

class MockGetCitiesUseCase extends Mock implements GetCitiesUseCase {}

class MockGetRecentCitiesUseCase extends Mock
    implements GetRecentCitiesUseCase {}

class MockGetNextTripUseCase extends Mock implements GetNextTripUseCase {}

void main() {
  late MockGetCitiesUseCase getCities;
  late MockGetRecentCitiesUseCase getRecentCities;
  late MockGetNextTripUseCase getNextTrip;

  final now = DateTime(2026, 8, 13, 10, 30);
  final trip = mockUpcomingTrip(now);

  setUpAll(() async {
    registerFallbackValue(const NoParams());
    // L'app le fait dans bootstrap() ; les tests d'écran ne passent pas
    // par là, mais les cartes affichent des dates formatées en français.
    await initializeDateFormatting(KoraDateFormats.locale);
  });

  setUp(() {
    getCities = MockGetCitiesUseCase();
    getRecentCities = MockGetRecentCitiesUseCase();
    getNextTrip = MockGetNextTripUseCase();

    when(() => getCities(any())).thenAnswer((_) async => Right(kMockCities));
    when(
      () => getRecentCities(any()),
    ).thenAnswer((_) async => Right(mockRecentCities()));
    when(() => getNextTrip(any())).thenAnswer((_) async => Right(trip));

    getIt.registerFactory<HomeBloc>(
      () => HomeBloc(getCities, getRecentCities, getNextTrip, now: () => now),
    );
  });

  tearDown(() => getIt.reset());

  /// L'accueil est dessiné pour un écran de téléphone : on force cette
  /// taille plutôt que les 800x600 par défaut des tests, sinon la mise en
  /// page déborderait pour de mauvaises raisons.
  Future<void> pumpHome(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    // Un `GoRouter` minimal, et non le routeur complet de l'app : l'accueil
    // ouvre l'écran de sélection de ville avec `context.push`, qui exige un
    // routeur dans l'arbre. On ne déclare que les deux routes utiles au
    // test, sans la bottom nav ni les autres onglets.
    final router = GoRouter(
      routes: [
        GoRoute(path: '/', builder: (_, _) => const HomePage()),
        GoRoute(
          path: AppRouter.originPath,
          builder: (context, state) {
            final args = state.extra! as CityPickerArgs;
            return CityPickerPage(
              field: CityField.origin,
              cities: args.cities,
              recentCities: args.recentCities,
              selected: args.selected,
            );
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
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        routerConfig: router,
      ),
    );
  }

  testWidgets('affiche un squelette pendant le chargement', (tester) async {
    // Les mocks répondent instantanément par défaut : sans ce délai,
    // l'écran serait déjà en succès à la première frame et l'état de
    // chargement ne serait jamais observable.
    when(() => getCities(any())).thenAnswer(
      (_) => Future.delayed(const Duration(milliseconds: 300), () {
        return Right(kMockCities);
      }),
    );

    await pumpHome(tester);
    await tester.pump(const Duration(milliseconds: 50));

    // L'en-tête ne dépend d'aucune donnée : il est visible tout de suite.
    expect(find.text('Bonjour Aïcha,'), findsOneWidget);
    expect(find.byType(KoraLoadingIndicator), findsWidgets);
    expect(find.text('Rechercher un voyage'), findsNothing);

    await tester.pumpAndSettle();
  });

  testWidgets('affiche le formulaire pré-rempli et le prochain voyage', (
    tester,
  ) async {
    await pumpHome(tester);
    await tester.pumpAndSettle();

    expect(find.text('Où souhaitez-vous voyager ?'), findsOneWidget);
    expect(find.text('Cotonou · St Michel'), findsOneWidget);
    expect(find.text('Malanville'), findsWidgets);
    expect(find.text('13 août 2026'), findsOneWidget);
    expect(find.text('1 adulte'), findsOneWidget);
    expect(find.text('Rechercher un voyage'), findsOneWidget);

    // Carte « Prochain voyage » : le badge est mis en capitales par KoraTag.
    expect(find.text('DANS 7 JOURS'), findsOneWidget);
    expect(find.text('Siège 4A'), findsOneWidget);
    expect(find.text('Présentez-vous 30 min avant le départ.'), findsOneWidget);
  });

  testWidgets('affiche l’état vide quand aucun voyage n’est à venir', (
    tester,
  ) async {
    when(
      () => getNextTrip(any()),
    ).thenAnswer((_) async => const Right<Failure, UpcomingTrip?>(null));

    await pumpHome(tester);
    await tester.pumpAndSettle();

    expect(find.text('Aucun voyage à venir'), findsOneWidget);
    expect(find.text('DANS 7 JOURS'), findsNothing);
  });

  testWidgets('affiche l’erreur et permet de réessayer', (tester) async {
    when(
      () => getCities(any()),
    ).thenAnswer((_) async => const Left(NetworkFailure()));

    await pumpHome(tester);
    await tester.pumpAndSettle();

    expect(find.text('Impossible de charger l’accueil'), findsOneWidget);
    expect(
      find.text('Vérifie ta connexion internet et réessaie.'),
      findsOneWidget,
    );

    // Le réessai relance bien le chargement — cette fois avec succès.
    when(() => getCities(any())).thenAnswer((_) async => Right(kMockCities));
    await tester.tap(find.text('Réessayer'));
    await tester.pumpAndSettle();

    expect(find.text('Rechercher un voyage'), findsOneWidget);
    verify(() => getCities(any())).called(2);
  });

  testWidgets('ouvre l’écran de sélection et reporte la ville choisie', (
    tester,
  ) async {
    await pumpHome(tester);
    await tester.pumpAndSettle();

    await tester.tap(find.text('Cotonou · St Michel'));
    await tester.pumpAndSettle();

    // On est bien sur l'écran plein, pas dans une feuille modale.
    expect(find.byType(CityPickerPage), findsOneWidget);
    expect(find.text('D’où partez-vous ?'), findsOneWidget);
    expect(find.text('Recherches récentes'), findsOneWidget);

    await tester.tap(find.text('Parakou · Zongo'));
    await tester.pumpAndSettle();

    // Retour sur l'accueil, champ « Départ » mis à jour.
    expect(find.byType(CityPickerPage), findsNothing);
    expect(find.text('Parakou · Zongo'), findsOneWidget);
    expect(find.text('Cotonou · St Michel'), findsNothing);
  });
}
