// Tests d'écran de la liste des départs : les cinq états visuels
// (recherche en cours, résultats, aucun départ, filtres trop stricts,
// erreur), le tri, les filtres par créneau, le badge des places et le
// tiré-pour-actualiser.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/usecases/usecase.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure_search.dart';
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
    await AppLocalization.preloadAll();
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
    ).thenAnswer((_) async => Right(DepartureSearch.oneWay(departures)));

    getIt.registerFactory<DeparturesBloc>(
      () => DeparturesBloc(getCities, searchDepartures),
    );
  });

  tearDown(() => getIt.reset());

  Future<void> pumpPage(WidgetTester tester) async {
    // Écran volontairement plus haut qu'un téléphone : la `ListView` ne
    // construit que ce qu'elle affiche, et sur 844 points la quatrième
    // carte tombe sous la ligne de flottaison depuis que la barre de tri
    // et de filtres occupe le haut de l'écran. Les assertions ci-dessous
    // portent sur l'ordre et le nombre de résultats, pas sur le défilement.
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
        home: const DeparturesPage(
          originId: 'ctn',
          destinationId: 'mlv',
          date: '2026-08-14',
          passengers: '1',
        ),
      ),
    );
  }

  /// Ouvre la feuille des tris et filtres, repliés derrière l'icône de
  /// l'en-tête. Les puces n'existent plus ailleurs : tout test qui en
  /// tape une passe forcément par là.
  Future<void> openFilters(WidgetTester tester) async {
    await tester.tap(find.byIcon(Icons.tune_rounded));
    await tester.pumpAndSettle();
  }

  Future<void> closeFilters(WidgetTester tester) async {
    await tester.tap(find.text('Voir les résultats'));
    await tester.pumpAndSettle();
  }

  /// Les heures de départ affichées, dans l'ordre des cartes.
  List<String> visibleTimes(WidgetTester tester) => tester
      .widgetList<DepartureCard>(find.byType(DepartureCard))
      .map((card) => card.departureTime)
      .toList();

  testWidgets('la carte nomme les services du bus, traduits', (tester) async {
    // Le serveur envoie des codes (`usb_charging`) et un libellé français.
    // La carte doit montrer le libellé de l'app, pas le code — et pas le
    // français quand le voyageur lit en anglais.
    await pumpPage(tester);
    await tester.pumpAndSettle();

    final card = tester
        .widgetList<DepartureCard>(find.byType(DepartureCard))
        .first;

    expect(card.servicesLabel, 'Wi-Fi · Climatisation · Prises USB');
  });

  testWidgets('affiche l’écran de recherche et ses squelettes', (tester) async {
    // Recherche volontairement lente : sans ça l'écran serait déjà sur les
    // résultats à la première frame.
    when(() => searchDepartures(any())).thenAnswer(
      (_) => Future.delayed(
        const Duration(milliseconds: 400),
        () => Right(DepartureSearch.oneWay(departures)),
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

    // 4 départs mockés, dont un complet : le compte ne retient que les
    // départs réservables.
    expect(find.text('3 départs disponibles'), findsOneWidget);
    expect(find.text('VEN. 14 AOÛT · 1 PASSAGER'), findsOneWidget);
    // Sans filtre, aucun récapitulatif : il ne ferait que répéter le
    // titre.
    expect(find.textContaining('ce matin'), findsNothing);
    // Les puces ne sont plus dans la page, seulement leur icône.
    expect(find.text('PRIX'), findsNothing);
    expect(find.byIcon(Icons.tune_rounded), findsOneWidget);
    expect(find.byType(DepartureCard), findsNWidgets(4));
    expect(find.text('Prix affichés définitifs'), findsOneWidget);
    expect(find.text('COMPLET'), findsOneWidget);
  });

  group('badge des places', () {
    testWidgets('colore chaque départ selon son remplissage', (tester) async {
      await pumpPage(tester);
      await tester.pumpAndSettle();

      Color? colorOf(String label) =>
          tester.widget<Text>(find.text(label)).style?.color;

      // Sur 55 places : 12 libres (22 %) → or, 5 (9 %) → rouge, 22 (40 %)
      // → rien à signaler. Le quatrième départ est complet : il porte le
      // badge « Complet », pas un compte de places.
      expect(colorOf('12 places'), AppColors.gold);
      expect(colorOf('5 places'), AppColors.danger);
      expect(colorOf('22 places'), isNot(AppColors.gold));
      expect(find.byType(KoraSeatsBadge), findsNWidgets(3));
    });

    testWidgets('annonce le nombre de places restantes', (tester) async {
      await pumpPage(tester);
      await tester.pumpAndSettle();

      expect(find.text('12 places'), findsOneWidget);
      expect(find.text('5 places'), findsOneWidget);
      expect(find.text('22 places'), findsOneWidget);
    });
  });

  group('tri', () {
    testWidgets('trie par prix au clic sur la puce', (tester) async {
      await pumpPage(tester);
      await tester.pumpAndSettle();
      await openFilters(tester);

      // Par défaut : tri par heure, croissant.
      expect(find.text('HEURE ↑'), findsOneWidget);
      expect(visibleTimes(tester), ['07:30', '09:00', '20:00', '22:30']);

      await tester.tap(find.text('PRIX'));
      await tester.pumpAndSettle();

      // Les deux départs à 12 500 FCFA passent devant celui à 14 500, et le
      // départ complet reste en dernier.
      expect(visibleTimes(tester).last, '22:30');
      expect(visibleTimes(tester).take(2), ['07:30', '09:00']);
    });

    testWidgets('retaper la puce active inverse le sens', (tester) async {
      await pumpPage(tester);
      await tester.pumpAndSettle();
      await openFilters(tester);

      await tester.tap(find.text('PRIX'));
      await tester.pumpAndSettle();
      expect(find.text('PRIX ↑'), findsOneWidget);

      await tester.tap(find.text('PRIX ↑'));
      await tester.pumpAndSettle();

      expect(find.text('PRIX ↓'), findsOneWidget);
      // Le plus cher réservable d'abord (20:00, 14 500 FCFA) ; le complet,
      // pourtant au même prix, reste en queue.
      expect(visibleTimes(tester).first, '20:00');
      expect(visibleTimes(tester).last, '22:30');
    });

    testWidgets('propose aussi un tri par durée', (tester) async {
      await pumpPage(tester);
      await tester.pumpAndSettle();
      await openFilters(tester);

      expect(find.text('DURÉE'), findsOneWidget);

      await tester.tap(find.text('DURÉE'));
      await tester.pumpAndSettle();

      // Tous les départs mockés partagent la même ligne : le tri ne peut
      // rien réordonner, mais la puce doit devenir active.
      expect(find.text('DURÉE ↑'), findsOneWidget);
      expect(find.byType(DepartureCard), findsNWidgets(4));
    });
  });

  group('filtres par créneau', () {
    testWidgets('« Matin » ne garde que les départs d’avant midi', (
      tester,
    ) async {
      await pumpPage(tester);
      await tester.pumpAndSettle();

      await openFilters(tester);
      await tester.tap(find.text('MATIN'));
      await tester.pumpAndSettle();
      await closeFilters(tester);

      expect(visibleTimes(tester), ['07:30', '09:00']);
      // Le titre garde le compte de la liaison entière ; c'est le
      // récapitulatif qui dit ce que le filtre retient, et à quel prix.
      // Sans lui, « 3 départs disponibles » au-dessus de deux cartes
      // serait un mensonge.
      expect(find.text('3 départs disponibles'), findsOneWidget);
      expect(find.textContaining('2 départs ce matin, à '), findsOneWidget);
    });

    testWidgets('retaper la puce enlève le filtre', (tester) async {
      await pumpPage(tester);
      await tester.pumpAndSettle();

      await openFilters(tester);
      await tester.tap(find.text('MATIN'));
      await tester.pumpAndSettle();
      expect(find.byType(DepartureCard), findsNWidgets(2));

      await tester.tap(find.text('MATIN'));
      await tester.pumpAndSettle();
      expect(find.byType(DepartureCard), findsNWidgets(4));
    });

    testWidgets('un filtre actif se signale dans l’en-tête', (tester) async {
      // Les puces repliées, la pastille est le seul indice qu'une partie
      // des départs est écartée. Sans elle, une liste amputée passerait
      // pour la liste complète.
      await pumpPage(tester);
      await tester.pumpAndSettle();

      expect(
        tester.widget<KoraIconButton>(find.byType(KoraIconButton).last),
        isA<KoraIconButton>().having((b) => b.showBadge, 'showBadge', isFalse),
      );

      await openFilters(tester);
      await tester.tap(find.text('MATIN'));
      await tester.pumpAndSettle();
      await closeFilters(tester);

      expect(
        tester.widget<KoraIconButton>(find.byType(KoraIconButton).last),
        isA<KoraIconButton>().having((b) => b.showBadge, 'showBadge', isTrue),
      );
    });

    testWidgets('un créneau sans départ affiche un état dédié', (tester) async {
      await pumpPage(tester);
      await tester.pumpAndSettle();

      await openFilters(tester);
      await tester.tap(find.text('APRÈS-MIDI'));
      await tester.pumpAndSettle();
      await closeFilters(tester);

      expect(find.text('Aucun départ sur ce créneau'), findsOneWidget);
      expect(find.byType(DepartureCard), findsNothing);
      // Surtout pas le message « liaison non desservie » : la liaison
      // l'est, ce sont les filtres qui sont trop stricts.
      expect(find.text('Aucun voyage ce jour-là'), findsNothing);
      // L'icône reste pour rouvrir la feuille et décocher sur place.
      expect(find.byIcon(Icons.tune_rounded), findsOneWidget);
    });

    testWidgets('« Voir tous les départs » rend la liste complète', (
      tester,
    ) async {
      await pumpPage(tester);
      await tester.pumpAndSettle();

      await openFilters(tester);
      await tester.tap(find.text('APRÈS-MIDI'));
      await tester.pumpAndSettle();
      await closeFilters(tester);

      await tester.tap(find.text('Voir tous les départs'));
      await tester.pumpAndSettle();

      expect(find.byType(DepartureCard), findsNWidgets(4));
      expect(find.text('3 départs disponibles'), findsOneWidget);
    });

    testWidgets('« Réinitialiser » vide les filtres depuis la liste', (
      tester,
    ) async {
      // Le raccourci existe parce que les puces sont repliées : sans lui,
      // défaire un créneau demanderait de rouvrir la feuille.
      await pumpPage(tester);
      await tester.pumpAndSettle();

      await openFilters(tester);
      await tester.tap(find.text('MATIN'));
      await tester.pumpAndSettle();
      await closeFilters(tester);

      expect(find.byType(DepartureCard), findsNWidgets(2));

      await tester.tap(find.text('Réinitialiser'));
      await tester.pumpAndSettle();

      expect(find.byType(DepartureCard), findsNWidgets(4));
      expect(find.text('Réinitialiser'), findsNothing);
    });
  });

  group('tiré-pour-actualiser', () {
    testWidgets('relance la recherche et met la liste à jour', (tester) async {
      // Le second appel rend un départ de moins : de quoi vérifier que
      // c'est bien le résultat frais qui est affiché.
      var attempt = 0;
      when(() => searchDepartures(any())).thenAnswer((_) async {
        attempt += 1;
        return Right(
          DepartureSearch.oneWay(
            attempt == 1 ? departures : departures.take(2).toList(),
          ),
        );
      });

      await pumpPage(tester);
      await tester.pumpAndSettle();
      expect(find.byType(DepartureCard), findsNWidgets(4));

      await tester.fling(
        find.byType(DepartureCard).first,
        const Offset(0, 400),
        1000,
      );
      await tester.pumpAndSettle();

      expect(attempt, 2);
      expect(find.byType(DepartureCard), findsNWidgets(2));
    });

    testWidgets('un échec garde la liste et prévient', (tester) async {
      var attempt = 0;
      when(() => searchDepartures(any())).thenAnswer((_) async {
        attempt += 1;
        return attempt == 1
            ? Right(DepartureSearch.oneWay(departures))
            : const Left(NetworkFailure());
      });

      await pumpPage(tester);
      await tester.pumpAndSettle();

      await tester.fling(
        find.byType(DepartureCard).first,
        const Offset(0, 400),
        1000,
      );
      await tester.pump();
      await tester.pump(const Duration(seconds: 1));

      // L'écran d'erreur ne remplace pas des résultats corrects.
      expect(find.byType(DepartureCard), findsNWidgets(4));
      expect(find.text('La recherche a échoué'), findsNothing);
      expect(find.byType(SnackBar), findsOneWidget);
    });
  });

  testWidgets('affiche l’état « aucun départ »', (tester) async {
    when(() => searchDepartures(any())).thenAnswer(
      (_) async =>
          const Right<Failure, DepartureSearch>(DepartureSearch.oneWay([])),
    );

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

  testWidgets('« Réessayer » relance la recherche et affiche les résultats', (
    tester,
  ) async {
    // Premier appel en échec, second en succès : le scénario d'une
    // connexion qui revient.
    var attempt = 0;
    when(() => searchDepartures(any())).thenAnswer((_) async {
      attempt += 1;
      return attempt == 1
          ? const Left(NetworkFailure())
          : Right(DepartureSearch.oneWay(departures));
    });

    await pumpPage(tester);
    await tester.pumpAndSettle();

    expect(find.text('La recherche a échoué'), findsOneWidget);
    expect(find.byType(DepartureCard), findsNothing);

    await tester.tap(find.text('Réessayer'));
    await tester.pumpAndSettle();

    expect(find.text('La recherche a échoué'), findsNothing);
    expect(find.byType(DepartureCard), findsWidgets);
    expect(attempt, 2);
  });
}
