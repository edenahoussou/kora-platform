// Les quatre écrans de paiement : ce qu'ils affichent, ce qu'ils refusent
// de laisser passer, et où ils mènent.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/phone_country.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/presentation/bloc/seat_hold_bloc.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_attempt.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_catalog.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_catalog_bloc.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_flow_bloc.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/gateway_checkout_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/mobile_money_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_args.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_method_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/wallet_payment_page.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_account.dart';

import '../../../../support/seat_hold_harness.dart';

void main() {
  late SeatHoldBloc holdBloc;

  const args = PaymentArgs(total: Price(12500), phone: '+229 01 97 42 18 60');

  /// Les montants passent par [Price.formatted], qui sépare les milliers
  /// d'une espace fine insécable — pas l'espace du clavier. Les écrire à
  /// la main ici ne trouverait jamais rien.
  String money(int amount) => Price(amount).formatted;

  setUpAll(() => AppLocalization.preloadAll());

  setUp(() {
    holdBloc = SeatHoldBloc(StubReservationRepository());
    addTearDown(holdBloc.close);
  });

  /// Monte [page] avec les Blocs du tunnel.
  ///
  /// [settle] tombe à `false` quand un blocage est posé : [SeatHoldBloc]
  /// fait alors battre un `Timer.periodic` pour le décompte, chaque
  /// battement programme une image, et `pumpAndSettle` attendrait un
  /// repos qui ne vient jamais — jusqu'à expirer au bout de dix minutes.
  Future<void> pump(
    WidgetTester tester,
    Widget page, {
    PaymentCatalogBloc? catalog,
    PaymentFlowBloc? flow,
    SeatHoldBloc? hold,
    bool settle = true,
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
        home: withSeatHold(
          page,
          bloc: hold ?? holdBloc,
          catalog: catalog,
          flow: flow,
        ),
      ),
    );

    if (settle) {
      await tester.pumpAndSettle();
      return;
    }

    // Deux images suffisent : les Blocs sont déjà dans l'état voulu, il
    // ne reste qu'à les laisser peindre.
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
  }

  group('choix du moyen', () {
    testWidgets('ne propose que ce que le serveur encaisse', (tester) async {
      await pump(
        tester,
        const PaymentMethodPage(args: args),
        catalog: await loadedCatalog(),
      );

      expect(find.text('Comment souhaitez-vous payer ?'), findsOneWidget);
      // La carte porte le nom de la passerelle, et le moyen dessous :
      // deux passerelles côte à côte qui s'appelleraient toutes deux
      // « Mobile Money » ne se distingueraient que par leur logo.
      expect(find.text('FedaPay'), findsOneWidget);
      expect(find.text('Mobile Money'), findsOneWidget);
      expect(find.text('KORA Wallet'), findsOneWidget);
      expect(find.text(money(12500)), findsOneWidget);

      // La maquette montrait un troisième pavé. Le serveur n'annonce pas
      // ce moyen : le proposer mènerait à un paiement refusé après que le
      // voyageur a saisi son numéro de carte.
      expect(find.text('Carte bancaire'), findsNothing);
    });

    testWidgets('donne une carte par passerelle Mobile Money', (tester) async {
      // Les deux n'encaissent pas de la même façon : les confondre sous
      // une seule carte promettrait au voyageur une expérience qu'il
      // n'aura pas.
      await pump(
        tester,
        const PaymentMethodPage(args: args),
        catalog: await loadedCatalog(catalog: kStubGatewayCatalog),
      );

      expect(find.text('FedaPay'), findsOneWidget);
      expect(find.text('KKiaPay'), findsOneWidget);
      expect(find.text('Mobile Money'), findsNWidgets(2));
    });

    testWidgets('grise le Wallet plutôt que de le faire disparaître', (
      tester,
    ) async {
      // Le serveur le renvoie exprès, désactivé, avec sa raison : le
      // masquer laisserait croire à un bug plutôt qu'à un moyen à venir.
      await pump(
        tester,
        const PaymentMethodPage(args: args),
        catalog: await loadedCatalog(),
      );

      expect(find.text('KORA Wallet'), findsOneWidget);
      expect(find.text('Bientôt disponible'), findsOneWidget);

      // Inerte : un pavé grisé ne mène nulle part.
      await tester.tap(find.text('KORA Wallet'));
      await tester.pumpAndSettle();

      expect(find.text('Comment souhaitez-vous payer ?'), findsOneWidget);
    });

    testWidgets('des moyens tous inertes s’expliquent', (tester) async {
      // Le cas réel du staging : le serveur annonce Mobile Money et le
      // Wallet, mais aucun provider n'a d'identifiants valides. Deux pavés
      // grisés sans un mot laisseraient le voyageur devant un écran qui
      // refuse sans dire pourquoi.
      await pump(
        tester,
        const PaymentMethodPage(args: args),
        catalog: await loadedCatalog(
          catalog: const PaymentCatalog(
            country: 'BJ',
            currency: 'XOF',
            methods: [
              PaymentMethod(
                code: PaymentCatalog.mobileMoneyCode,
                label: 'Mobile Money',
                // Le serveur le désactive quand aucun encaisseur n'est
                // configuré.
                enabled: false,
              ),
              PaymentMethod(
                code: PaymentCatalog.walletCode,
                label: 'Mini Wallet',
                enabled: false,
                availability: 'out_of_mvp',
              ),
            ],
          ),
        ),
      );

      expect(find.text('Mobile Money'), findsOneWidget);
      expect(find.text('Indisponible'), findsOneWidget);
      expect(
        find.text('Aucun moyen de paiement n’est disponible pour le moment.'),
        findsOneWidget,
      );
    });

    testWidgets('le total reste lisible pendant le chargement', (tester) async {
      // Même sans savoir *comment* payer, le voyageur doit voir *combien*.
      await pump(
        tester,
        const PaymentMethodPage(args: args),
        catalog: PaymentCatalogBloc(StubPaymentRepository()),
      );

      expect(find.text('Chargement des moyens de paiement…'), findsOneWidget);
      expect(find.text(money(12500)), findsOneWidget);
      expect(find.text('Mobile Money'), findsNothing);
    });

    testWidgets('un serveur injoignable propose de réessayer', (tester) async {
      await pump(
        tester,
        const PaymentMethodPage(args: args),
        catalog: await loadedCatalog(
          failure: const NetworkFailure('Connexion perdue.'),
        ),
      );

      expect(find.text('Connexion perdue.'), findsOneWidget);
      expect(find.text('Réessayer'), findsOneWidget);
      // Aucun moyen affiché : mieux vaut un écran qui l'avoue qu'un choix
      // qui échouera à la fin.
      expect(find.text('Mobile Money'), findsNothing);
    });

    testWidgets('sans blocage en cours, aucun délai n’est annoncé', (
      tester,
    ) async {
      // Annoncer une protection qui n'existe pas serait pire que de ne
      // rien dire.
      await pump(tester, const PaymentMethodPage(args: args));

      expect(find.textContaining('restent réservés'), findsNothing);
    });
  });

  group('Mobile Money', () {
    testWidgets('reprend le numéro du compte et le montant', (tester) async {
      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
      );

      // `ScreenHeader` met le surtitre en capitales.
      expect(
        find.text('PAIEMENT · ${money(12500)}'.toUpperCase()),
        findsOneWidget,
      );
      // L'indicatif est passé dans son propre sélecteur : le champ ne
      // garde que la partie nationale.
      expect(find.text('01 97 42 18 60'), findsOneWidget);
      expect(find.textContaining('+229'), findsOneWidget);
      expect(find.text('Payer ${money(12500)}'), findsOneWidget);
    });

    testWidgets('« Payer » reste inerte tant que la saisie manque', (
      tester,
    ) async {
      // L'écran opposait auparavant deux reproches à qui appuyait sans
      // avoir choisi : le bouton partait vif et ne faisait rien. Il part
      // maintenant éteint, et s'allume quand la saisie tient.
      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
      );

      KoraButton payButton() => tester.widget<KoraButton>(
        find.widgetWithText(KoraButton, 'Payer ${money(12500)}'),
      );

      // Le numéro vient du compte ; seul l'opérateur manque.
      expect(payButton().onPressed, isNull);

      await tester.tap(find.text('MTN MoMo'));
      await tester.pumpAndSettle();

      expect(payButton().onPressed, isNotNull);

      // Un numéro vidé l'éteint de nouveau.
      await tester.enterText(find.byType(KoraInput), '');
      await tester.pumpAndSettle();

      expect(payButton().onPressed, isNull);
    });

    testWidgets('ne demande jamais le code secret', (tester) async {
      // Un écran qui réclamerait ce code apprendrait au voyageur à le
      // taper ailleurs que chez son opérateur.
      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
      );

      expect(find.textContaining('code secret Mobile Money'), findsOneWidget);
      expect(find.textContaining('Saisir votre code'), findsNothing);
    });

    testWidgets('liste les réseaux annoncés par le serveur', (tester) async {
      // Le voyageur a un compte MTN ou Moov, pas un compte FedaPay :
      // c'est le **réseau** qu'il choisit. L'encaisseur s'en déduit — un
      // détail d'acheminement que l'écran n'a pas à lui montrer.
      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
      );

      // Les réseaux s'affichent d'emblée, chacun sur sa tuile : plus de
      // menu à ouvrir pour savoir par où l'on peut payer.
      expect(find.text('MTN MoMo'), findsOneWidget);
      expect(find.text('Moov Flooz'), findsOneWidget);
      expect(find.text('Celtiis Cash'), findsOneWidget);
      // Et chacune porte le logo de son réseau, fourni par KORA.
      for (final network in const ['mtn_momo', 'moov_flooz', 'celtiis_cash']) {
        expect(
          find.image(AssetImage('assets/images/operators/$network.png')),
          findsOneWidget,
          reason: 'vignette manquante pour $network',
        );
      }

      // Wave figurait dans la maquette ; le serveur ne le connaît pas.
      expect(find.text('Wave'), findsNothing);
      // FedaPay encaisse, mais n'est pas un opérateur téléphonique.
      expect(find.text('FedaPay'), findsNothing);
    });

    testWidgets('le montant à payer s’affiche en tête', (tester) async {
      // Sur un terminal de paiement, la somme se lit avant de choisir
      // par où l'on paie — pas en petit dans un surtitre.
      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
      );

      expect(find.text('Montant à payer'), findsOneWidget);
      expect(find.text(money(12500)), findsWidgets);
      expect(find.textContaining('Paiement sécurisé'), findsOneWidget);
    });

    testWidgets('choisir un opérateur le marque comme retenu', (tester) async {
      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
      );

      // Les vignettes vont par deux sur une ligne : la place manque
      // pour une pastille à côté du logo et du nom. La sélection se lit
      // donc au liseré doré — et se déclare aux lecteurs d'écran, ce
      // que ce test vérifie.
      List<Semantics> retenus() => tester
          .widgetList<Semantics>(find.byType(Semantics))
          .where((tile) => tile.properties.selected ?? false)
          .toList();

      expect(retenus(), isEmpty);

      await tester.tap(find.text('Moov Flooz'));
      await tester.pumpAndSettle();

      // Un seul retenu, et c'est celui qu'on a touché.
      expect(retenus(), hasLength(1));
      expect(retenus().single.properties.label, 'Moov Flooz');
    });

    testWidgets('sans réseau annoncé, le sélecteur le dit et reste inerte', (
      tester,
    ) async {
      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(
          catalog: const PaymentCatalog(country: 'BJ', currency: 'XOF'),
        ),
      );

      expect(
        find.text('Aucun moyen de paiement n’est disponible pour le moment.'),
        findsOneWidget,
      );
    });
  });

  group('un paiement déjà lancé', () {
    /// Un [SeatHoldBloc] qui retient déjà un siège.
    ///
    /// Posé ici et non dans le corps du test : `testWidgets` fait
    /// tourner une horloge feinte, et le blocage — qui passe par un
    /// devis puis deux appels — n'y aboutirait jamais sans pompage.
    late SeatHoldBloc held;

    setUp(() async {
      held = SeatHoldBloc(StubReservationRepository());
      addTearDown(held.close);
      await holdOneSeat(held);
    });

    /// Un [PaymentFlowBloc] dont la demande est partie et déjà tranchée.
    ///
    /// C'est l'état dans lequel on retrouve l'écran quand le voyageur
    /// revient dessus par le geste « retour » depuis l'échec : le bloc
    /// garde la tentative pour ne pas rouvrir un second paiement.
    Future<PaymentFlowBloc> flowWith(
      PaymentAttempt attempt, {
      String reservationId = 'res-1',
    }) async {
      final bloc = PaymentFlowBloc(StubPaymentRepository(attempt: attempt));
      addTearDown(bloc.close);

      bloc.add(
        PaymentFlowStarted(
          reservationId: reservationId,
          provider: 'fedapay',
          network: 'mtn_momo',
          phone: '+2290166000001',
        ),
      );
      await bloc.stream.firstWhere((state) => !state.isStarting);

      return bloc;
    }

    testWidgets('après un refus, on peut repayer sur place', (tester) async {
      // Le voyageur corrige son numéro et réessaie ici même : c'est ce
      // qu'il fait naturellement. L'écran lui opposait « Changer de
      // moyen », qui le renvoyait choisir un moyen qu'il avait déjà
      // choisi.
      final flow = await flowWith(
        const PaymentAttempt(
          id: 'pay-1',
          status: PaymentAttemptStatus.failed,
          reference: 'KE-PAY-1',
        ),
      );

      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
        flow: flow,
      );

      expect(find.text('Payer ${money(12500)}'), findsOneWidget);
      expect(find.text('Changer de moyen'), findsNothing);
    });

    testWidgets('celui d’une autre réservation ne verrouille rien', (
      tester,
    ) async {
      // [PaymentFlowBloc] vit au niveau du parcours et redemande au
      // serveur, à l'entrée, l'état du paiement laissé en attente au
      // lancement précédent. Ce paiement peut porter sur **une autre**
      // réservation : son statut pilotait quand même cet écran, et le
      // bouton « Payer » devenait « Suivre le paiement en cours » — la
      // réservation en cours ne se payait alors plus du tout.
      final flow = await flowWith(
        const PaymentAttempt(
          id: 'pay-ancien',
          status: PaymentAttemptStatus.pending,
          reference: 'KE-PAY-ANCIEN',
        ),
        reservationId: 'une-autre-reservation',
      );

      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
        flow: flow,
        hold: held,
        settle: false,
      );

      expect(find.text('Payer ${money(12500)}'), findsOneWidget);
      expect(find.text('Suivre le paiement en cours'), findsNothing);

      // Deux battements à couper avant la fin du test, que le cadre
      // déclarerait sinon comme des minuteurs restés en suspens : le
      // décompte du blocage, à la seconde, et la relecture du paiement
      // en attente, aux dix secondes. `runAsync` sort de l'horloge
      // feinte, sans quoi l'attente des fermetures ne se résoudrait
      // jamais.
      await tester.runAsync(flow.close);
      await tester.runAsync(held.close);
    });

    testWidgets('celui de la réservation en cours, lui, se suit', (
      tester,
    ) async {
      // L'autre moitié de la règle : le paiement qui porte bien sur le
      // blocage courant continue de prendre la main sur l'écran, sans
      // quoi le voyageur relancerait un second débit.
      final flow = await flowWith(
        const PaymentAttempt(
          id: 'pay-1',
          status: PaymentAttemptStatus.pending,
          reference: 'KE-PAY-1',
        ),
        reservationId: kHeldReservationId,
      );

      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
        flow: flow,
        hold: held,
        settle: false,
      );

      expect(find.text('Suivre le paiement en cours'), findsOneWidget);
      expect(find.text('Payer ${money(12500)}'), findsNothing);

      await tester.runAsync(flow.close);
      await tester.runAsync(held.close);
    });
  });

  group('l’indicatif suit l’opérateur', () {
    /// L'indicatif affiché dans le champ du numéro.
    PhoneCountry shown(WidgetTester tester) => tester
        .widget<DropdownButtonFormField<PhoneCountry>>(
          find.byType(DropdownButtonFormField<PhoneCountry>),
        )
        .initialValue!;

    Future<void> open(WidgetTester tester) async => pump(
      tester,
      const MobileMoneyPage(
        args: PaymentArgs(
          total: Price(12500),
          provider: 'fedapay',
          phone: '+229 01 97 42 18 60',
        ),
      ),
      catalog: await loadedCatalog(catalog: kStubGatewayCatalog),
    );

    testWidgets('une marque ivoirienne amène l’indicatif ivoirien', (
      tester,
    ) async {
      // Wave et Orange Money ne sont servis qu'en Côte d'Ivoire : les
      // choisir sous l'indicatif béninois ne menait nulle part, et
      // l'écran renvoyait le voyageur changer d'indicatif lui-même.
      await open(tester);
      expect(shown(tester).code, '+229');

      await tester.tap(find.text('Wave'));
      await tester.pumpAndSettle();

      expect(shown(tester).code, '+225');

      // Le `01` béninois ne survit pas au changement de pays.
      expect(find.text('01 97 42 18 60'), findsNothing);
    });

    testWidgets('T-Money amène l’indicatif togolais', (tester) async {
      await open(tester);

      await tester.tap(find.text('T-Money'));
      await tester.pumpAndSettle();

      expect(shown(tester).code, '+228');
    });

    testWidgets('MTN MoMo laisse l’indicatif béninois en place', (
      tester,
    ) async {
      // MTN est annoncé au Bénin : rien à corriger, et corriger quand
      // même déplacerait le compte du voyageur dans un autre pays.
      await open(tester);

      await tester.tap(find.text('MTN MoMo'));
      await tester.pumpAndSettle();

      expect(shown(tester).code, '+229');
      expect(find.text('01 97 42 18 60'), findsOneWidget);
    });

    testWidgets('un indicatif déjà servi n’est pas écrasé', (tester) async {
      // Moov sert les trois pays : une fois le voyageur passé en Côte
      // d'Ivoire, le choisir ne doit pas le ramener au Bénin.
      await open(tester);

      await tester.tap(find.text('Wave'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Moov Flooz'));
      await tester.pumpAndSettle();

      expect(shown(tester).code, '+225');
    });
  });

  group('indicatif et numéro', () {
    testWidgets('le 01 béninois disparaît quand on passe au Togo', (
      tester,
    ) async {
      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
      );

      // Le compte est béninois : le champ part avec son `01`.
      expect(find.text('01 97 42 18 60'), findsOneWidget);

      await tester.tap(find.byType(DropdownButtonFormField<PhoneCountry>));
      await tester.pumpAndSettle();
      await tester.tap(find.textContaining('+228').last);
      await tester.pumpAndSettle();

      // Un numéro togolais ne commence pas par 01 : le garder afficherait
      // un numéro qui n'existe dans aucun des deux pays.
      expect(find.text('01 97 42 18 60'), findsNothing);
      expect(find.text('97 42 18 60'), findsOneWidget);
    });

    testWidgets('un numéro béninois sans 01 est refusé, un togolais non', (
      tester,
    ) async {
      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
      );

      // Le reproche n'attend plus l'appui : « Payer » étant inerte tant
      // que le numéro ne tient pas, l'attendre laisserait le voyageur
      // devant un bouton éteint sans lui dire pourquoi.
      await tester.enterText(find.byType(KoraInput), '97 42 18 60');
      await tester.pumpAndSettle();

      expect(find.text('Un numéro Bénin commence par 01.'), findsOneWidget);

      // Le même numéro, sous indicatif togolais, ne doit plus rien
      // déclencher : le préfixe est une règle béninoise, pas une règle de
      // l'écran.
      await tester.tap(find.byType(DropdownButtonFormField<PhoneCountry>));
      await tester.pumpAndSettle();
      await tester.tap(find.textContaining('+228').last);
      await tester.pumpAndSettle();

      expect(find.text('Un numéro Bénin commence par 01.'), findsNothing);
    });
  });

  group('Wallet', () {
    /// Le Wallet tel que le serveur le rend, passé à l'écran pour ne pas
    /// dépendre du réseau. L'écran l'a longtemps eu écrit en dur — c'est
    /// précisément ce qu'on ne veut plus.
    const account = WalletAccount(
      id: 'w-1',
      cash: Price(18750),
      points: 2480,
      rules: WalletRules(
        cashEnabled: true,
        pointsEnabled: true,
        pointValue: 1,
        pointsMinSpend: 100,
      ),
    );

    testWidgets('affiche le solde du serveur et le reste à payer', (
      tester,
    ) async {
      await pump(tester, const WalletPaymentPage(args: args, account: account));

      expect(find.text(money(18750)), findsOneWidget);
      expect(find.text('Total du billet'), findsOneWidget);
      // Rien n'est engagé d'office : le reste vaut le billet entier.
      expect(find.text(money(12500)), findsWidgets);
    });

    testWidgets('le crédit ajouté diminue le complément', (tester) async {
      await pump(tester, const WalletPaymentPage(args: args, account: account));

      await tester.tap(find.byIcon(Icons.add_rounded));
      await tester.pumpAndSettle();

      // 1 000 F de solde sur 12 500 : il reste 11 500.
      expect(find.text(money(11500)), findsOneWidget);
    });

    testWidgets('sans Wallet lu, rien ne se répartit', (tester) async {
      // L'écran ne connaît ni solde ni points : proposer un curseur
      // laisserait engager une somme que le serveur refusera.
      await pump(tester, const WalletPaymentPage(args: args), settle: false);
      await tester.pump();

      expect(find.byType(Slider), findsNothing);
      final confirm = tester.widget<KoraButton>(
        find.widgetWithText(KoraButton, 'Confirmer et continuer'),
      );
      expect(confirm.onPressed, isNull);
    });
  });

  group('carte bancaire', () {
    testWidgets('ne demande aucun numéro de carte', (tester) async {
      // L'écran réclamait autrefois le numéro, la date et le CVV — sans
      // jamais les envoyer. Depuis MEZ-146, c'est le widget KKiaPay qui
      // les recueille, chez le prestataire : faire transiter un PAN par
      // nos serveurs y ferait entrer les obligations PCI-DSS.
      await pump(
        tester,
        const GatewayCheckoutPage(method: PaymentCatalog.cardCode, args: args),
        catalog: await loadedCatalog(),
      );

      expect(find.text('Numéro de carte'), findsNothing);
      expect(find.byType(TextField), findsNothing);
    });

    testWidgets('dit où le numéro sera saisi', (tester) async {
      // Un écran qui retire un formulaire sans rien dire laisse croire à
      // une panne : il explique donc ce qui va s'ouvrir.
      await pump(
        tester,
        const GatewayCheckoutPage(method: PaymentCatalog.cardCode, args: args),
        catalog: await loadedCatalog(),
      );

      expect(find.textContaining('chez notre prestataire'), findsOneWidget);
      expect(find.text('Paiement par carte'), findsOneWidget);
      expect(find.text(money(12500)), findsOneWidget);
    });
  });

  group('Mobile Money par la feuille de la passerelle', () {
    testWidgets('ne demande pas l’opérateur', (tester) async {
      // KKiaPay encaisse dans sa propre fenêtre et y demande
      // l'opérateur : la liste que montre l'écran FedaPay ferait choisir
      // deux fois, et le second choix écraserait le premier.
      await pump(
        tester,
        const GatewayCheckoutPage(
          method: PaymentCatalog.mobileMoneyCode,
          args: PaymentArgs(total: Price(12500), provider: 'kkiapay'),
        ),
        catalog: await loadedCatalog(catalog: kStubGatewayCatalog),
      );

      expect(find.text('Choisissez votre opérateur'), findsNothing);
      expect(find.text('MTN MoMo'), findsNothing);
      expect(find.byType(TextField), findsNothing);
    });

    testWidgets('dit où l’opérateur se choisira', (tester) async {
      // Un écran qui ne demande rien laisse croire à une panne : il
      // annonce donc la fenêtre qui va s'ouvrir, et sous quel nom.
      await pump(
        tester,
        const GatewayCheckoutPage(
          method: PaymentCatalog.mobileMoneyCode,
          args: PaymentArgs(total: Price(12500), provider: 'kkiapay'),
        ),
        catalog: await loadedCatalog(catalog: kStubGatewayCatalog),
      );

      expect(find.text('Mobile Money'), findsOneWidget);
      expect(
        find.textContaining('se choisissent dans la fenêtre KKiaPay'),
        findsOneWidget,
      );
      expect(find.text('Payer ${money(12500)}'), findsOneWidget);
    });
  });
}
