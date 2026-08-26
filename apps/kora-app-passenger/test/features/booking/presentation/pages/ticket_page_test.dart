// Le billet numérique : ce qu'il montre, et ce qu'il change quand le
// voyage est annulé.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/utils/date_formats.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/ticket.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/ticket_page.dart';

void main() {
  final cotonou = kMockCities.firstWhere((city) => city.id == 'ctn');
  final malanville = kMockCities.firstWhere((city) => city.id == 'mlv');

  late Ticket ticket;

  setUpAll(() async {
    await AppLocalization.preloadAll();
    await initializeDateFormatting(KoraDateFormats.locale);
  });

  setUp(() {
    final departure = mockDeparturesFor(
      SearchCriteria(
        origin: cotonou,
        destination: malanville,
        date: DateTime(2026, 8, 14),
      ),
    ).first;

    ticket = Ticket(
      reference: 'KE-20260814-78342',
      departure: departure,
      seat: const Seat(id: 's-4a', label: '4A', row: 4, column: 1),
      status: TicketStatus.confirmed,
    );
  });

  Future<void> pumpTicket(WidgetTester tester, {TicketArgs? args}) async {
    tester.view.physicalSize = const Size(390 * 3, 1400 * 3);
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
        home: TicketPage(args: args),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('montre le trajet, le siège et la référence', (tester) async {
    await pumpTicket(
      tester,
      args: TicketArgs(ticket: ticket, passengerName: 'A. HOUNKPÈ'),
    );

    expect(find.text('Votre billet'), findsOneWidget);
    expect(find.text(cotonou.code), findsOneWidget);
    expect(find.text(malanville.code), findsOneWidget);
    expect(find.text('4A'), findsOneWidget);
    expect(find.text('KE-20260814-78342'), findsOneWidget);
    expect(find.text('A. HOUNKPÈ'), findsOneWidget);
  });

  testWidgets('annonce que le billet est confirmé', (tester) async {
    await pumpTicket(tester, args: TicketArgs(ticket: ticket));

    expect(find.text('Voyage confirmé'), findsOneWidget);
    expect(find.text('Présentez ce QR Code au convoyeur'), findsOneWidget);
  });

  testWidgets('un billet annulé est barré, sans bandeau vert', (tester) async {
    // Un billet annulé doit se reconnaître sans lire : le tampon
    // remplace le bandeau qui invite à embarquer.
    await pumpTicket(
      tester,
      args: TicketArgs(
        ticket: Ticket(
          reference: ticket.reference,
          departure: ticket.departure,
          seat: ticket.seat,
          status: TicketStatus.cancelled,
        ),
      ),
    );

    // Deux fois : la pastille d'état et le tampon en surimpression — la
    // maquette porte les deux, `KoraTag` mettant son libellé en capitales.
    expect(find.text('ANNULÉ'), findsNWidgets(2));
    expect(find.text('Voyage confirmé'), findsNothing);
  });

  testWidgets('sans billet, propose de retourner aux voyages', (tester) async {
    await pumpTicket(tester);

    expect(find.text('Aucun billet à afficher'), findsOneWidget);
    expect(find.text('Voir mes voyages'), findsOneWidget);
  });

  testWidgets('rappelle que le billet fonctionne hors ligne', (tester) async {
    // Les gares KORA sont souvent en limite de couverture, et c'est
    // précisément là qu'on présente son billet.
    await pumpTicket(tester, args: TicketArgs(ticket: ticket));

    expect(find.textContaining('accessible hors ligne'), findsOneWidget);
  });
}
