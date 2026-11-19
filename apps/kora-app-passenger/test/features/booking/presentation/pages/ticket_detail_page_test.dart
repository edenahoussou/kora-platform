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
    List<TicketLeg> legs = const [],
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
            legs: legs,
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

  testWidgets('une correspondance détaille ses bus', (tester) async {
    // Le titre annonce le voyage d'un bout à l'autre ; c'est ici qu'on
    // apprend où l'on descend, à quelle heure et sur quelle place.
    await pumpDetail(
      tester,
      ticketIds: ['ticket-1'],
      legs: [
        TicketLeg(
          order: 1,
          origin: 'Cotonou',
          destination: 'Bohicon',
          seatCode: '4A',
          departsAt: DateTime(2026, 8, 20, 6),
          arrivesAt: DateTime(2026, 8, 20, 7, 31),
        ),
        TicketLeg(
          order: 2,
          origin: 'Bohicon',
          destination: 'Natitingou',
          seatCode: '3C',
          departsAt: DateTime(2026, 8, 20, 8, 36),
          arrivesAt: DateTime(2026, 8, 20, 13, 14),
        ),
      ],
    );

    expect(find.text('Votre voyage, bus par bus'), findsOneWidget);
    expect(find.text('Cotonou → Bohicon'), findsOneWidget);
    expect(find.text('Bohicon → Natitingou'), findsOneWidget);
    // La place change d'un bus à l'autre.
    expect(find.text('SIÈGE 4A'), findsOneWidget);
    expect(find.text('SIÈGE 3C'), findsOneWidget);
    expect(find.text('08:36 → 13:14'), findsOneWidget);
  });

  testWidgets('l’aller et le retour ne se mélangent pas', (tester) async {
    // Les deux sens tiennent dans la même liste : sans un mot pour les
    // séparer, le billet annonçait un troisième bus qui repartait d’où
    // l’on venait, comme s’il fallait le prendre dans la foulée.
    await pumpDetail(
      tester,
      ticketIds: ['ticket-1'],
      legs: [
        TicketLeg(
          order: 1,
          origin: 'Cotonou',
          destination: 'Bohicon',
          seatCode: '4B',
          departsAt: DateTime(2026, 11, 16, 6),
          arrivesAt: DateTime(2026, 11, 16, 7, 31),
        ),
        TicketLeg(
          order: 2,
          origin: 'Bohicon',
          destination: 'Natitingou',
          seatCode: '4B',
          departsAt: DateTime(2026, 11, 16, 8, 36),
          arrivesAt: DateTime(2026, 11, 16, 13, 14),
        ),
        TicketLeg(
          order: 3,
          origin: 'Natitingou',
          destination: 'Cotonou',
          seatCode: '4B',
          departsAt: DateTime(2026, 11, 16, 17, 36),
          isReturn: true,
        ),
      ],
    );

    expect(find.text('ALLER'), findsOneWidget);
    expect(find.text('RETOUR'), findsOneWidget);
    expect(find.text('Natitingou → Cotonou'), findsOneWidget);
  });

  testWidgets('un aller simple ne porte aucun sens', (tester) async {
    // Rien à distinguer : un badge « Aller » solitaire ferait chercher
    // un retour qui n’existe pas.
    await pumpDetail(
      tester,
      ticketIds: ['ticket-1'],
      legs: [
        TicketLeg(order: 1, origin: 'Cotonou', destination: 'Bohicon'),
        TicketLeg(order: 2, origin: 'Bohicon', destination: 'Natitingou'),
      ],
    );

    expect(find.text('ALLER'), findsNothing);
    expect(find.text('RETOUR'), findsNothing);
  });

  testWidgets('un trajet direct ne détaille rien', (tester) async {
    await pumpDetail(tester, ticketIds: ['ticket-1']);

    expect(find.text('Votre voyage, bus par bus'), findsNothing);
  });
}
