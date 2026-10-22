// L'écran qui ouvre un billet à partir du seul identifiant de sa
// réservation.
//
// C'est la porte d'entrée des liens profonds : une notification ne
// transporte qu'une chaîne, jamais un billet déjà chargé. Trois issues
// comptent, et les trois arrivent — le billet est là, le réseau manque,
// ou la réservation n'est plus dans le compte.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/data/datasources/booking_mock_data.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/ticket.dart';
import 'package:kora_app_passenger/features/booking/domain/repositories/booking_repository.dart';
import 'package:kora_app_passenger/features/booking/domain/usecases/get_tickets_usecase.dart';
import 'package:kora_app_passenger/features/booking/presentation/pages/ticket_by_reservation_page.dart';
import 'package:mocktail/mocktail.dart';

class MockBookingRepository extends Mock implements BookingRepository {}

void main() {
  final today = DateTime(2026, 10, 15, 9);

  late MockBookingRepository repository;
  late GetTicketsUseCase getTickets;

  setUp(() {
    repository = MockBookingRepository();
    getTickets = GetTicketsUseCase(repository);
  });

  /// Les billets de démonstration, re-rattachés à une réservation : seul
  /// ce rattachement intéresse cet écran.
  final sample = mockTickets(today).first;

  Ticket ticket(String reservationId) => Ticket(
    reference: sample.reference,
    departure: sample.departure,
    seat: sample.seat,
    status: sample.status,
    reservationId: reservationId,
  );

  Future<void> pump(
    WidgetTester tester, {
    String uuid = 'reservation-1',
    void Function(Ticket, DateTime)? onOpenTicket,
    VoidCallback? onFallback,
  }) async {
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
        home: TicketByReservationPage(
          reservationUuid: uuid,
          getTickets: getTickets,
          today: today,
          onOpenTicket: onOpenTicket,
          onFallback: onFallback,
        ),
      ),
    );

    // Pas de `pumpAndSettle` : une fois le billet trouvé, l'écran garde son
    // indicateur de chargement — la route le remplace aussitôt par le
    // détail — et une animation qui tourne ne se stabilise jamais.
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
  }

  testWidgets('ouvre le billet de la réservation demandée', (tester) async {
    when(repository.getTickets).thenAnswer(
      (_) async => Right([ticket('reservation-0'), ticket('reservation-1')]),
    );

    Ticket? opened;
    await pump(tester, onOpenTicket: (found, _) => opened = found);

    // Celui-là, et pas son voisin.
    expect(opened?.reservationId, 'reservation-1');
  });

  testWidgets('une réservation absente du compte ne mène pas au vide', (
    tester,
  ) async {
    // Annulée, remboursée, ou appartenant à quelqu'un d'autre : le billet
    // n'est pas dans la liste, et l'écran doit le dire plutôt que de
    // rester blanc.
    when(
      repository.getTickets,
    ).thenAnswer((_) async => Right([ticket('reservation-0')]));

    var fallbacks = 0;
    await pump(tester, onFallback: () => fallbacks++);

    expect(find.text('Billet introuvable'), findsOneWidget);

    await tester.tap(find.text('Voir mes voyages'));
    await tester.pump();
    expect(fallbacks, 1);
  });

  testWidgets('une panne réseau propose de réessayer', (tester) async {
    when(repository.getTickets).thenAnswer(
      (_) async => const Left(NetworkFailure('réseau indisponible')),
    );

    await pump(tester, onOpenTicket: (_, _) {});

    expect(find.text('Billet indisponible'), findsOneWidget);
    expect(find.text('Réessayer'), findsOneWidget);

    // Le serveur revient : le bouton rejoue la lecture.
    when(
      repository.getTickets,
    ).thenAnswer((_) async => Right([ticket('reservation-1')]));

    await tester.tap(find.text('Réessayer'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.text('Billet indisponible'), findsNothing);
  });

  testWidgets('une panne laisse aussi la sortie vers les voyages', (
    tester,
  ) async {
    when(repository.getTickets).thenAnswer(
      (_) async => const Left(NetworkFailure('réseau indisponible')),
    );

    var fallbacks = 0;
    await pump(tester, onFallback: () => fallbacks++);

    await tester.tap(find.text('Voir mes voyages'));
    await tester.pump();

    expect(fallbacks, 1);
  });

  testWidgets('ne demande la liste qu’une fois', (tester) async {
    when(
      repository.getTickets,
    ).thenAnswer((_) async => Right([ticket('reservation-1')]));

    await pump(tester, onOpenTicket: (_, _) {});

    verify(repository.getTickets).called(1);
  });
}
