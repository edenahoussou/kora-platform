// Recharger le Wallet — point 2 de MEZ-160.
//
// Les bornes du montant sont des réglages d'administration, pas des
// constantes : ces tests tiennent le fait que l'écran les lit au lieu de
// les connaître, et qu'il refuse exactement ce que le serveur refuse —
// y compris le plafond, qui porte sur le solde **après** recharge.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_attempt.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_catalog.dart';
import 'package:kora_app_passenger/features/payment/domain/repositories/payment_repository.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_account.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_movement.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_top_up.dart';
import 'package:kora_app_passenger/features/wallet/domain/repositories/wallet_repository.dart';
import 'package:kora_app_passenger/features/wallet/presentation/bloc/wallet_top_up_cubit.dart';
import 'package:kora_app_passenger/features/wallet/presentation/pages/wallet_top_up_page.dart';

class _StubWallet implements WalletRepository {
  _StubWallet({this.account, this.topUpFailure});

  final WalletAccount? account;

  /// Ce que le serveur répond à `POST /wallet/topups`.
  final Failure? topUpFailure;

  /// Ce qui a été envoyé : (montant, moyen, provider, clé).
  final List<(int, String, String, String)> submissions = [];

  @override
  Future<Either<Failure, WalletAccount>> fetchAccount() async {
    final value = account;
    if (value == null) return const Left(ServerFailure('Panne.'));

    return Right(value);
  }

  @override
  Future<Either<Failure, WalletLedgerPage>> fetchMovements({
    WalletBalanceKind? kind,
    WalletDirection? direction,
    String? cursor,
  }) async => const Right(WalletLedgerPage(movements: []));

  @override
  Future<Either<Failure, WalletTopUp>> initiateTopUp({
    required int amount,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? phone,
    String? network,
  }) async {
    submissions.add((amount, method, provider, idempotencyKey));

    final refusal = topUpFailure;
    if (refusal != null) return Left(refusal);

    return Right(
      WalletTopUp(
        id: 't-1',
        amount: Price(amount),
        status: WalletTopUpStatus.pending,
        attempt: const PaymentAttempt(
          id: 'p-1',
          status: PaymentAttemptStatus.pending,
          nextAction: 'wait',
        ),
      ),
    );
  }

  @override
  Future<Either<Failure, WalletAccount>> attachReferral(String code) async =>
      throw UnimplementedError();

  @override
  Future<Either<Failure, WalletTopUp>> fetchTopUp(String uuid) async =>
      const Left(ServerFailure('Hors sujet ici.'));
}

class _StubPayments implements PaymentRepository {
  _StubPayments(this.catalogue);

  final PaymentCatalog? catalogue;

  @override
  Future<Either<Failure, PaymentCatalog>> catalog() async {
    final value = catalogue;
    if (value == null) return const Left(ServerFailure('Catalogue muet.'));

    return Right(value);
  }

  @override
  Future<Either<Failure, PaymentAttempt>> initiate({
    required String reservationId,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? network,
    String? phone,
    int? walletAmount,
    int? pointsAmount,
  }) async => const Left(ServerFailure('Hors sujet ici.'));

  @override
  Future<Either<Failure, PaymentAttempt>> cancel(String paymentId) async =>
      const Left(ServerFailure('Hors sujet ici.'));

  @override
  Future<Either<Failure, PaymentAttempt>> status(String paymentId) async =>
      const Left(ServerFailure('Hors sujet ici.'));

  @override
  Future<Either<Failure, PaymentAttempt>> verify(
    String paymentId, {
    String? transactionId,
  }) async => const Left(ServerFailure('Hors sujet ici.'));
}

