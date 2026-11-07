// L'onglet Wallet, branché sur le serveur.
//
// L'écran affichait « 18 750 FCFA » et « 2 480 points » écrits en dur.
// Ces tests tiennent la règle qui a remplacé ces chiffres : ce qui est
// montré vient du serveur, et quand le serveur ne répond pas, l'écran le
// dit au lieu d'inventer un solde.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_account.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_movement.dart';
import 'package:kora_app_passenger/features/wallet/domain/repositories/wallet_repository.dart';
import 'package:kora_app_passenger/features/wallet/presentation/bloc/wallet_cubit.dart';
import 'package:kora_app_passenger/features/wallet/presentation/pages/wallet_page.dart';

/// Un dépôt qui rend ce que le test lui donne, et note ce qu'on a
/// demandé.
class _StubWalletRepository implements WalletRepository {
  _StubWalletRepository({
    this.account,
    this.movements,
    this.failure,
    this.delay = Duration.zero,
  });

  final WalletAccount? account;
  final List<WalletMovement>? movements;
  final Failure? failure;

  /// Le temps que met le serveur : sans attente, l'écran est déjà
  /// rempli à la première image et le squelette ne se voit jamais.
  final Duration delay;

  final List<String> calls = [];

  @override
  Future<Either<Failure, WalletAccount>> fetchAccount() async {
    calls.add('account');
    await Future<void>.delayed(delay);
    final value = account;
    if (value == null) return Left(failure ?? const ServerFailure('Panne.'));

    return Right(value);
  }

  @override
  Future<Either<Failure, WalletLedgerPage>> fetchMovements({
    WalletBalanceKind? kind,
    String? cursor,
  }) async {
    calls.add('movements');
    await Future<void>.delayed(delay);
    final value = movements;
    if (value == null) return Left(failure ?? const ServerFailure('Panne.'));

    return Right(WalletLedgerPage(movements: value));
  }
}

