// Ce que l'écran propose après un refus.
//
// Un refus d'opérateur fait aujourd'hui **expirer la réservation** côté
// serveur : les sièges repartent à la vente, et toute nouvelle demande de
// paiement est refusée — « Cette réservation doit d'abord être reprise ».
// L'écran doit donc cesser de promettre un nouvel essai, et couper un
// décompte qui tourne sur des places déjà rendues.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat_hold.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_attempt.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_flow_bloc.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_failed_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_flow_args.dart';

import '../../../../support/seat_hold_harness.dart';

/// Un blocage vivant : c'est lui que le décompte affiche.
SeatHoldBloc heldBloc() {
  final bloc = SeatHoldBloc(StubReservationRepository())
    ..add(
      SeatHoldResumed(
        hold: SeatHold(
          id: 'res-1',
          expiresAt: DateTime.now().toUtc().add(const Duration(minutes: 8)),
          seatLabels: const ['5B'],
          total: const Price(2600),
        ),
        passengers: 1,
      ),
    );

  return bloc;
}

const _args = PaymentFlowArgs(total: Price(2600), providerLabel: 'MTN MoMo');

/// Un refus, avec ou sans sièges encore retenus.
Future<PaymentFlowBloc> _refused({required bool reservationHeld}) async {
  final bloc = PaymentFlowBloc(
    StubPaymentRepository(
      attempt: PaymentAttempt(
        id: 'pay-1',
        status: PaymentAttemptStatus.failed,
        failureMessage: 'Paiement refusé par l’opérateur.',
        reservationHeld: reservationHeld,
      ),
    ),
  );

  bloc.add(
    const PaymentFlowStarted(
      reservationId: 'res-1',
      provider: 'fedapay',
      network: 'mtn_momo',
      phone: '+2290191003606',
    ),
  );
  await bloc.stream.firstWhere((state) => state.hasFailed);

  return bloc;
}

Future<void> _pump(
  WidgetTester tester, {
  required PaymentFlowBloc flow,
  required SeatHoldBloc hold,
}) async {
  tester.view.physicalSize = const Size(390 * 3, 900 * 3);
  tester.view.devicePixelRatio = 3;
  addTearDown(tester.view.reset);

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
      routerConfig: GoRouter(
        routes: [
          GoRoute(
            path: '/',
            builder: (_, _) => MultiBlocProvider(
              providers: [
                BlocProvider<PaymentFlowBloc>.value(value: flow),
                BlocProvider<SeatHoldBloc>.value(value: hold),
              ],
              child: const PaymentFailedPage(args: _args),
            ),
          ),
          GoRoute(
            path: '/home',
            builder: (_, _) => const Scaffold(body: Text('accueil')),
          ),
        ],
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  setUpAll(AppLocalization.preloadAll);

  testWidgets('sans sièges, l’écran renvoie à la recherche', (tester) async {
    final flow = await _refused(reservationHeld: false);
    addTearDown(flow.close);
    final hold = heldBloc();
    addTearDown(hold.close);

    await _pump(tester, flow: flow, hold: hold);

    expect(find.textContaining('vos sièges ont été relâchés'), findsOneWidget);
    expect(find.text('Refaire une recherche'), findsOneWidget);
    // Ni nouvel essai ni autre moyen : les deux buteraient sur la même
    // réservation morte.
    expect(find.text('Réessayer le paiement'), findsNothing);
    expect(find.text('Changer de moyen'), findsNothing);

    // Et le décompte s'arrête : il tournait sur des places déjà rendues.
    expect(hold.state.isHeld, isFalse);
    expect(find.textContaining('restantes'), findsNothing);

    await tester.tap(find.text('Refaire une recherche'));
    await tester.pumpAndSettle();
    expect(find.text('accueil'), findsOneWidget);
  });

  testWidgets('sièges encore tenus, le nouvel essai reste offert', (
    tester,
  ) async {
    // Le jour où le serveur cessera d'expirer la réservation sur un refus,
    // c'est ce chemin-là qui servira.
    final flow = await _refused(reservationHeld: true);
    addTearDown(flow.close);
    final hold = heldBloc();
    addTearDown(hold.close);

    await _pump(tester, flow: flow, hold: hold);

    expect(find.text('Réessayer le paiement'), findsOneWidget);
    expect(find.text('Changer de moyen'), findsOneWidget);
    expect(hold.state.isHeld, isTrue);

    // Le décompte bat la seconde tant qu'il court : on l'arrête ici, sinon
    // le test se termine sur une minuterie encore vivante.
    hold.add(const SeatHoldSettled());
    await tester.pumpAndSettle();
  });
}
