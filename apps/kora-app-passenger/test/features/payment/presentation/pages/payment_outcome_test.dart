// Où mène un paiement, une fois le serveur ayant tranché.
//
// L'écran d'attente d'avant offrait un bouton « Actualiser » qui poussait
// la page de succès **sans rien vérifier** : un paiement refusé s'y
// annonçait comme confirmé. Le parcours ne passe plus par là — le
// résultat vient du serveur, et de lui seul.

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_attempt.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_flow_bloc.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_failed_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_flow_args.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_pending_page.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  setUpAll(() => AppLocalization.preloadAll());

  const args = PaymentFlowArgs(total: Price(12500), providerLabel: 'MTN MoMo');

  /// Un paiement refusé par le fournisseur.
  const declined = PaymentAttempt(
    id: 'pay-1',
    status: PaymentAttemptStatus.failed,
    reference: 'KE-PAY-1',
  );

  Future<void> pumpFailed(
    WidgetTester tester, {
    required PaymentFlowBloc flow,
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
        home: withSeatHold(
          const PaymentFailedPage(args: args),
          bloc: SeatHoldBloc(StubReservationRepository()),
          flow: flow,
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  /// L'écran d'attente, monté seul.
  ///
  /// Pas de `pumpAndSettle` : il porte une barre de chargement qui tourne
  /// sans fin, et attendre qu'elle s'arrête ne rendrait jamais la main.
  Future<void> pumpPending(
    WidgetTester tester, {
    required PaymentFlowBloc flow,
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
        home: withSeatHold(
          const PaymentPendingPage(args: args),
          bloc: SeatHoldBloc(StubReservationRepository()),
          flow: flow,
        ),
      ),
    );
    await tester.pump();
  }

  testWidgets('une attente normale n’offre rien à faire', (tester) async {
    // Vingt secondes sont le cours ordinaire d'un paiement Mobile Money.
    // Offrir d'« actualiser » ou de « signaler un problème » pendant ce
    // temps, c'est annoncer une panne qui n'a pas eu lieu — et pousser à
    // intervenir là où il suffisait d'attendre.
    final flow = PaymentFlowBloc(StubPaymentRepository());
    addTearDown(flow.close);

    await pumpPending(tester, flow: flow);

    expect(find.text('Actualiser le statut'), findsNothing);
    expect(find.text('Signaler un problème'), findsNothing);

    // Passé le délai prévu, l'attente cesse d'être normale : il y a
    // désormais quelque chose à tenter, et les deux boutons paraissent.
    await tester.pump(const Duration(seconds: 80));

    expect(find.text('Actualiser le statut'), findsOneWidget);
    expect(find.text('Signaler un problème'), findsOneWidget);
  });

  test('un refus du fournisseur devient un échec, jamais un succès', () async {
    // Le bloc traduit le statut du serveur. C'est la seule source du
    // résultat affiché ensuite.
    final flow = PaymentFlowBloc(StubPaymentRepository(attempt: declined));
    addTearDown(flow.close);

    flow.add(
      const PaymentFlowStarted(
        reservationId: 'res-1',
        provider: 'fedapay',
        network: 'mtn_momo',
        phone: '+2290166000000',
      ),
    );
    await flow.stream.firstWhere((state) => !state.isStarting);

    expect(flow.state.hasFailed, isTrue);
    expect(flow.state.isConfirmed, isFalse);
  });

  testWidgets('l’écran d’échec dit ce que le serveur reproche', (tester) async {
    final flow = PaymentFlowBloc(StubPaymentRepository(attempt: declined));
    addTearDown(flow.close);

    await pumpFailed(tester, flow: flow);

    expect(find.text('Paiement refusé'), findsWidgets);
    // Les sièges restent retenus : réessayer a un sens tant que le
    // décompte court.
    expect(find.text('Réessayer le paiement'), findsOneWidget);
    expect(find.text('Changer de moyen'), findsOneWidget);
  });

  testWidgets('« Changer de moyen » rend l’écran des moyens déjà ouvert', (
    tester,
  ) async {
    // Un `go` reconstruisait l'écran des moyens sans son total : écran
    // vide, et un retour qui, la pile remplacée, ne menait qu'au noir.
    tester.view.physicalSize = const Size(390 * 3, 900 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final flow = PaymentFlowBloc(StubPaymentRepository(attempt: declined));
    addTearDown(flow.close);

    final router = GoRouter(
      initialLocation: '/recapitulatif',
      routes: [
        GoRoute(
          path: '/recapitulatif',
          builder: (context, state) =>
              const Scaffold(body: Text('Récapitulatif')),
        ),
        GoRoute(
          path: AppRouter.paymentMethodPath,
          builder: (context, state) =>
              const Scaffold(body: Text('Moyens, total compris')),
        ),
        GoRoute(
          path: AppRouter.paymentMobileMoneyPath,
          builder: (context, state) =>
              const Scaffold(body: Text('Saisie Mobile Money')),
        ),
        GoRoute(
          path: AppRouter.paymentFailedPath,
          builder: (context, state) => const PaymentFailedPage(args: args),
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
        builder: (context, child) => withSeatHold(
          child!,
          bloc: SeatHoldBloc(StubReservationRepository()),
          flow: flow,
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Le chemin du tunnel : récapitulatif, moyens, saisie, refus.
    unawaited(router.push(AppRouter.paymentMethodPath));
    await tester.pumpAndSettle();
    unawaited(router.push(AppRouter.paymentMobileMoneyPath));
    await tester.pumpAndSettle();
    unawaited(router.push(AppRouter.paymentFailedPath));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Changer de moyen'));
    await tester.pumpAndSettle();

    expect(find.text('Moyens, total compris'), findsOneWidget);
    expect(find.text('Changer de moyen'), findsNothing);

    // Et le retour mène encore quelque part.
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();

    expect(find.text('Récapitulatif'), findsOneWidget);
  });

  test('le suivi interroge le fournisseur, il ne relit pas la base', () async {
    // `GET payments/{uuid}` rend ce que la base dit déjà ; seul
    // `POST payments/{uuid}/verify` va demander au fournisseur. Tant
    // qu'aucun webhook n'arrive, la base reste à `pending` — et le suivi
    // tournait donc sur une valeur figée jusqu'à l'expiration du blocage.
    const waiting = PaymentAttempt(
      id: 'pay-1',
      status: PaymentAttemptStatus.pending,
      reference: 'KE-PAY-1',
      pollingInterval: Duration(milliseconds: 20),
    );

    final repository = StubPaymentRepository(
      attempt: waiting,
      statuses: const [
        PaymentAttempt(
          id: 'pay-1',
          status: PaymentAttemptStatus.confirmed,
          reference: 'KE-PAY-1',
        ),
      ],
    );
    final flow = PaymentFlowBloc(repository);
    addTearDown(flow.close);

    flow.add(
      const PaymentFlowStarted(
        reservationId: 'res-1',
        provider: 'fedapay',
        network: 'mtn_momo',
        phone: '+2290166000001',
      ),
    );

    // Le paiement se confirme tout seul : le voyageur n'appuie sur rien.
    await flow.stream.firstWhere((state) => state.isConfirmed);

    expect(repository.calls, contains('verify'));
    expect(repository.calls, isNot(contains('status')));
  });
}
