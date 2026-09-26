// Le détail d'un billet : tout ce qu'on peut en faire tient dans une seule
// liste — le PDF officiel compris, quand la réservation n'a qu'un billet.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/ticket.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/cancel_ticket_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/ticket_document_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/ticket_detail_page.dart';

import '../../../../support/seat_hold_harness.dart';
import '../../../../support/stub_ticket_document_repository.dart';

void main() {
  final today = DateTime(2026, 8, 18);
  late Ticket ticket;

  setUpAll(() async {
    await AppLocalization.preloadAll();
    await initializeDateFormatting(KoraDateFormats.locale);
  });

  setUp(() => ticket = mockTickets(today).first);

  late StubReservationRepository reservations;
  late CancelTicketCubit cancel;

  Future<TicketDocumentCubit> pumpDetail(
    WidgetTester tester, {
    required List<String> ticketIds,
    StubTicketDocumentRepository? repository,
    TicketStatus status = TicketStatus.confirmed,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1400 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final cubit = TicketDocumentCubit(
      repository ?? StubTicketDocumentRepository(),
    );
    addTearDown(cubit.close);

    reservations = StubReservationRepository();
    cancel = CancelTicketCubit(reservations, BookingRefresh());
    addTearDown(cancel.close);

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
        home: TicketDetailPage(
          ticket: Ticket(
            reference: ticket.reference,
            departure: ticket.departure,
            seat: ticket.seat,
            status: status,
            reservationId: 'resa-1',
            ticketIds: ticketIds,
          ),
          today: today,
          documents: cubit,
          cancel: cancel,
        ),
      ),
    );
    await tester.pumpAndSettle();
    return cubit;
  }

  testWidgets('un seul billet : le PDF officiel entre dans la liste', (
    tester,
  ) async {
    await pumpDetail(tester, ticketIds: ['ticket-1']);

    // Les actions du billet et celles du PDF, dans le même bloc.
    expect(find.text('Afficher le QR Code'), findsOneWidget);
    expect(find.text('Télécharger le billet (PDF)'), findsOneWidget);
    expect(find.text('Billet officiel KORA'), findsOneWidget);
    expect(find.text('Suivre le bus'), findsOneWidget);
    expect(find.byType(KoraMenuList), findsOneWidget);
    // Plus de pile de boutons sous la liste.
    expect(find.byType(KoraButton), findsNothing);
  });

  testWidgets('billet déjà enregistré : l’ouvrir, le garder', (tester) async {
    await pumpDetail(
      tester,
      ticketIds: ['ticket-1'],
      repository: StubTicketDocumentRepository()
        ..savedDocument = StubTicketDocumentRepository.documentFor('ticket-1'),
    );

    expect(find.text('Ouvrir le billet'), findsOneWidget);
    expect(find.text('Enregistré sur ce téléphone'), findsOneWidget);
    expect(find.text('Enregistrer une copie'), findsOneWidget);
    expect(find.text('Télécharger à nouveau'), findsOneWidget);
  });

  testWidgets('plusieurs billets : la liste reste courte', (tester) async {
    // Chaque voyageur a le sien : c'est l'écran des billets du groupe qui
    // les sert un par un.
    await pumpDetail(tester, ticketIds: ['ticket-1', 'ticket-2']);

    expect(find.text('Afficher le QR Code'), findsOneWidget);
    expect(find.text('Partager mon billet'), findsOneWidget);
    expect(find.text('Télécharger le billet (PDF)'), findsNothing);
    expect(find.text('Ouvrir le billet'), findsNothing);
  });

  testWidgets('annuler demande confirmation, puis annule la réservation', (
    tester,
  ) async {
    await pumpDetail(tester, ticketIds: ['ticket-1']);

    await tester.tap(find.text('Annuler le billet'));
    await tester.pumpAndSettle();

    // Rien n'est parti tant que le voyageur n'a pas confirmé.
    expect(find.text('Annuler ce billet ?'), findsOneWidget);
    expect(reservations.calls, isEmpty);

    await tester.tap(find.text('Garder'));
    await tester.pumpAndSettle();
    expect(reservations.calls, isEmpty);

    await tester.tap(find.text('Annuler le billet'));
    await tester.pumpAndSettle();
    // Le bouton rouge de la fenêtre, pas la ligne de la liste.
    await tester.tap(find.widgetWithText(KoraButton, 'Annuler'));
    await tester.pumpAndSettle();

    expect(reservations.calls, ['cancelBooking:resa-1']);

    // Le toast de confirmation : entrée, pause, sortie.
    await tester.pump(const Duration(milliseconds: 300));
    expect(find.textContaining('Billet annulé'), findsOneWidget);
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
  });

  testWidgets('un billet déjà annulé ne propose plus de l’annuler', (
    tester,
  ) async {
    await pumpDetail(
      tester,
      ticketIds: ['ticket-1'],
      status: TicketStatus.cancelled,
    );

    expect(find.text('Annuler le billet'), findsNothing);
  });
}
