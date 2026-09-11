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
import 'package:kora_app_passenger/features/payment/domain/entities/payment_catalog.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_catalog_bloc.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/card_payment_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/mobile_money_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_args.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/payment_method_page.dart';
import 'package:kora_app_passenger/features/payment/presentation/pages/wallet_payment_page.dart';

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

  Future<void> pump(
    WidgetTester tester,
    Widget page, {
    PaymentCatalogBloc? catalog,
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
        home: withSeatHold(page, bloc: holdBloc, catalog: catalog),
      ),
    );
    await tester.pumpAndSettle();
  }

  group('choix du moyen', () {
    testWidgets('ne propose que ce que le serveur encaisse', (tester) async {
      await pump(
        tester,
        const PaymentMethodPage(args: args),
        catalog: await loadedCatalog(),
      );

      expect(find.text('Comment souhaitez-vous payer ?'), findsOneWidget);
      expect(find.text('Mobile Money'), findsOneWidget);
      expect(find.text('KORA Wallet'), findsOneWidget);
      expect(find.text(money(12500)), findsOneWidget);

      // La maquette montrait un troisième pavé. Le serveur n'annonce pas
      // ce moyen : le proposer mènerait à un paiement refusé après que le
      // voyageur a saisi son numéro de carte.
      expect(find.text('Carte bancaire'), findsNothing);
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

    testWidgets('refuse de payer sans opérateur', (tester) async {
      await pump(
        tester,
        const MobileMoneyPage(args: args),
        catalog: await loadedCatalog(),
      );

      await tester.tap(find.text('Payer ${money(12500)}'));
      await tester.pumpAndSettle();

      expect(find.text('Choisissez un opérateur'), findsOneWidget);
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

      await tester.tap(find.text('Sélectionner un opérateur'));
      await tester.pumpAndSettle();

      expect(find.text('MTN MoMo'), findsWidgets);
      expect(find.text('Moov Flooz'), findsWidgets);
      expect(find.text('Celtiis Cash'), findsWidgets);

      // Wave figurait dans la maquette ; le serveur ne le connaît pas.
      expect(find.text('Wave'), findsNothing);
      // FedaPay encaisse, mais n'est pas un opérateur téléphonique.
      expect(find.text('FedaPay'), findsNothing);
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

      await tester.enterText(find.byType(KoraInput), '97 42 18 60');
      await tester.tap(find.text('Payer ${money(12500)}'));
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
    testWidgets('affiche le solde et le reste à payer', (tester) async {
      await pump(tester, const WalletPaymentPage(args: args));

      expect(find.text(money(18750)), findsOneWidget);
      expect(find.text('Total du billet'), findsOneWidget);
      // 100 points d'office, donc 12 400 restants.
      expect(find.text(money(12400)), findsOneWidget);
    });

    testWidgets('le crédit ajouté diminue le complément', (tester) async {
      await pump(tester, const WalletPaymentPage(args: args));

      await tester.tap(find.byIcon(Icons.add_rounded));
      await tester.pumpAndSettle();

      // 1 000 de crédit + 100 points sur 12 500.
      expect(find.text(money(11400)), findsOneWidget);
    });
  });

  group('carte bancaire', () {
    testWidgets('n’affiche aucun numéro pré-rempli', (tester) async {
      // La maquette pré-remplit `4242 4242 4242 4242` ; un numéro affiché
      // d'avance apprend à ne pas vérifier ce qu'on paie.
      await pump(tester, const CardPaymentPage(args: args));

      expect(find.text('Numéro de carte'), findsOneWidget);
      expect(find.textContaining('4242'), findsNothing);
    });

    testWidgets('rejette un numéro qui ne passe pas la clé de Luhn', (
      tester,
    ) async {
      await pump(tester, const CardPaymentPage(args: args));

      await tester.enterText(find.byType(TextField).at(1), '4242424242424241');
      await tester.tap(find.text('Payer ${money(12500)}'));
      await tester.pumpAndSettle();

      expect(find.text('Numéro de carte invalide'), findsOneWidget);
    });

    testWidgets('rejette une date d’expiration passée', (tester) async {
      await pump(tester, const CardPaymentPage(args: args));

      await tester.enterText(find.byType(TextField).at(2), '01 / 20');
      await tester.tap(find.text('Payer ${money(12500)}'));
      await tester.pumpAndSettle();

      expect(find.text('Date invalide'), findsOneWidget);
    });
  });
}
