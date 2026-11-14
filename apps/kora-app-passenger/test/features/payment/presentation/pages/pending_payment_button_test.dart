// « Payer » ne doit jamais rester muet.
//
// Quand une demande est déjà en vol, le Bloc refuse d'en lancer une
// seconde — c'est ce qui protège du double débit. L'écran d'encaissement
// rouvrait alors la fenêtre de la passerelle, ce qui suffit tant que le
// serveur en rend la configuration.
//
// Mais dès que le serveur a pris la main — il vérifie une transaction
// que la feuille lui a déjà rendue, ou l'initiation n'a pas abouti — il
// répond « attends », sans fenêtre à rouvrir. Le bouton n'émettait plus
// rien et n'ouvrait plus rien : l'écran devenait un cul-de-sac.
//
// Vu sur le téléphone le 13 novembre 2026, sur une recharge de 5 000 F
// par KKiaPay laissée en attente : « Payer 5 000 FCFA » sans aucun
// effet, deux fois de suite.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_attempt.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_catalog.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_flow_bloc.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/gateway_checkout_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_args.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_account.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_movement.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_top_up.dart';
import 'package:kora_app_passenger/features/wallet/domain/repositories/wallet_repository.dart';

import '../../../../support/seat_hold_harness.dart';

/// Le Wallet réduit à ce que la recharge lui demande.
///
/// Sa réponse porte un paiement en attente **sans configuration de
/// widget** : l'état exact où le serveur dit « j'attends », et où
/// l'écran n'avait plus rien à faire.
class _Wallet implements WalletRepository {
  @override
  Future<Either<Failure, WalletTopUp>> initiateTopUp({
    required int amount,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? phone,
    String? network,
  }) async => Right(
    WalletTopUp(
      id: 'top-1',
      amount: Price(amount),
      status: WalletTopUpStatus.pending,
      attempt: const PaymentAttempt(
        id: 'pay-1',
        status: PaymentAttemptStatus.pending,
        nextAction: 'wait',
      ),
    ),
  );

  @override
  Future<Either<Failure, WalletAccount>> fetchAccount() async =>
      throw UnimplementedError();

  @override
  Future<Either<Failure, WalletLedgerPage>> fetchMovements({
    WalletBalanceKind? kind,
    WalletDirection? direction,
    String? cursor,
  }) async => throw UnimplementedError();

  @override
  Future<Either<Failure, WalletAccount>> attachReferral(String code) async =>
      throw UnimplementedError();

  @override
  Future<Either<Failure, WalletTopUp>> fetchTopUp(String uuid) async =>
      throw UnimplementedError();
}

void main() {
  setUpAll(AppLocalization.preloadAll);

  const args = PaymentArgs(
    total: Price(5000),
    provider: 'kkiapay',
    topUpAmount: 5000,
  );

  testWidgets('une recharge déjà en vérification mène au suivi', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390 * 3, 1400 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    // La recharge est lancée **avant** que l'écran n'existe : c'est le
    // cas réel, celui du voyageur qui revient sur une demande laissée
    // en attente.
    final flow = PaymentFlowBloc(StubPaymentRepository(), null, _Wallet());

    flow.add(
      const PaymentFlowStarted(
        topUpAmount: 5000,
        method: PaymentCatalog.mobileMoneyCode,
        provider: 'kkiapay',
      ),
    );
    await flow.stream.firstWhere((state) => state.isPending);
    expect(flow.state.attempt?.needsWidget, isFalse);

    final hold = SeatHoldBloc(StubReservationRepository());
    addTearDown(hold.close);

    final catalog = await loadedCatalog(catalog: kStubGatewayCatalog);
    addTearDown(catalog.close);

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
              builder: (_, _) => withSeatHold(
                const GatewayCheckoutPage(
                  method: PaymentCatalog.mobileMoneyCode,
                  args: args,
                ),
                bloc: hold,
                catalog: catalog,
                flow: flow,
              ),
            ),
            GoRoute(
              path: AppRouter.paymentPendingPath,
              builder: (_, _) => const Scaffold(body: Text('suivi')),
            ),
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Payer ${const Price(5000).formatted}'));
    await tester.pumpAndSettle();

    // Sans cette porte, le Bloc avalait l'événement et l'écran ne
    // bougeait pas d'un pixel.
    expect(find.text('suivi'), findsOneWidget);

    // Le Bloc fait battre un `Timer.periodic` pour interroger le
    // serveur : le laisser en vol ferait échouer le test sur
    // `!timersPending`, bien après l'assertion qui compte.
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.runAsync(flow.close);
  });
}