void main() {
  setUpAll(() async {
    await initializeDateFormatting();
    await AppLocalization.preloadAll();
  });

  const catalogue = PaymentCatalog(
    country: 'BJ',
    currency: 'XOF',
    methods: [
      PaymentMethod(
        code: 'mobile_money',
        label: 'Mobile Money',
        enabled: true,
        providers: [
          PaymentProvider(code: 'kkiapay', label: 'KKiaPay', enabled: true),
        ],
      ),
      PaymentMethod(code: 'wallet', label: 'Wallet KORA BUS', enabled: true),
    ],
  );

  WalletAccount accountWith({
    int cash = 0,
    int? min,
    int? max,
    int? cap,
    List<int> presets = const [2000, 5000],
    bool cashEnabled = true,
  }) {
    return WalletAccount(
      id: 'w-1',
      cash: Price(cash),
      points: 0,
      rules: WalletRules(
        cashEnabled: cashEnabled,
        pointsEnabled: true,
        topupMin: min,
        topupMax: max,
        cashBalanceCap: cap,
        topupPresets: presets,
      ),
    );
  }

  group('les bornes du montant', () {
    Future<WalletTopUpCubit> cubitWith(WalletAccount account) async {
      final cubit = WalletTopUpCubit(
        _StubWallet(account: account),
        _StubPayments(catalogue),
      );
      await cubit.load();

      return cubit;
    }

    test('viennent du serveur, jamais d’un seuil écrit dans l’app', () async {
      final cubit = await cubitWith(accountWith(min: 1000, max: 50000));

      cubit.chooseAmount(500);
      expect(cubit.state.amountIssue, TopUpAmountIssue.tooSmall);

      cubit.chooseAmount(60000);
      expect(cubit.state.amountIssue, TopUpAmountIssue.tooLarge);

      cubit.chooseAmount(5000);
      expect(cubit.state.amountIssue, isNull);

      await cubit.close();
    });

    test('sans borne serveur, tout montant positif passe', () async {
      // C'est le réglage de staging : ni minimum ni maximum. L'app ne
      // doit pas en inventer.
      final cubit = await cubitWith(accountWith());

      cubit.chooseAmount(1);
      expect(cubit.state.amountIssue, isNull);

      cubit.chooseAmount(0);
      expect(cubit.state.amountIssue, TopUpAmountIssue.empty);

      await cubit.close();
    });

    test('le plafond porte sur le solde après recharge', () async {
      // La règle du serveur (`assertAmount`) compare `solde + montant`
      // au plafond, pas le montant seul. La lire autrement ferait
      // accepter ici ce que le serveur refusera.
      final cubit = await cubitWith(accountWith(cash: 18000, cap: 20000));

      cubit.chooseAmount(2000);
      expect(cubit.state.amountIssue, isNull);

      cubit.chooseAmount(2001);
      expect(cubit.state.amountIssue, TopUpAmountIssue.overCap);

      await cubit.close();
    });
  });

  group('l’envoi', () {
    test('n’offre pas de recharger le Wallet avec le Wallet', () async {
      // Le catalogue annonce le Wallet comme moyen de paiement : s'en
      // servir pour alimenter le Wallet tournerait en rond.
      final cubit = WalletTopUpCubit(
        _StubWallet(account: accountWith()),
        _StubPayments(catalogue),
      );
      await cubit.load();

      expect(cubit.state.methods.map((m) => m.code), ['mobile_money']);
      expect(cubit.state.method, 'mobile_money');

      await cubit.close();
    });

    test('porte le provider que le catalogue annonce', () async {
      final wallet = _StubWallet(account: accountWith());
      final cubit = WalletTopUpCubit(wallet, _StubPayments(catalogue));
      await cubit.load();

      cubit.chooseAmount(5000);
      await cubit.submit();

      expect(wallet.submissions.single.$1, 5000);
      expect(wallet.submissions.single.$2, 'mobile_money');
      expect(wallet.submissions.single.$3, 'kkiapay');

      await cubit.close();
    });

    test('un refus fait tourner la clé d’idempotence', () async {
      // Rejouer la même clé ferait rendre au serveur la recharge
      // refusée au lieu d'en tenter une neuve.
      final wallet = _StubWallet(
        account: accountWith(),
        topUpFailure: const ValidationFailure('Montant refusé.'),
      );
      final cubit = WalletTopUpCubit(wallet, _StubPayments(catalogue));
      await cubit.load();

      cubit.chooseAmount(5000);
      await cubit.submit();
      await cubit.submit();

      expect(wallet.submissions.length, 2);
      expect(wallet.submissions[0].$4, isNot(equals(wallet.submissions[1].$4)));

      await cubit.close();
    });

    test('un montant hors bornes n’est jamais envoyé', () async {
      final wallet = _StubWallet(account: accountWith(min: 1000));
      final cubit = WalletTopUpCubit(wallet, _StubPayments(catalogue));
      await cubit.load();

      cubit.chooseAmount(500);
      await cubit.submit();

      expect(wallet.submissions, isEmpty);

      await cubit.close();
    });
  });

  group('l’écran', () {
    Future<void> pump(
      WidgetTester tester, {
      required WalletRepository wallet,
      PaymentCatalog? catalogue = catalogue,
    }) async {
      tester.view.physicalSize = const Size(390 * 3, 1000 * 3);
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
          home: WalletTopUpPage(
            cubit: WalletTopUpCubit(wallet, _StubPayments(catalogue)),
          ),
        ),
      );
      await tester.pumpAndSettle();
    }

    testWidgets('une poche cash fermée le dit avant toute saisie', (
      tester,
    ) async {
      // Le serveur refuse la recharge tant que `walletCashEnabled` est
      // à faux. Laisser saisir un montant pour finir sur un refus
      // serait une perte de temps, et une promesse en trop.
      await pump(
        tester,
        wallet: _StubWallet(account: accountWith(cashEnabled: false)),
      );

      expect(find.text('Recharge pas encore ouverte'), findsOneWidget);
      expect(find.text('Montant à recharger'), findsNothing);
    });

    testWidgets('propose les montants réglés par l’administration', (
      tester,
    ) async {
      await pump(
        tester,
        wallet: _StubWallet(account: accountWith(presets: [2000, 10000])),
      );

      expect(find.text(const Price(2000).formatted), findsOneWidget);
      expect(find.text(const Price(10000).formatted), findsOneWidget);
    });

    testWidgets('ne laisse pas continuer sans montant', (tester) async {
      // L'écran suivant demande une somme à encaisser : y mener sans
      // montant ouvrirait une page qui ne sait pas quoi débiter.
      await pump(tester, wallet: _StubWallet(account: accountWith()));

      final button = tester.widget<KoraButton>(
        find.widgetWithText(KoraButton, 'Continuer'),
      );
      expect(button.onPressed, isNull);
    });

    testWidgets('un montant choisi remplit le champ et ouvre la suite', (
      tester,
    ) async {
      await pump(tester, wallet: _StubWallet(account: accountWith()));

      await tester.tap(find.text(const Price(2000).formatted));
      await tester.pumpAndSettle();

      // Le montant se lit là où on l'aurait tapé : on peut le corriger.
      expect(find.text('2000'), findsOneWidget);

      final button = tester.widget<KoraButton>(
        find.widgetWithText(KoraButton, 'Continuer'),
      );
      expect(button.onPressed, isNotNull);
    });

    testWidgets('un montant hors bornes dit pourquoi et bloque la suite', (
      tester,
    ) async {
      await pump(tester, wallet: _StubWallet(account: accountWith(min: 5000)));

      await tester.tap(find.text(const Price(2000).formatted));
      await tester.pumpAndSettle();

      expect(
        find.textContaining('Le montant minimum de recharge est de'),
        findsOneWidget,
      );
      final button = tester.widget<KoraButton>(
        find.widgetWithText(KoraButton, 'Continuer'),
      );
      expect(button.onPressed, isNull);
    });
  });
}
