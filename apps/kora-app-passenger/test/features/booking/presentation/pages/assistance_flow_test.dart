// Confier la réservation au support KORA (MEZ-81) : du récapitulatif au
// suivi.
//
// Le parcours nominal, le nouvel essai après une coupure réseau, WhatsApp
// absent, le retour depuis WhatsApp, les états du dossier et le retour au
// paiement en ligne.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/assisted_booking.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/passenger.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/assistance_cubits.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/group_quote_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/assistance_request_page.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/assistance_status_page.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/summary_page.dart';

import '../../../../support/assistance_harness.dart';
import '../../../../support/seat_hold_harness.dart';

void main() {
  late StubReservationRepository reservations;
  late StubAssistedBookingRepository assistance;
  late SeatHoldBloc holdBloc;
  late AuthBloc auth;
  late Departure departure;
  late Seat seat;

  /// Les liens que l'écran a tenté d'ouvrir, et ce que le téléphone répond.
  late List<Uri> launched;
  late bool launchSucceeds;

  setUpAll(() async {
    await AppLocalization.preloadAll();
    await initializeDateFormatting(KoraDateFormats.locale);
  });

  setUp(() async {
    reservations = StubReservationRepository();
    assistance = StubAssistedBookingRepository();
    holdBloc = SeatHoldBloc(reservations);
    addTearDown(holdBloc.close);
    auth = await openedSession();
    addTearDown(auth.close);

    getIt.registerFactory<AssistanceRequestCubit>(
      () => AssistanceRequestCubit(assistance),
    );
    getIt.registerFactory<AssistanceStatusCubit>(
      () => AssistanceStatusCubit(assistance),
    );

    launched = [];
    launchSucceeds = true;

    final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
    final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');
    departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;
    seat = mockSeatsFor(
      departure,
    ).firstWhere((seat) => seat.status == SeatStatus.free);
  });

  tearDown(() => getIt.reset());

  List<Passenger> passengers() => [
    Passenger(
      firstName: 'Aïcha',
      lastName: 'HOUNKPÈ',
      phone: '+229 01 97 42 18 60',
      seat: seat,
    ),
  ];

  Widget wrap(Widget child) => withSeatHold(
    child,
    bloc: holdBloc,
    auth: auth,
    groupQuote: GroupQuoteCubit(reservations),
  );

  /// Le tunnel réduit à ce qui compte ici : le plan (où ramène un siège
  /// pris), le récapitulatif, la demande, le suivi et le paiement.
  Future<GoRouter> pumpFlow(
    WidgetTester tester, {
    String initialLocation = AppRouter.seatsPath,
    Object? initialExtra,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1600 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final router = GoRouter(
      initialLocation: initialLocation,
      initialExtra: initialExtra,
      routes: [
        GoRoute(
          path: AppRouter.seatsPath,
          builder: (context, state) => Scaffold(
            body: TextButton(
              onPressed: () => context.push(AppRouter.summaryPath),
              child: const Text('plan'),
            ),
          ),
        ),
        GoRoute(
          path: AppRouter.summaryPath,
          builder: (context, state) =>
              wrap(SummaryPage(departure: departure, passengers: passengers())),
        ),
        GoRoute(
          path: AppRouter.assistanceRequestPath,
          builder: (context, state) =>
              wrap(AssistanceRequestPage(args: state.extra as AssistanceArgs?)),
        ),
        GoRoute(
          path: AppRouter.assistanceStatusPath,
          builder: (context, state) => wrap(
            AssistanceStatusPage(
              args: state.extra as AssistanceStatusArgs?,
              launcher: (uri) async {
                launched.add(uri);
                return launchSucceeds;
              },
            ),
          ),
        ),
        GoRoute(
          path: AppRouter.paymentMethodPath,
          builder: (context, state) => const Scaffold(body: Text('paiement')),
        ),
        GoRoute(
          path: '/home',
          builder: (context, state) => const Scaffold(body: Text('accueil')),
        ),
        GoRoute(
          path: '/voyages',
          builder: (context, state) => const Scaffold(body: Text('voyages')),
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
    return router;
  }

  /// Laisse les Blocs finir : celui des sièges tourne en temps réel, hors
  /// du temps simulé du test, et ses réponses repassent par l'écran.
  Future<void> settle(WidgetTester tester) async {
    for (var i = 0; i < 15; i++) {
      await tester.runAsync(
        () => Future<void>.delayed(const Duration(milliseconds: 10)),
      );
      await tester.pump();
    }
    await tester.pumpAndSettle();
  }

  /// Laisse passer le toast de KoraToaster : entrée, pause, sortie.
  Future<void> clearToast(WidgetTester tester) async {
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
  }

  Future<void> openRequest(WidgetTester tester) async {
    await pumpFlow(tester);
    await tester.tap(find.text('plan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Avec un agent KORA'));
    await settle(tester);
  }

  Future<void> send(WidgetTester tester) async {
    await tester.tap(find.text('Envoyer via WhatsApp'));
    await settle(tester);
  }

  testWidgets('le récapitulatif propose les deux choix, au même endroit', (
    tester,
  ) async {
    await pumpFlow(tester);
    await tester.tap(find.text('plan'));
    await tester.pumpAndSettle();

    expect(find.text('Comment souhaitez-vous payer ?'), findsOneWidget);
    expect(find.text('En ligne'), findsOneWidget);
    expect(find.text('Avec un agent KORA'), findsOneWidget);
  });

  testWidgets('parcours nominal : bloquer, envoyer, ouvrir WhatsApp, '
      'confirmer', (tester) async {
    await openRequest(tester);

    // Plus d'agence à choisir : le canal, et le contact qu'on rappellera.
    expect(find.text('Comment vous faire aider ?'), findsOneWidget);
    expect(find.textContaining('+2290197421860'), findsOneWidget);

    await send(tester);

    // La même réservation : bloquée, voyageurs rattachés, puis confiée.
    expect(
      reservations.calls,
      containsAllInOrder(['holdGroup:1', 'passengers:1']),
    );
    expect(assistance.calls, contains('request:whatsapp:+2290197421860'));
    expect(assistance.idempotencyKeys, ['kora-assist-hold-groupe-1']);
    expect(assistance.calls, contains('remember:KBR-7X2K9P4Q'));

    // Le paiement n'est pas parti depuis le récapitulatif resté dessous.
    expect(find.text('paiement'), findsNothing);
    // Le décompte est coupé : l'agence affectée confirmera les sièges.
    expect(holdBloc.state.isHeld, isFalse);

    // WhatsApp s'ouvre sur le message déjà rédigé.
    expect(launched.single.host, 'wa.me');

    // La confirmation : référence et validité.
    expect(find.text('KBR-7X2K9P4Q'), findsWidgets);
    expect(find.textContaining('Départ le'), findsOneWidget);
    expect(find.text('EN ATTENTE'), findsOneWidget);
    // Personne n'a encore pris le dossier : la carte le dit.
    expect(
      find.textContaining('En cours d’affectation'),
      findsOneWidget,
    );
    expect(assistance.saved.single.seats.single.seatId, seat.id);
  });

  testWidgets('une coupure réseau le dit, et le nouvel essai garde la même '
      'clé', (tester) async {
    assistance.nextRequestFailures.add(
      const NetworkFailure('Pas de connexion internet.'),
    );
    await openRequest(tester);

    await send(tester);
    expect(find.text('Pas de connexion internet.'), findsOneWidget);
    expect(find.text('Réserver avec un agent'), findsOneWidget);
    await clearToast(tester);

    await tester.tap(find.text('Envoyer via WhatsApp'));
    await settle(tester);

    // Rejouer la même clé : le serveur rend le dossier au lieu d'en ouvrir
    // un second.
    expect(assistance.idempotencyKeys, [
      'kora-assist-hold-groupe-1',
      'kora-assist-hold-groupe-1',
    ]);
    // Les sièges n'ont été bloqués qu'une fois ; seuls les voyageurs
    // repartent.
    expect(
      reservations.calls.where((call) => call.startsWith('holdGroup')),
      hasLength(1),
    );
    expect(find.text('KBR-7X2K9P4Q'), findsWidgets);
  });

  testWidgets('un siège pris au blocage ramène au plan, sans envoi', (
    tester,
  ) async {
    reservations.nextHoldFailures.add(
      const SeatUnavailableFailure('Le siège 4A vient d’être pris.'),
    );
    await openRequest(tester);

    await send(tester);

    expect(find.text('plan'), findsOneWidget);
    expect(
      assistance.calls.where((call) => call.startsWith('request')),
      isEmpty,
    );
  });

  testWidgets('WhatsApp absent : appeler ou copier le message', (tester) async {
    launchSucceeds = false;
    await openRequest(tester);

    await send(tester);

    expect(find.textContaining('WhatsApp n’a pas pu s’ouvrir'), findsOneWidget);
    expect(find.text('Appeler KORA'), findsOneWidget);
    expect(find.text('Copier le message'), findsOneWidget);
    // La référence reste là : rien n'est perdu.
    expect(find.text('KBR-7X2K9P4Q'), findsWidgets);
  });

  testWidgets('le voyageur peut demander un appel plutôt', (tester) async {
    await openRequest(tester);

    await tester.tap(find.text('Appel'));
    await tester.pump();
    await tester.tap(find.text('Envoyer et appeler KORA'));
    await settle(tester);

    expect(assistance.calls, contains('request:call:+2290197421860'));
    expect(launched.single.scheme, 'tel');
  });

  group('suivi', () {
    Future<void> openStatus(WidgetTester tester) async {
      assistance.saved.add(assistanceRecord());
      await pumpFlow(
        tester,
        initialLocation: AppRouter.assistanceStatusPath,
        initialExtra: const AssistanceStatusArgs(
          reservationId: 'hold-groupe-1',
        ),
      );
      await settle(tester);
    }

    testWidgets('le retour depuis WhatsApp relit le dossier', (tester) async {
      await openStatus(tester);
      expect(find.text('EN ATTENTE'), findsOneWidget);

      // Pendant que le voyageur était dans WhatsApp, un agent a pris le
      // dossier.
      assistance.status = AssistanceStatus.inProgress;
      // L'app passe en arrière-plan le temps de WhatsApp, puis revient.
      for (final state in [
        AppLifecycleState.inactive,
        AppLifecycleState.hidden,
        AppLifecycleState.paused,
        AppLifecycleState.hidden,
        AppLifecycleState.inactive,
        AppLifecycleState.resumed,
      ]) {
        tester.binding.handleAppLifecycleStateChanged(state);
      }
      await settle(tester);

      expect(find.text('EN COURS'), findsOneWidget);
      // Un agent a la main : le paiement en ligne n'est plus proposé.
      expect(find.text('Continuer vers le paiement'), findsNothing);
    });

    testWidgets('finalisé : les billets sont dans Voyages', (tester) async {
      assistance.status = AssistanceStatus.finalized;
      await openStatus(tester);

      expect(find.text('FINALISÉE'), findsOneWidget);
      await tester.tap(find.text('Voir mes voyages'));
      await tester.pumpAndSettle();
      expect(find.text('voyages'), findsOneWidget);
    });

    for (final (status, title) in [
      (AssistanceStatus.expired, 'EXPIRÉE'),
      (AssistanceStatus.refused, 'REFUSÉE'),
      (AssistanceStatus.unavailable, 'INDISPONIBLE'),
    ]) {
      testWidgets('$title : proposer de refaire une réservation', (
        tester,
      ) async {
        assistance.status = status;
        await openStatus(tester);

        expect(find.text(title), findsOneWidget);
        expect(find.text('Ouvrir WhatsApp'), findsNothing);
        await tester.tap(find.text('Refaire une réservation'));
        await tester.pumpAndSettle();
        expect(find.text('accueil'), findsOneWidget);
      });
    }

    testWidgets('payer en ligne reprend les sièges puis ouvre le paiement', (
      tester,
    ) async {
      await openStatus(tester);

      await tester.tap(find.text('Continuer vers le paiement'));
      await settle(tester);

      expect(assistance.calls, contains('resume:hold-groupe-1:1'));
      expect(assistance.idempotencyKeys, ['kora-resume-hold-groupe-1']);
      expect(holdBloc.state.isHeld, isTrue);
      expect(find.text('paiement'), findsOneWidget);
    });

    testWidgets('un siège parti à la reprise le dit, sans quitter le suivi', (
      tester,
    ) async {
      assistance.resumeFailure = const SeatUnavailableFailure(
        'Le siège 4A n’est plus disponible sur ce segment.',
        seatLabel: '4A',
      );
      await openStatus(tester);

      await tester.tap(find.text('Continuer vers le paiement'));
      await settle(tester);

      expect(
        find.text('Le siège 4A n’est plus disponible sur ce segment.'),
        findsOneWidget,
      );
      expect(find.text('paiement'), findsNothing);
      expect(find.text('KBR-7X2K9P4Q'), findsWidgets);
      await clearToast(tester);
    });

    testWidgets('hors ligne, la référence gardée reste affichée', (
      tester,
    ) async {
      assistance.fetchFailure = const NetworkFailure(
        'Pas de connexion internet.',
      );
      await openStatus(tester);

      expect(find.text('KBR-7X2K9P4Q'), findsWidgets);
      expect(find.text('Réessayer'), findsOneWidget);
    });
  });
}
