// Les billets du groupe (BUS-17) : un par voyageur, chacun son QR.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/group_ticket.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/group_tickets_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/group_tickets_page.dart';
import 'package:kora_app_passenger/features/booking/presentation/widgets/kora_qr.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  setUpAll(() => AppLocalization.preloadAll());

  const tickets = [
    GroupTicket(
      id: 't-1',
      shortCode: 'KE-20260907-78342',
      status: GroupTicketStatus.issued,
      qrCode: 'qr-1',
      passengerName: 'Aïcha HOUNKPÈ',
      seatCode: '4A',
    ),
    GroupTicket(
      id: 't-2',
      shortCode: 'KE-20260907-78343',
      status: GroupTicketStatus.boarded,
      qrCode: 'qr-2',
      passengerName: 'Kofi MENSAH',
      seatCode: '4B',
    ),
  ];

  Future<void> pump(
    WidgetTester tester, {
    required StubReservationRepository repository,
    GroupTicketsArgs? args = const GroupTicketsArgs(
      reservationId: 'res-1',
      passengers: 2,
    ),
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
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
        home: BlocProvider<GroupTicketsCubit>(
          create: (_) => GroupTicketsCubit(repository),
          child: GroupTicketsPage(args: args),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('liste un billet par voyageur, avec son siège', (tester) async {
    await pump(tester, repository: StubReservationRepository(tickets: tickets));

    expect(find.text('Billets du groupe'), findsOneWidget);
    expect(find.text('2 voyageurs · 2 QR Codes'.toUpperCase()), findsOneWidget);
    expect(find.text('Aïcha HOUNKPÈ · 4A'), findsOneWidget);
    expect(find.text('Kofi MENSAH · 4B'), findsOneWidget);
  });

  testWidgets('chaque billet porte sa référence et son statut', (tester) async {
    // Les statuts sont propres à chaque billet : la validation de l'un
    // n'affecte pas les autres, et c'est tout l'intérêt de l'écran.
    await pump(tester, repository: StubReservationRepository(tickets: tickets));

    expect(find.text('KE-20260907-78342 · Prêt'), findsOneWidget);
    expect(find.text('KE-20260907-78343 · Embarqué'), findsOneWidget);
  });

  testWidgets('rappelle que chaque QR est indépendant', (tester) async {
    await pump(tester, repository: StubReservationRepository(tickets: tickets));

    expect(
      find.textContaining('La validation d’un billet n’affecte pas'),
      findsOneWidget,
    );
  });

  testWidgets('le billet d’un voyageur montre son QR, pas sa référence', (
    tester,
  ) async {
    // La douchette du convoyeur attend la charge du serveur : dessiner la
    // référence à la place rendrait le billet illisible au contrôle.
    await pump(tester, repository: StubReservationRepository(tickets: tickets));

    await tester.tap(find.text('Aïcha HOUNKPÈ · 4A'));
    await tester.pumpAndSettle();

    final qr = tester.widget<KoraQr>(find.byType(KoraQr));
    expect(qr.payload, 'qr-1');
  });

  testWidgets('chaque voyageur a son propre code', (tester) async {
    await pump(tester, repository: StubReservationRepository(tickets: tickets));

    await tester.tap(find.text('Kofi MENSAH · 4B'));
    await tester.pumpAndSettle();

    expect(tester.widget<KoraQr>(find.byType(KoraQr)).payload, 'qr-2');
  });

  testWidgets('une liste vide annonce une émission en cours', (tester) async {
    // L'émission suit le paiement : arriver avant qu'elle finisse rend une
    // liste vide. Dire « aucun billet » ferait croire à une perte.
    await pump(tester, repository: StubReservationRepository());

    expect(
      find.textContaining('billets sont en cours d’émission'),
      findsOneWidget,
    );
  });

  testWidgets('un échec montre le message du serveur', (tester) async {
    await pump(
      tester,
      repository: StubReservationRepository(
        failure: const ServerFailure('Billets indisponibles.'),
      ),
    );

    expect(find.text('Billets indisponibles.'), findsOneWidget);
  });

  testWidgets('sans réservation, l’écran ne fabrique rien', (tester) async {
    await pump(tester, repository: StubReservationRepository(), args: null);

    expect(find.text('Billets du groupe'), findsNothing);
  });
}
