// Le panneau du PDF officiel : chaque état se lit à l'écran.

import 'dart:async';

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/ticket_document.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/ticket_document_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/widgets/official_ticket_download.dart';

import '../../../../support/stub_ticket_document_repository.dart';

void main() {
  setUpAll(() => AppLocalization.preloadAll());

  const ticketId = 'ticket-1';

  Future<void> pump(WidgetTester tester, TicketDocumentCubit cubit) async {
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
        home: Scaffold(
          body: OfficialTicketDownload(ticketId: ticketId, cubit: cubit),
        ),
      ),
    );
    await tester.pump();
  }

  testWidgets('télécharger, attendre, puis ouvrir le billet', (tester) async {
    final repository = StubTicketDocumentRepository()..gate = Completer();
    final cubit = TicketDocumentCubit(repository);
    addTearDown(cubit.close);

    await pump(tester, cubit);
    expect(find.text('Télécharger le billet (PDF)'), findsOneWidget);
    expect(find.text('Ouvrir le billet'), findsNothing);

    await tester.tap(find.text('Télécharger le billet (PDF)'));
    await tester.pump();
    // Le téléchargement se voit, et le bouton ne se relance pas.
    expect(cubit.state.of(ticketId).isDownloading, isTrue);

    repository.gate!.complete();
    await tester.pump();
    await tester.pump();

    expect(
      find.text('Billet officiel KORA enregistré sur ce téléphone.'),
      findsOneWidget,
    );
    expect(find.text('Ouvrir le billet'), findsOneWidget);
    expect(find.text('Partager le billet'), findsOneWidget);
    expect(find.text('Télécharger à nouveau'), findsOneWidget);
  });

  testWidgets('une session expirée le dit, et laisse réessayer', (
    tester,
  ) async {
    final cubit = TicketDocumentCubit(
      StubTicketDocumentRepository(
        results: [const Left(TicketDocumentError.sessionExpired)],
      ),
    );
    addTearDown(cubit.close);

    await pump(tester, cubit);
    await tester.tap(find.text('Télécharger le billet (PDF)'));
    await tester.pump();
    await tester.pump();

    expect(
      find.text('Votre session a expiré. Reconnectez-vous, puis réessayez.'),
      findsOneWidget,
    );
    expect(find.byType(KoraButton), findsOneWidget);
  });

  testWidgets('un billet inaccessible ne propose pas de réessayer', (
    tester,
  ) async {
    final cubit = TicketDocumentCubit(
      StubTicketDocumentRepository(
        results: [const Left(TicketDocumentError.inaccessible)],
      ),
    );
    addTearDown(cubit.close);

    await pump(tester, cubit);
    await tester.tap(find.text('Télécharger le billet (PDF)'));
    await tester.pump();
    await tester.pump();

    expect(
      find.text('Ce billet n’est pas accessible depuis votre compte.'),
      findsOneWidget,
    );
    expect(find.byType(KoraButton), findsNothing);
  });

  testWidgets('hors ligne, le billet enregistré s’ouvre quand même', (
    tester,
  ) async {
    final saved = StubTicketDocumentRepository.documentFor(ticketId);
    final cubit = TicketDocumentCubit(
      StubTicketDocumentRepository(
        savedDocument: saved,
        results: [Right(saved.asStale())],
      ),
    );
    addTearDown(cubit.close);

    await pump(tester, cubit);
    // Déjà là à l'ouverture, sans réseau.
    expect(find.text('Ouvrir le billet'), findsOneWidget);

    await tester.tap(find.text('Télécharger à nouveau'));
    await tester.pump();
    await tester.pump();

    expect(
      find.text(
        'Hors connexion : voici le billet déjà enregistré sur ce téléphone.',
      ),
      findsOneWidget,
    );
    expect(find.text('Ouvrir le billet'), findsOneWidget);
  });
}
