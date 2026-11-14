// La répartition Wallet / points survit au choix du moyen.
//
// Le Wallet peut ne couvrir qu'une partie du billet : l'écran du Wallet
// envoie alors le voyageur choisir comment régler le reste, et les trois
// parts doivent arriver ensemble dans `POST /payments` — c'est le serveur
// qui calcule le reste à encaisser, à partir de la part Wallet qu'on lui
// déclare.
//
// L'écran des moyens reconstruisait `PaymentArgs` pour y ajouter la
// passerelle, et y perdait la part Wallet. Le serveur mettait alors le
// billet **entier** à la charge de la passerelle : la feuille KKiaPay
// réclamait 3 756 F pour un billet à 3 700 F dont 1 000 F étaient déjà
// couverts par le solde, et FedaPay poussait la même somme sur le
// téléphone. Le voyageur aurait payé le billet deux fois pour partie.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_args.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_method_page.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  setUpAll(AppLocalization.preloadAll);

  // Un billet à 3 700 F dont le solde couvre 1 000 F et les points 200 F :
  // l'écran des moyens ne voit plus que le reste, 2 500 F.
  const args = PaymentArgs(
    total: Price(2500),
    phone: '+229 01 97 42 18 60',
    walletAmount: 1000,
    pointsAmount: 200,
  );

  /// Monte l'écran des moyens, et note les arguments reçus par l'écran
  /// d'encaissement suivant.
  Future<List<PaymentArgs>> pump(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 1400 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final received = <PaymentArgs>[];

    final hold = SeatHoldBloc(StubReservationRepository());
    addTearDown(hold.close);

    final catalog = await loadedCatalog(catalog: kStubGatewayCatalog);
    addTearDown(catalog.close);

    /// L'écran d'arrivée : il ne fait que retenir ce qu'on lui a passé.
    GoRoute landing(String path) => GoRoute(
      path: path,
      builder: (_, state) {
        received.add(state.extra! as PaymentArgs);
        return const Scaffold(body: Text('encaissement'));
      },
    );

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
                const PaymentMethodPage(args: args),
                bloc: hold,
                catalog: catalog,
              ),
            ),
            landing(AppRouter.paymentGatewayMomoPath),
            landing(AppRouter.paymentMobileMoneyPath),
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();

    return received;
  }

  testWidgets('KKiaPay reçoit la part Wallet, pas seulement le reste', (
    tester,
  ) async {
    final received = await pump(tester);

    await tester.tap(find.text('KKiaPay'));
    await tester.pumpAndSettle();

    expect(received, hasLength(1));
    expect(received.single.provider, 'kkiapay');
    expect(received.single.walletAmount, 1000);
    expect(received.single.pointsAmount, 200);
    // Et le reste à payer reste celui que l'écran du Wallet a calculé.
    expect(received.single.total.amount, 2500);
  });

  testWidgets('FedaPay la reçoit aussi', (tester) async {
    // Les deux passerelles sortent de la même carte : ce qui se perd pour
    // l'une se perd pour l'autre.
    final received = await pump(tester);

    await tester.tap(find.text('FedaPay'));
    await tester.pumpAndSettle();

    expect(received, hasLength(1));
    expect(received.single.provider, 'fedapay');
    expect(received.single.walletAmount, 1000);
    expect(received.single.pointsAmount, 200);
  });

  testWidgets('le numéro et le voyage suivent toujours', (tester) async {
    // La recopie champ par champ est précisément ce qui avait laissé
    // tomber la part Wallet : ce test tient le reste du contenu.
    final received = await pump(tester);

    await tester.tap(find.text('KKiaPay'));
    await tester.pumpAndSettle();

    expect(received.single.phone, '+229 01 97 42 18 60');
  });
}
