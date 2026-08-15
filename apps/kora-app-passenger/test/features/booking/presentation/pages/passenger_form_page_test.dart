// Tests du formulaire des voyageurs : un bloc par siège, pré-remplissage
// du premier passager, validation des champs obligatoires.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/passenger_form_page.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/summary_page.dart';

void main() {
  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');

  late Departure departure;
  late List<Seat> freeSeats;

  setUpAll(() async {
    // Le récapitulatif, atteint depuis ce formulaire, affiche des dates
    // formatées en français.
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
    freeSeats = mockSeatsFor(departure)
        .where((seat) => seat.status == SeatStatus.free)
        .take(2)
        .toList();
  });

  Future<void> pumpForm(WidgetTester tester, {int passengers = 1}) async {
    tester.view.physicalSize = const Size(390 * 3, 1200 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    // Un routeur minimal : le formulaire valide empile le récapitulatif
    // avec `context.push`, qui exige un `GoRouter` dans l'arbre.
    final router = GoRouter(
      routes: [
        GoRoute(
          path: '/',
          builder: (_, _) => PassengerFormPage(
            departure: departure,
            seats: freeSeats.take(passengers).toList(),
          ),
        ),
        GoRoute(
          path: AppRouter.summaryPath,
          builder: (context, state) {
            final args = state.extra! as SummaryArgs;
            return SummaryPage(
              departure: args.departure,
              passengers: args.passengers,
            );
          },
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      MaterialApp.router(theme: AppTheme.light, routerConfig: router),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('affiche un bloc par siège, avec son numéro', (tester) async {
    await pumpForm(tester, passengers: 2);

    expect(find.text('Qui voyage ?'), findsOneWidget);
    expect(find.text('2 PASSAGERS'), findsOneWidget);
    expect(find.text('Passager 1'), findsOneWidget);
    expect(find.text('Passager 2'), findsOneWidget);
    expect(find.text('Siège ${freeSeats[0].label}'), findsOneWidget);
    expect(find.text('Siège ${freeSeats[1].label}'), findsOneWidget);
  });

  testWidgets('pré-remplit le premier passager seulement', (tester) async {
    await pumpForm(tester, passengers: 2);

    // Le premier voyageur est le titulaire du compte, les suivants sont à
    // saisir.
    expect(find.text('Aïcha'), findsOneWidget);
    expect(find.text('HOUNKPÈ'), findsOneWidget);

    final champs = tester.widgetList<TextField>(find.byType(TextField));
    final vides = champs.where((champ) => champ.controller!.text.isEmpty);
    expect(vides, hasLength(4), reason: 'les 4 champs du 2e passager');
  });

  testWidgets('refuse de continuer tant qu’un champ requis est vide', (
    tester,
  ) async {
    await pumpForm(tester, passengers: 2);

    await tester.tap(find.text('Enregistrer et continuer'));
    await tester.pumpAndSettle();

    expect(find.text('Prénom requis'), findsOneWidget);
    expect(find.text('Nom requis'), findsOneWidget);
    expect(find.text('Téléphone requis'), findsOneWidget);
    expect(
      find.text('Complétez les champs signalés pour continuer.'),
      findsOneWidget,
    );
  });

  testWidgets('signale une adresse e-mail mal formée', (tester) async {
    await pumpForm(tester);

    final email = find.byType(TextField).last;
    await tester.enterText(email, 'aicha.example.com');
    await tester.tap(find.text('Enregistrer et continuer'));
    await tester.pumpAndSettle();

    expect(find.text('Adresse e-mail invalide'), findsOneWidget);
  });

  testWidgets('accepte un formulaire complet', (tester) async {
    await pumpForm(tester);

    await tester.tap(find.text('Enregistrer et continuer'));
    await tester.pumpAndSettle();

    // Le premier passager est pré-rempli : rien à signaler.
    expect(find.textContaining('requis'), findsNothing);
    // Le formulaire valide mène au récapitulatif.
    expect(find.text('Vérifiez votre voyage'), findsOneWidget);
  });

  testWidgets('annonce l’absence de sièges plutôt que d’afficher un vide', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: PassengerFormPage(departure: departure),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Aucun siège sélectionné'), findsOneWidget);
  });
}
