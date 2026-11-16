// Tests de l'onglet « Mes voyages » : les trois onglets et leurs
// compteurs, le tri de chaque liste, les états vide et erreur, et le fait
// que l'écran s'affiche aussi bien en français qu'en anglais.

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
import 'package:kora_app_passenger/core/widgets/brand_mini.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/assisted_booking.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/ticket.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_tickets_usecase.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/assistance_cubits.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/cancel_ticket_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/tickets_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/tickets_page.dart';
import 'package:mocktail/mocktail.dart';

import '../../../../support/assistance_harness.dart';

class _MockGetTickets extends Mock implements GetTicketsUseCase {}

void main() {
  late _MockGetTickets getTickets;
  late StubAssistedBookingRepository assistance;

  /// Un jeudi quelconque, figé : les libellés « dans 7 jours » dépendent
  /// du jour où tourne le test.
  final today = DateTime(2026, 8, 18);
  final tickets = mockTickets(today);

  setUpAll(() async {
    registerFallbackValue(const NoParams());
    // Les cartes affichent des dates : sans les données de locale, le
    // premier DateFormat lèverait une exception.
    await initializeDateFormatting();
  });

  setUp(() {
    getTickets = _MockGetTickets();
    when(() => getTickets(any())).thenAnswer((_) async => Right(tickets));

    // Même branchement que l'app : l'écran récupère son bloc dans le
    // service locator. On y met celui du test, avec son horloge figée.
    getIt.registerFactory<TicketsBloc>(
      () => TicketsBloc(getTickets, now: () => today),
    );

    // Le signal des réservations qui changent : la liste s'y accroche.
    getIt.registerLazySingleton<BookingRefresh>(BookingRefresh.new);

    // Aucune demande en agence par défaut : la section reste invisible.
    assistance = StubAssistedBookingRepository();
    getIt.registerFactory<AssistanceRequestsCubit>(
      () => AssistanceRequestsCubit(assistance),
    );
  });

  tearDown(() => getIt.reset());

  /// Monte l'écran seul, sans routeur ni service locator : le bloc est
  /// fourni à la main pour piloter le cas testé.
  Future<void> pumpTickets(
    WidgetTester tester, {
    Locale locale = const Locale('fr'),
    VoidCallback? onOpenNotifications,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1200 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: locale,
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: TicketsPage(onOpenNotifications: onOpenNotifications),
      ),
    );
    await tester.pumpAndSettle();
  }

  /// Les quatre puces ne tiennent pas toutes à l'écran : on amène celle
  /// qu'on veut avant d'appuyer.
  Future<void> openTab(WidgetTester tester, String label) async {
    await tester.ensureVisible(find.text(label));
    await tester.pumpAndSettle();
    await tester.tap(find.text(label));
    await tester.pumpAndSettle();
  }

  testWidgets('affiche les onglets avec leurs compteurs', (tester) async {
    await pumpTickets(tester);

    // Les puces passent en capitales : `.tag { text-transform: uppercase }`
    // dans la maquette, appliqué par KoraTag.
    expect(find.text('À VENIR · 1'), findsOneWidget);
    // Les demandes confiées à une agence ont leur propre onglet.
    expect(find.text('EN ATTENTE · 0'), findsOneWidget);
    expect(find.text('PASSÉS · 3'), findsOneWidget);
    expect(find.text('ANNULÉS · 1'), findsOneWidget);
  });

  testWidgets('ouvre sur le billet à venir', (tester) async {
    await pumpTickets(tester);

    expect(find.text('Cotonou → Malanville'), findsOneWidget);
    expect(find.text('CONFIRMÉ'), findsOneWidget);
    // Le billet mocké part dans 7 jours.
    expect(find.textContaining('dans 7 jours'), findsOneWidget);
    expect(find.textContaining('Siège 4A'), findsOneWidget);
  });

  testWidgets('plusieurs billets : le lien passe au pluriel', (tester) async {
    // Une réservation à deux sièges : le lien mène à la liste des billets.
    final one = tickets.firstWhere(
      (ticket) => ticket.status == TicketStatus.confirmed,
    );
    when(() => getTickets(any())).thenAnswer(
      (_) async => Right([
        Ticket(
          reference: one.reference,
          departure: one.departure,
          seat: one.seat,
          status: one.status,
          reservationId: 'resa-1',
          ticketIds: const ['ticket-1', 'ticket-2'],
        ),
      ]),
    );

    await pumpTickets(tester);

    expect(find.text('Voir les billets →'), findsOneWidget);
    expect(find.text('Voir le billet →'), findsNothing);
  });

  testWidgets('la liste dit le genre du voyage', (tester) async {
    // Aller simple, aller-retour et correspondance se ressemblaient tous
    // dans la liste : « Cotonou → Natitingou » et rien d'autre. Le
    // voyageur ne savait pas lequel l'oblige à changer de bus.
    final one = tickets.firstWhere(
      (ticket) => ticket.status == TicketStatus.confirmed,
    );
    when(() => getTickets(any())).thenAnswer(
      (_) async => Right([
        Ticket(
          reference: one.reference,
          departure: one.departure,
          seat: one.seat,
          status: one.status,
          reservationId: 'resa-1',
          // Deux billets : la carte montre le lien vers la liste, et non
          // le téléchargement du PDF, qui réclamerait son cubit.
          ticketIds: const ['ticket-1', 'ticket-2'],
          legs: const [
            TicketLeg(order: 1, origin: 'Cotonou', destination: 'Bohicon'),
            TicketLeg(order: 2, origin: 'Bohicon', destination: 'Natitingou'),
            TicketLeg(
              order: 3,
              origin: 'Natitingou',
              destination: 'Cotonou',
              isReturn: true,
            ),
          ],
        ),
      ]),
    );

    await pumpTickets(tester);

    expect(find.text('Aller-retour · 1 correspondance'), findsOneWidget);
  });

  testWidgets('un aller simple et direct n’annonce rien', (tester) async {
    // Le cas courant : une ligne de plus pour dire « rien de spécial »
    // encombrerait la liste.
    await pumpTickets(tester);

    expect(find.textContaining('Aller-retour'), findsNothing);
    expect(find.textContaining('correspondance'), findsNothing);
  });

  testWidgets('un seul billet : le lien reste au singulier', (tester) async {
    await pumpTickets(tester);

    expect(find.text('Voir le billet →'), findsOneWidget);
  });

  testWidgets('une réservation qui change relit la liste', (tester) async {
    // Le cas du billet qu'on vient de payer : l'onglet, resté en mémoire
    // derrière le tunnel, doit le voir sans déconnexion.
    await pumpTickets(tester);
    verify(() => getTickets(any())).called(1);

    getIt<BookingRefresh>().notify();
    await tester.pumpAndSettle();

    verify(() => getTickets(any())).called(1);
  });

  testWidgets('la cloche ouvre les notifications', (tester) async {
    var opened = 0;
    await pumpTickets(tester, onOpenNotifications: () => opened++);

    await tester.tap(find.byType(NotificationButton));
    await tester.pumpAndSettle();

    expect(opened, 1);
  });

  testWidgets('bascule sur les voyages passés', (tester) async {
    await pumpTickets(tester);

    await openTab(tester, 'PASSÉS · 3');

    expect(find.text('TERMINÉ'), findsNWidgets(3));
    expect(find.text('CONFIRMÉ'), findsNothing);
  });

  testWidgets('trie l’historique du plus récent au plus ancien', (
    tester,
  ) async {
    await pumpTickets(tester);

    await openTab(tester, 'PASSÉS · 3');

    final past =
        tickets
            .where((ticket) => ticket.status == TicketStatus.completed)
            .toList()
          ..sort(
            (a, b) => b.departure.departsAt.compareTo(a.departure.departsAt),
          );

    // Le premier de la liste doit être le plus récent des trois.
    final firstRoute =
        '${past.first.departure.line.origin.name} → '
        '${past.first.departure.line.destination.name}';

    final routes = tester
        .widgetList<Text>(find.byType(Text))
        .map((text) => text.data)
        .whereType<String>()
        .where((value) => value.contains('→'))
        .toList();

    expect(routes.first, firstRoute);
  });

  testWidgets('annonce un onglet vide sans le faire disparaître', (
    tester,
  ) async {
    when(() => getTickets(any())).thenAnswer(
      (_) async => Right(
        tickets.where((t) => t.status == TicketStatus.confirmed).toList(),
      ),
    );

    await pumpTickets(tester);
    await openTab(tester, 'ANNULÉS · 0');

    expect(find.text('Aucune annulation'), findsOneWidget);
    // Les onglets restent affichés : on doit pouvoir revenir.
    expect(find.text('À VENIR · 1'), findsOneWidget);
  });

  testWidgets('affiche l’erreur et permet de réessayer', (tester) async {
    when(
      () => getTickets(any()),
    ).thenAnswer((_) async => const Left(ServerFailure('Serveur injoignable')));

    await pumpTickets(tester);

    expect(find.text('Vos billets n’ont pas pu être chargés'), findsOneWidget);
    expect(find.text('Serveur injoignable'), findsOneWidget);

    when(() => getTickets(any())).thenAnswer((_) async => Right(tickets));
    await tester.tap(find.text('Réessayer'));
    await tester.pumpAndSettle();

    expect(find.text('Cotonou → Malanville'), findsOneWidget);
  });

  testWidgets('s’affiche en anglais quand la langue est l’anglais', (
    tester,
  ) async {
    await pumpTickets(tester, locale: const Locale('en'));

    expect(find.text('My trips'), findsOneWidget);
    expect(find.text('UPCOMING · 1'), findsOneWidget);
    expect(find.text('CONFIRMED'), findsOneWidget);
    expect(find.text('Book a trip'), findsOneWidget);
    expect(find.textContaining('in 7 days'), findsOneWidget);
    expect(find.textContaining('Seat 4A'), findsOneWidget);
  });

  testWidgets('les demandes en agence ont leur onglet, sans billet', (
    tester,
  ) async {
    // Pas encore de billet pour elles : sans cet onglet, le voyageur ne
    // les retrouverait nulle part.
    assistance.saved.add(assistanceRecord());
    assistance.status = AssistanceStatus.inProgress;

    await pumpTickets(tester);
    await openTab(tester, 'EN ATTENTE · 1');

    expect(find.text('KBR-7X2K9P4Q'), findsOneWidget);
    expect(find.text('EN COURS'), findsOneWidget);
    expect(find.text('Voir la demande'), findsOneWidget);
    expect(find.text('Voir le billet'), findsNothing);
  });

  testWidgets('une demande sans suite se range dans les annulations', (
    tester,
  ) async {
    assistance.saved.add(assistanceRecord());
    assistance.status = AssistanceStatus.expired;

    await pumpTickets(tester);

    // Comptée avec les billets annulés, et affichée dans leur onglet.
    expect(find.text('EN ATTENTE · 0'), findsOneWidget);
    await openTab(tester, 'ANNULÉS · 2');

    expect(find.text('KBR-7X2K9P4Q'), findsOneWidget);
    expect(find.text('EXPIRÉE'), findsOneWidget);
    // L'onglet n'est pas vide : le message de vide n'a rien à y faire.
    expect(find.text('Aucune annulation'), findsNothing);
  });
}
