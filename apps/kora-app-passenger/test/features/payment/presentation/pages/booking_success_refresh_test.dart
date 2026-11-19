// Ce que l'écran de confirmation prévient en arrivant.
//
// Les onglets Voyages et Wallet naissent une fois et vivent tant que
// l'app vit : leur contenu est lu à la création, puis plus jamais. Un
// achat conclu dans le tunnel ne les traverse pas tout seul.
//
// Le Wallet n'était prévenu que par le bouton « Voir mon Wallet » de la
// recharge. Un billet réglé en partie avec le solde laissait donc
// l'onglet sur le montant d'avant l'achat, et le voyageur qui venait
// vérifier son crédit y lisait un chiffre faux.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/cancel_ticket_cubit.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/booking_success_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_flow_args.dart';
import 'package:kora_app_passenger/features/wallet/presentation/bloc/wallet_refresh.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  setUpAll(AppLocalization.preloadAll);

  /// Remet des signaux neufs dans le conteneur, et rend celui du Wallet.
  WalletRefresh signals() {
    if (getIt.isRegistered<WalletRefresh>()) getIt.unregister<WalletRefresh>();
    if (getIt.isRegistered<BookingRefresh>()) {
      getIt.unregister<BookingRefresh>();
    }

    final wallet = WalletRefresh();
    getIt.registerSingleton<WalletRefresh>(wallet);
    getIt.registerSingleton<BookingRefresh>(BookingRefresh());

    addTearDown(() {
      if (getIt.isRegistered<WalletRefresh>()) {
        getIt.unregister<WalletRefresh>();
      }
      if (getIt.isRegistered<BookingRefresh>()) {
        getIt.unregister<BookingRefresh>();
      }
    });

    return wallet;
  }

  Future<void> pump(WidgetTester tester, {required bool isTopUp}) async {
    final hold = SeatHoldBloc(StubReservationRepository());
    addTearDown(hold.close);

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
        home: BlocProvider<SeatHoldBloc>.value(
          value: hold,
          child: BookingSuccessPage(
            args: PaymentFlowArgs(
              total: const Price(3700),
              providerLabel: 'FedaPay',
              isTopUp: isTopUp,
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('un billet payé prévient l’onglet Wallet', (tester) async {
    // Le solde a pu servir à régler une partie du billet : l'onglet doit
    // relire, sans attendre que le voyageur tire dessus.
    final wallet = signals();
    final seen = <void>[];
    final watch = wallet.changes.listen(seen.add);
    addTearDown(watch.cancel);

    await pump(tester, isTopUp: false);

    expect(seen, hasLength(1));
  });

  testWidgets('une recharge le prévient aussi, sans passer par le bouton', (
    tester,
  ) async {
    // Le signal partait du bouton « Voir mon Wallet ». Le voyageur qui
    // rentre à l'accueil puis rouvre l'onglet par la barre du bas doit
    // voir la même chose que celui qui a touché le bouton.
    final wallet = signals();
    final seen = <void>[];
    final watch = wallet.changes.listen(seen.add);
    addTearDown(watch.cancel);

    await pump(tester, isTopUp: true);

    expect(seen, hasLength(1));
  });
}