void main() {
  setUpAll(() async {
    await initializeDateFormatting();
    await AppLocalization.preloadAll();
  });

  setUp(() {
    // Le presse-papiers passe par un canal de plateforme, absent des
    // tests : sans ce double, la copie du code parrain lèverait avant
    // d'afficher quoi que ce soit.
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          SystemChannels.platform,
          (call) async => null,
        );
    addTearDown(
      () => TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(SystemChannels.platform, null),
    );
  });

  final today = DateTime(2026, 11, 4);

  const rules = WalletRules(
    cashEnabled: true,
    pointsEnabled: true,
    pointValue: 1,
    pointsMinSpend: 100,
    expiryReminderDays: [30, 7, 1],
  );

  WalletAccount accountWith({
    DateTime? expiry,
    String? referral = 'KORA-4F2A',
    WalletRules walletRules = rules,
  }) {
    return WalletAccount(
      id: 'w-1',
      cash: const Price(18750),
      points: 2480,
      pointsExpireAt: expiry,
      referralCode: referral,
      rules: walletRules,
    );
  }

  final movement = WalletMovement(
    id: 'm-1',
    kind: WalletBalanceKind.points,
    direction: WalletDirection.credit,
    amount: 375,
    reason: WalletReason.pointsEarn,
    createdAt: DateTime(2026, 10, 28),
  );

  Future<_StubWalletRepository> pump(
    WidgetTester tester, {
    WalletAccount? account,
    List<WalletMovement>? movements = const [],
    Failure? failure,
    Duration delay = Duration.zero,
    bool settle = true,
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1200 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final repository = _StubWalletRepository(
      account: account,
      movements: movements,
      failure: failure,
      delay: delay,
    );

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
        home: WalletPage(cubit: WalletCubit(repository), today: today),
      ),
    );

    if (settle) await tester.pumpAndSettle();

    return repository;
  }

  group('les soldes', () {
    testWidgets('viennent du serveur, jamais d’un montant écrit en dur', (
      tester,
    ) async {
      await pump(tester, account: accountWith());

      expect(find.text(const Price(18750).formatted), findsOneWidget);
      expect(find.textContaining('2480'), findsOneWidget);
    });

    testWidgets('n’affichent rien tant que le serveur n’a pas répondu', (
      tester,
    ) async {
      // Un squelette, pas un montant provisoire : un solde faux, même une
      // seconde, est pire qu'une attente.
      await pump(
        tester,
        account: accountWith(),
        delay: const Duration(milliseconds: 40),
        settle: false,
      );
      await tester.pump();

      expect(find.textContaining('FCFA'), findsNothing);

      await tester.pumpAndSettle();
      expect(find.text(const Price(18750).formatted), findsOneWidget);
    });
  });

  group('l’échéance des points', () {
    testWidgets('se lit en permanence sous la bande', (tester) async {
      await pump(tester, account: accountWith(expiry: DateTime(2027, 4, 30)));

      expect(find.textContaining('30 avril 2027'), findsOneWidget);
    });

    testWidgets('se dit absente plutôt que de laisser un vide', (tester) async {
      await pump(tester, account: accountWith());

      expect(find.text('Pas d’échéance pour le moment'), findsOneWidget);
    });

    testWidgets('alerte quand le seuil serveur est franchi', (tester) async {
      await pump(tester, account: accountWith(expiry: DateTime(2026, 11, 10)));

      expect(find.textContaining('expirent dans 6 jours'), findsOneWidget);
    });

    testWidgets('n’alerte pas quand l’échéance est loin', (tester) async {
      await pump(tester, account: accountWith(expiry: DateTime(2027, 4, 30)));

      expect(find.textContaining('expirent dans'), findsNothing);
    });
  });

  group('le code parrain', () {
    testWidgets('s’affiche et se copie', (tester) async {
      await pump(tester, account: accountWith());

      expect(find.text('KORA-4F2A'), findsOneWidget);

      await tester.tap(find.byIcon(Icons.copy_rounded));
      // La copie passe par le canal de plateforme : il faut laisser le
      // `await` se résoudre avant que le message ne s'affiche.
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));

      expect(find.text('Code copié'), findsOneWidget);
    });

    testWidgets('se dit non renseigné quand le serveur n’en donne pas', (
      tester,
    ) async {
      await pump(tester, account: accountWith(referral: null));

      expect(find.text('Non renseigné'), findsOneWidget);
      expect(find.byIcon(Icons.copy_rounded), findsNothing);
    });
  });

  group('les derniers mouvements', () {
    testWidgets('portent leur libellé métier et leur signe', (tester) async {
      await pump(tester, account: accountWith(), movements: [movement]);

      expect(find.text('Points gagnés'), findsOneWidget);
      expect(find.text('+ 375 pts'), findsOneWidget);
    });

    testWidgets('disent qu’il n’y en a aucun', (tester) async {
      await pump(tester, account: accountWith());

      expect(find.text('Aucun mouvement pour l’instant.'), findsOneWidget);
    });

    testWidgets('un relevé en panne n’emporte pas les soldes', (tester) async {
      // Le solde reste utile même si l'historique ne répond pas : on le
      // garde à l'écran et on le dit pour la seule section concernée.
      await pump(tester, account: accountWith(), movements: null);

      expect(find.text(const Price(18750).formatted), findsOneWidget);
      expect(
        find.text('Mouvements indisponibles pour le moment.'),
        findsOneWidget,
      );
    });
  });

  group('les états de l’écran', () {
    testWidgets('une panne des soldes propose de réessayer', (tester) async {
      final repository = await pump(
        tester,
        failure: const ServerFailure('Service indisponible.'),
      );

      expect(find.text('Soldes indisponibles'), findsOneWidget);
      expect(find.text('Service indisponible.'), findsOneWidget);

      await tester.tap(find.text('Réessayer'));
      await tester.pumpAndSettle();

      expect(repository.calls.where((call) => call == 'account').length, 2);
    });

    testWidgets('un Wallet pas encore ouvert le dit', (tester) async {
      await pump(
        tester,
        account: accountWith(walletRules: const WalletRules()),
      );

      expect(find.text('Wallet pas encore ouvert'), findsOneWidget);
    });
  });
}
