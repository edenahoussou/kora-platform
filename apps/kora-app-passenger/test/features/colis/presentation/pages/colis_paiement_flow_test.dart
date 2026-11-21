// Le règlement d'une expédition — écrans 08 et 09 de la maquette.
//
// Ce que ces tests tiennent : les encaisseurs sont ceux du billet, le
// chemin dépend de celui qu'on touche, aucun débit n'est déclenché, et
// la référence officielle naît au paiement — jamais avant.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/features/colis/presentation/bloc/colis_draft_cubit.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_checkout_args.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_gateway_page.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_momo_page.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_payment_page.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_pending_page.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_receipt_page.dart';

void main() {
  late ColisDraftCubit draft;

  setUpAll(AppLocalization.preloadAll);

  setUp(() {
    draft = ColisDraftCubit()
      ..start(senderName: 'Koffi MENSAH', senderPhone: '+229 97 12 34 45')
      ..chooseOrigin('ctn-gare-centrale')
      ..chooseDestination('pkx-gare-nord')
      ..describe('2 pagnes')
      ..setRecipient(name: 'Amina KOTO', phone: '0195000088')
      ..preregister()
      // Le montant se fige à la pesée : sans elle, l'écran encaisserait
      // une déclaration.
      ..confirmWeight(3.4);
  });

  tearDown(() => draft.close());

  Widget wrap(Widget child) =>
      BlocProvider<ColisDraftCubit>.value(value: draft, child: child);

  Future<void> pumpFlow(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 2600 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    ColisCheckoutArgs? argsOf(GoRouterState state) =>
        state.extra as ColisCheckoutArgs?;

    final router = GoRouter(
      initialLocation: AppRouter.colisPaymentPath,
      routes: [
        GoRoute(
          path: AppRouter.colisPaymentPath,
          builder: (context, state) => wrap(const ColisPaymentPage()),
        ),
        GoRoute(
          path: AppRouter.colisMomoPath,
          builder: (context, state) => wrap(ColisMomoPage(args: argsOf(state))),
        ),
        GoRoute(
          path: AppRouter.colisGatewayPath,
          builder: (context, state) =>
              wrap(ColisGatewayPage(isCard: false, args: argsOf(state))),
        ),
        GoRoute(
          path: AppRouter.colisCardPath,
          builder: (context, state) =>
              wrap(ColisGatewayPage(isCard: true, args: argsOf(state))),
        ),
        GoRoute(
          path: AppRouter.colisPendingPath,
          builder: (context, state) =>
              wrap(ColisPendingPage(args: argsOf(state))),
        ),
        GoRoute(
          path: AppRouter.colisReceiptPath,
          builder: (context, state) => wrap(const ColisReceiptPage()),
        ),
        GoRoute(
          path: '/colis',
          builder: (context, state) => const Scaffold(body: Text('accueil')),
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      MaterialApp.router(
        theme: AppTheme.light,
        locale: const Locale('fr', 'FR'),
        supportedLocales: const [Locale('fr', 'FR')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        routerConfig: router,
      ),
    );
    await tester.pumpAndSettle();
  }

  /// Laisse la transition se jouer sans attendre l'arrêt des
  /// animations : l'écran d'attente anime une barre indéterminée, et
  /// `pumpAndSettle` l'attendrait indéfiniment.
  Future<void> settle(WidgetTester tester) async {
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
  }

  /// Le bouton de règlement, quel que soit l'écran qui le porte.
  Finder payCta() => find.widgetWithText(KoraButton, 'Payer 800 FCFA');

  testWidgets('les encaisseurs sont ceux d’un billet', (tester) async {
    // Le colis se réglait par une liste d'opérateurs qui n'existait que
    // là : MTN, Moov, carte. Le passager a déjà payé un voyage par
    // FedaPay ou KKiaPay, et deux listes pour un même geste lui font
    // croire à deux paiements.
    await pumpFlow(tester);

    expect(find.text('FedaPay'), findsOneWidget);
    expect(find.text('KKiaPay'), findsOneWidget);
    expect(find.text('Carte bancaire'), findsOneWidget);

    // Choisir l'encaisseur **est** le geste : plus de bouton « Payer »
    // sur cet écran, comme pour un billet.
    expect(payCta(), findsNothing);
  });

  testWidgets('FedaPay réclame l’opérateur et le numéro', (tester) async {
    // FedaPay pousse la demande sur le téléphone : sans le réseau ni le
    // numéro, il n'a personne à appeler.
    await pumpFlow(tester);

    await tester.tap(find.text('FedaPay'));
    await tester.pumpAndSettle();

    expect(find.text('Choisissez votre opérateur'), findsOneWidget);
    expect(tester.widget<KoraButton>(payCta()).onPressed, isNull);

    await tester.tap(find.text('MTN MoMo'));
    await tester.pumpAndSettle();
    // L'opérateur seul ne suffit pas.
    expect(tester.widget<KoraButton>(payCta()).onPressed, isNull);

    await tester.enterText(find.byType(TextField).last, '0152235689');
    await tester.pumpAndSettle();
    expect(tester.widget<KoraButton>(payCta()).onPressed, isNotNull);
  });

  testWidgets('KKiaPay encaisse dans sa feuille, sans rien demander', (
    tester,
  ) async {
    // Rien n'est saisi ici, et c'est volontaire : la feuille du
    // prestataire recueille elle-même le numéro.
    await pumpFlow(tester);

    await tester.tap(find.text('KKiaPay'));
    await tester.pumpAndSettle();

    expect(find.text('Choisissez votre opérateur'), findsNothing);
    expect(find.byType(TextField), findsNothing);
    expect(tester.widget<KoraButton>(payCta()).onPressed, isNotNull);
  });

  testWidgets('la carte bancaire passe par la fenêtre du prestataire', (
    tester,
  ) async {
    // Aucun numéro de carte ne transite par nos écrans : les
    // obligations PCI-DSS y entreraient avec lui.
    await pumpFlow(tester);

    await tester.tap(find.text('Carte bancaire'));
    await tester.pumpAndSettle();

    expect(find.text('Paiement par carte'), findsOneWidget);
    expect(find.byType(TextField), findsNothing);
  });

  testWidgets('la référence officielle naît au paiement, pas avant', (
    tester,
  ) async {
    await pumpFlow(tester);

    await tester.tap(find.text('KKiaPay'));
    await tester.pumpAndSettle();
    expect(draft.state.reference, isNull);

    await tester.tap(payCta());
    // `pumpAndSettle` n'en finirait pas : le médaillon d'attente porte
    // une barre de progression indéterminée, qui tourne sans fin.
    await settle(tester);

    // L'attente ne conclut rien d'elle-même : aucun opérateur n'est au
    // bout du fil, et le bouton le dit.
    expect(find.text('Paiement en attente de confirmation…'), findsOneWidget);
    expect(draft.state.reference, isNull);

    await tester.tap(find.text('Simuler la confirmation'));
    await settle(tester);

    expect(find.text('Paiement confirmé'), findsOneWidget);
    expect(draft.state.reference, startsWith('KC-'));
  });
}
