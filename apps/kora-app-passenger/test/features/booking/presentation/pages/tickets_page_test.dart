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
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/ticket.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_tickets_usecase.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/tickets_bloc.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/tickets_page.dart';
import 'package:mocktail/mocktail.dart';

class _MockGetTickets extends Mock implements GetTicketsUseCase {}

void main() {
  late _MockGetTickets getTickets;

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
  });

  tearDown(() => getIt.reset());

  /// Monte l'écran seul, sans routeur ni service locator : le bloc est
  /// fourni à la main pour piloter le cas testé.
  Future<void> pumpTickets(
    WidgetTester tester, {
    Locale locale = const Locale('fr'),
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
        home: const TicketsPage(),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('affiche les trois onglets avec leurs compteurs', (tester) async {
    await pumpTickets(tester);

    // Les puces passent en capitales : `.tag { text-transform: uppercase }`
    // dans la maquette, appliqué par KoraTag.
    expect(find.text('À VENIR · 1'), findsOneWidget);
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

  testWidgets('bascule sur les voyages passés', (tester) async {
    await pumpTickets(tester);

    await tester.tap(find.text('PASSÉS · 3'));
    await tester.pumpAndSettle();

    expect(find.text('TERMINÉ'), findsNWidgets(3));
    expect(find.text('CONFIRMÉ'), findsNothing);
  });

  testWidgets('trie l’historique du plus récent au plus ancien', (
    tester,
  ) async {
    await pumpTickets(tester);

    await tester.tap(find.text('PASSÉS · 3'));
    await tester.pumpAndSettle();

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
    await tester.tap(find.text('ANNULÉS · 0'));
    await tester.pumpAndSettle();

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
}
