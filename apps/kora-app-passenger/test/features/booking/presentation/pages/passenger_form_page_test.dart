// Tests du formulaire des voyageurs : un bloc par siège, pré-remplissage
// du premier passager, validation des champs obligatoires.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/itinerary.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/journey.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/passenger_form_page.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/summary_page.dart';
import 'package:kora_app_passenger/features/profil/presentation/pages/birth_date_picker_page.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  late SeatHoldBloc holdBloc;
  late AuthBloc authBloc;

  /// Gardée à part du Bloc : c'est elle qui retient les voyageurs envoyés,
  /// et les tests de la revue Kora vérifient ce que chacun porte.
  late StubReservationRepository reservations;

  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');

  late Departure departure;

  /// Le même départ, mais pris de Cotonou à Lomé : le tronçon quitte le
  /// Bénin, et c'est cela seul qui déclenche le formulaire du manifeste.
  late Departure crossBorder;

  late List<Seat> freeSeats;

  setUpAll(() async {
    await AppLocalization.preloadAll();
    // Le récapitulatif, atteint depuis ce formulaire, affiche des dates
    // formatées en français.
    await initializeDateFormatting(KoraDateFormats.locale);
  });

  setUp(() {
    reservations = StubReservationRepository();
    holdBloc = SeatHoldBloc(reservations);
    addTearDown(holdBloc.close);

    departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;
    crossBorder = Departure(
      id: departure.id,
      line: departure.line,
      departsAt: departure.departsAt,
      comfort: departure.comfort,
      price: departure.price,
      seatsTotal: departure.seatsTotal,
      seatsAvailable: departure.seatsAvailable,
      originCountry: 'BJ',
      destinationCountry: 'TG',
    );

    freeSeats = mockSeatsFor(
      departure,
    ).where((seat) => seat.status == SeatStatus.free).take(2).toList();
  });

  /// Laisse le toast finir sa course : il porte un timer, et le laisser
  /// pendre ferait échouer le test à la destruction de l'arbre.
  Future<void> settleToast(WidgetTester tester) =>
      tester.pump(const Duration(seconds: 4));

  Future<void> pumpForm(
    WidgetTester tester, {
    int passengers = 1,
    bool international = false,
    // Un formulaire international à deux voyageurs dépasse la hauteur
    // par défaut : ce qui n'est pas posé n'est pas trouvable.
    double height = 1200,
  }) async {
    tester.view.physicalSize = Size(390 * 3, height * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    // Un routeur minimal : le formulaire valide empile le récapitulatif
    // avec `context.push`, qui exige un `GoRouter` dans l'arbre.
    authBloc = await openedSession();
    addTearDown(authBloc.close);

    final router = GoRouter(
      routes: [
        GoRoute(
          path: '/',
          builder: (_, _) => withSeatHold(
            PassengerFormPage(
              journey: Journey(
                outbound: Itinerary.direct(
                  international ? crossBorder : departure,
                ),
                seatsByLeg: [freeSeats.take(passengers).toList()],
              ),
            ),
            bloc: holdBloc,
            auth: authBloc,
          ),
        ),
        GoRoute(
          path: AppRouter.birthDatePath,
          builder: (context, state) {
            final args = state.extra! as BirthDateArgs;
            return BirthDatePickerPage(
              selected: args.selected,
              eyebrow: args.eyebrow,
            );
          },
        ),
        GoRoute(
          path: AppRouter.summaryPath,
          builder: (context, state) {
            final args = state.extra! as SummaryArgs;
            return withSeatHold(
              SummaryPage(journey: args.journey, passengers: args.passengers),
              bloc: holdBloc,
              auth: authBloc,
            );
          },
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      MaterialApp.router(
        theme: AppTheme.light,
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
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

  /// Coche la confirmation d'exactitude, seule condition bloquante du
  /// formulaire depuis la revue Kora du 29 août 2026.
  Future<void> confirmAccuracy(WidgetTester tester) async {
    await tester.tap(
      find.text('Je confirme l’exactitude des informations saisies.'),
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

  testWidgets('pré-remplit le premier passager avec le titulaire', (
    tester,
  ) async {
    await pumpForm(tester, passengers: 2);

    // Le compte ne garde qu'un nom entier : il est coupé au premier
    // espace, comme sur « Mes informations ».
    expect(find.text('Aïcha'), findsOneWidget);
    expect(find.text('HOUNKPÈ'), findsOneWidget);
    expect(find.text('aicha@example.com'), findsOneWidget);

    // Une seule occurrence de chaque : le second bloc reste vierge, c'est
    // une autre personne que le titulaire renseigne lui-même.
    expect(find.text('Passager 2'), findsOneWidget);
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
    // Le message nomme le voyageur et ses champs : « Complétez les champs
    // signalés » laissait croire, sur le formulaire international, que la
    // pièce d'identité bloquait encore.
    expect(
      find.textContaining('Passager 2 : Prénom, Nom, Téléphone'),
      findsOneWidget,
    );
    await settleToast(tester);
  });

  testWidgets('le formulaire international ne retient plus aucun champ', (
    tester,
  ) async {
    // La naissance a rejoint la pièce d'identité du côté des champs
    // facultatifs : le manifeste de frontière la réclame toujours, mais
    // c'est au comptoir de la compléter. Bloquer l'achat sur une date que
    // le voyageur ne sait pas toujours de tête coûtait des réservations
    // qu'aucune règle ne refusait.
    await pumpForm(tester, international: true);

    await tester.tap(find.text('Enregistrer et continuer'));
    await tester.pumpAndSettle();

    expect(
      find.textContaining('Vérifiez ces champs avant de continuer'),
      findsNothing,
    );
    // Seule la case de confirmation retient encore.
    expect(
      find.text('Cochez la case de confirmation pour continuer.'),
      findsWidgets,
    );
    await settleToast(tester);
  });

  testWidgets('signale une adresse e-mail mal formée', (tester) async {
    await pumpForm(tester);

    final email = find.byType(TextField).last;
    await tester.enterText(email, 'aicha.example.com');
    await tester.tap(find.text('Enregistrer et continuer'));
    await tester.pumpAndSettle();

    expect(find.text('Adresse e-mail invalide'), findsOneWidget);
    await settleToast(tester);
  });

  testWidgets('accepte un formulaire complet', (tester) async {
    await pumpForm(tester);
    await confirmAccuracy(tester);

    await tester.tap(find.text('Enregistrer et continuer'));
    await tester.pumpAndSettle();

    // Le premier passager est pré-rempli : rien à signaler.
    expect(find.textContaining('requis'), findsNothing);
    // Le formulaire valide mène au récapitulatif.
    expect(find.text('Vérifiez votre voyage'), findsOneWidget);
  });

  group('formulaire international', () {
    testWidgets('reste simple tant que le trajet ne quitte pas le pays', (
      tester,
    ) async {
      await pumpForm(tester);

      expect(find.text('Qui voyage ?'), findsOneWidget);
      expect(find.text('E-mail (optionnel)'), findsOneWidget);
      // Aucun champ de frontière : réclamer un passeport pour un
      // Cotonou → Malanville n'aurait aucun sens.
      expect(find.text('Type de document (facultatif)'), findsNothing);
      expect(find.text('Nationalité'), findsNothing);
    });

    testWidgets('bascule tout seul dès qu’une frontière est franchie', (
      tester,
    ) async {
      await pumpForm(tester, international: true);

      expect(find.text('Identités internationales'), findsOneWidget);
      expect(find.text('Type de document (facultatif)'), findsOneWidget);
      expect(find.text('Numéro du document (facultatif)'), findsOneWidget);
      expect(find.text('Nationalité'), findsOneWidget);
      expect(find.text('Naissance (facultatif)'), findsOneWidget);
      // Le courriel facultatif cède la place : la maquette ne l'affiche
      // pas sur ce formulaire-là.
      expect(find.text('E-mail (optionnel)'), findsNothing);
      expect(
        find.text('Ces informations servent au manifeste de frontière.'),
        findsOneWidget,
      );
    });

    testWidgets('laisse passer sans date de naissance', (tester) async {
      await pumpForm(tester, international: true);

      // Identité déjà pré-remplie, naissance vide : il ne reste que la
      // case de confirmation à cocher, et le parcours continue.
      await tester.tap(
        find.text(
          'Je confirme l’exactitude des informations '
          'saisies.',
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Enregistrer et continuer'));
      await tester.pumpAndSettle();

      expect(find.text('Vérifiez votre voyage'), findsOneWidget);
    });

    testWidgets('ne réclame plus la pièce d’identité', (tester) async {
      // Revue Kora du 29 août 2026 : le document est facultatif, pour le
      // voyageur dont la pièce est expirée, perdue ou en renouvellement.
      await pumpForm(tester, international: true);

      await tester.tap(find.text('Enregistrer et continuer'));
      await tester.pumpAndSettle();

      expect(find.text('Numéro requis'), findsNothing);
      await settleToast(tester);
    });

    testWidgets('ne propose plus le CIP', (tester) async {
      // Retiré de la liste à la revue Kora. Le serveur l'accepte encore,
      // mais l'app cesse de l'offrir.
      await pumpForm(tester, international: true);

      await tester.tap(find.text('Non renseigné'));
      await tester.pumpAndSettle();

      expect(find.text('Passeport'), findsWidgets);
      expect(find.text('Carte biométrique'), findsWidgets);
      expect(find.text('Carte consulaire'), findsWidgets);
      expect(find.text('CIP'), findsNothing);
    });
  });

  group('revue Kora du 29 août 2026', () {
    testWidgets('sans la confirmation d’exactitude, rien ne part', (
      tester,
    ) async {
      await pumpForm(tester);

      // Le premier voyageur est pré-rempli : la saisie est complète, et
      // seule la case non cochée retient encore.
      await tester.tap(find.text('Enregistrer et continuer'));
      await tester.pumpAndSettle();

      expect(
        find.text('Cochez la case de confirmation pour continuer.'),
        findsWidgets,
      );
      expect(find.text('Vérifiez votre voyage'), findsNothing);
      await settleToast(tester);
    });

    testWidgets('sans document mais avec la confirmation, le parcours passe', (
      tester,
    ) async {
      // Le critère d'acceptation du ticket, mot pour mot.
      await pumpForm(tester, international: true);

      // La naissance reste due au manifeste ; la pièce, non. Le bouton de
      // confirmation du calendrier porte la date choisie, pas un « OK ».
      await tester.tap(find.text('Choisir'));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(KoraButton).last);
      await tester.pumpAndSettle();

      // Aucun type ni numéro de document saisi.
      expect(find.text('Non renseigné'), findsOneWidget);

      await confirmAccuracy(tester);
      await tester.tap(find.text('Enregistrer et continuer'));
      await tester.pumpAndSettle();

      expect(find.text('Vérifiez votre voyage'), findsOneWidget);
    });

    testWidgets('la vaccination se coche voyageur par voyageur', (
      tester,
    ) async {
      // Deux voyageurs sur un même billet n'ont pas le même carnet : la
      // case du premier ne doit rien décider pour le second.
      await pumpForm(tester, passengers: 2, international: true, height: 2400);

      final boxes = find.byType(Checkbox);
      // Deux cases de vaccination, plus la confirmation d'exactitude.
      expect(boxes, findsNWidgets(3));

      await tester.tap(find.text('Vacciné contre la fièvre jaune').first);
      await tester.pumpAndSettle();

      // Le second bloc reste vierge : rien ne s'est propagé.
      expect(tester.widget<Checkbox>(boxes.at(0)).value, isTrue);
      expect(tester.widget<Checkbox>(boxes.at(1)).value, isFalse);
    });

    testWidgets('un trajet intérieur ne demande pas la vaccination', (
      tester,
    ) async {
      // Le certificat international de vaccination n'a pas cours sur un
      // Cotonou–Parakou : la case y faisait cocher une déclaration sans
      // objet, que personne ne contrôlerait à l'embarquement.
      await pumpForm(tester, passengers: 2);

      expect(find.text('Vacciné contre la fièvre jaune'), findsNothing);
      // Reste la seule confirmation d'exactitude, commune aux deux
      // voyageurs.
      expect(find.byType(Checkbox), findsOneWidget);
    });

    testWidgets('les champs obligatoires portent une astérisque', (
      tester,
    ) async {
      // Ce que la validation exige se voit avant d'être appris par un
      // message d'erreur, formulaire déjà rempli.
      await pumpForm(tester);

      expect(find.text('Prénom *'), findsOneWidget);
      expect(find.text('Nom *'), findsOneWidget);
      expect(find.text('Téléphone *'), findsOneWidget);

      // L'e-mail n'est pas exigé : la validation ne vérifie que sa forme.
      expect(find.text('E-mail (optionnel)'), findsOneWidget);
    });
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
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: withSeatHold(
          PassengerFormPage(
            journey: Journey(outbound: Itinerary.direct(departure)),
          ),
          bloc: holdBloc,
          auth: authBloc,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Aucun siège sélectionné'), findsOneWidget);
  });
}
