// L'historique complet du Wallet — point 3 de MEZ-160.
//
// Un relevé se lit par curseur, pas par numéro de page : le serveur
// répond « voici vingt écritures, et voici de quoi demander la suite ».
// Ces tests tiennent les trois choses qui s'en déduisent : on enchaîne
// les pages sans en répéter, changer de filtre repart de zéro, et une
// suite qui échoue n'emporte pas ce qui est déjà lu.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_account.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_movement.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_top_up.dart';
import 'package:kora_app_passenger/features/wallet/domain/repositories/wallet_repository.dart';
import 'package:kora_app_passenger/features/wallet/presentation/bloc/wallet_history_cubit.dart';
import 'package:kora_app_passenger/features/wallet/presentation/pages/wallet_history_page.dart';

/// Un dépôt qui sert des pages préparées et note chaque demande.
class _PagedRepository implements WalletRepository {
  _PagedRepository(this.pages, {this.failOn = const {}});

  /// Les pages à servir, dans l'ordre d'appel.
  final List<WalletLedgerPage> pages;

  /// Les index d'appel qui doivent échouer.
  final Set<int> failOn;

  /// Ce qui a été demandé : (filtre de solde, curseur).
  final List<(WalletBalanceKind?, String?)> calls = [];

  @override
  Future<Either<Failure, WalletAccount>> fetchAccount() async =>
      const Left(ServerFailure('Hors sujet ici.'));

  @override
  Future<Either<Failure, WalletLedgerPage>> fetchMovements({
    WalletBalanceKind? kind,
    String? cursor,
  }) async {
    final index = calls.length;
    calls.add((kind, cursor));

    if (failOn.contains(index)) return const Left(ServerFailure('Panne.'));
    if (index >= pages.length) {
      return const Right(WalletLedgerPage(movements: []));
    }

    return Right(pages[index]);
  }

  // La recharge a son propre écran et ses propres tests.
  @override
  Future<Either<Failure, WalletTopUp>> initiateTopUp({
    required int amount,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? phone,
    String? network,
  }) async => const Left(ServerFailure('Hors sujet ici.'));

  @override
  Future<Either<Failure, WalletTopUp>> fetchTopUp(String uuid) async =>
      const Left(ServerFailure('Hors sujet ici.'));
}

WalletMovement movement(
  int n, {
  WalletBalanceKind kind = WalletBalanceKind.points,
  WalletReason reason = WalletReason.pointsEarn,
  WalletDirection direction = WalletDirection.credit,
}) {
  return WalletMovement(
    id: 'm-$n',
    kind: kind,
    direction: direction,
    amount: n,
    reason: reason,
    createdAt: DateTime(2026, 10, 28),
  );
}

void main() {
  setUpAll(() async {
    await initializeDateFormatting();
    await AppLocalization.preloadAll();
  });

  group('le cubit', () {
    test('enchaîne les pages par curseur sans répéter', () async {
      final repository = _PagedRepository([
        WalletLedgerPage(
          movements: [movement(1), movement(2)],
          nextCursor: 'c1',
        ),
        WalletLedgerPage(movements: [movement(3)]),
      ]);
      final cubit = WalletHistoryCubit(repository);

      await cubit.load();
      expect(cubit.state.movements.length, 2);
      expect(cubit.state.hasMore, isTrue);

      await cubit.loadMore();
      expect(cubit.state.movements.map((m) => m.id), ['m-1', 'm-2', 'm-3']);
      // Plus de curseur : on est au bout, et le pied doit le dire.
      expect(cubit.state.hasMore, isFalse);
      expect(repository.calls, [(null, null), (null, 'c1')]);

      await cubit.close();
    });

    test('ne redemande rien une fois au bout', () async {
      final repository = _PagedRepository([
        WalletLedgerPage(movements: [movement(1)]),
      ]);
      final cubit = WalletHistoryCubit(repository);

      await cubit.load();
      await cubit.loadMore();
      await cubit.loadMore();

      expect(repository.calls.length, 1);

      await cubit.close();
    });

    test('une suite en panne garde ce qui est déjà lu', () async {
      final repository = _PagedRepository(
        [
          WalletLedgerPage(movements: [movement(1)], nextCursor: 'c1'),
        ],
        failOn: {1},
      );
      final cubit = WalletHistoryCubit(repository);

      await cubit.load();
      await cubit.loadMore();

      expect(cubit.state.movements.map((m) => m.id), ['m-1']);
      expect(cubit.state.moreFailed, isTrue);
      // Le curseur reste : réessayer doit redemander la même page.
      expect(cubit.state.hasMore, isTrue);

      await cubit.close();
    });

    test('une liste vide ne se dit vide qu’après une réponse', () async {
      final repository = _PagedRepository([
        const WalletLedgerPage(movements: []),
      ]);
      final cubit = WalletHistoryCubit(repository);

      expect(cubit.state.isEmpty, isFalse);

      await cubit.load();
      expect(cubit.state.isEmpty, isTrue);

      await cubit.close();
    });
  });

  group('l’écran', () {
    Future<_PagedRepository> pump(
      WidgetTester tester, {
      required List<WalletLedgerPage> pages,
      Set<int> failOn = const {},
    }) async {
      tester.view.physicalSize = const Size(390 * 3, 1000 * 3);
      tester.view.devicePixelRatio = 3;
      addTearDown(tester.view.reset);

      final repository = _PagedRepository(pages, failOn: failOn);

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
          home: WalletHistoryPage(cubit: WalletHistoryCubit(repository)),
        ),
      );
      await tester.pumpAndSettle();

      return repository;
    }

    testWidgets('mêle les deux soldes dans une seule liste', (tester) async {
      // Après un paiement mixte, les points dépensés et le cash débité
      // doivent se lire l'un sous l'autre : deux relevés séparés
      // obligeraient le voyageur à les recoller lui-même.
      await pump(
        tester,
        pages: [
          WalletLedgerPage(
            movements: [
              movement(375),
              movement(
                2000,
                kind: WalletBalanceKind.cash,
                reason: WalletReason.topUp,
              ),
            ],
          ),
        ],
      );

      expect(find.text('Points gagnés'), findsOneWidget);
      expect(find.text('Recharge'), findsOneWidget);
    });

    testWidgets('un onglet ne montre que les écritures de ce sens', (
      tester,
    ) async {
      await pump(
        tester,
        pages: [
          WalletLedgerPage(
            movements: [
              movement(375),
              movement(
                6000,
                kind: WalletBalanceKind.cash,
                reason: WalletReason.purchase,
                direction: WalletDirection.debit,
              ),
            ],
          ),
        ],
      );

      expect(find.text('Points gagnés'), findsOneWidget);
      expect(find.text('Paiement'), findsOneWidget);

      // `KoraTag` met ses libellés en capitales.
      await tester.tap(find.text('UTILISATIONS'));
      await tester.pumpAndSettle();

      expect(find.text('Points gagnés'), findsNothing);
      expect(find.text('Paiement'), findsOneWidget);
    });

    testWidgets('un relevé vide le dit sans accuser une panne', (tester) async {
      await pump(tester, pages: [const WalletLedgerPage(movements: [])]);

      expect(find.text('Rien à afficher'), findsOneWidget);
    });

    testWidgets('une panne ne recopie pas le texte du serveur', (tester) async {
      await pump(
        tester,
        pages: const [WalletLedgerPage(movements: [])],
        failOn: {0},
      );

      expect(find.text('Historique indisponible'), findsOneWidget);
      expect(find.text('Panne.'), findsNothing);
    });

    testWidgets('le bout du relevé se dit', (tester) async {
      await pump(
        tester,
        pages: [
          WalletLedgerPage(movements: [movement(1)]),
        ],
      );

      expect(find.text('Vous avez tout vu.'), findsOneWidget);
    });
  });
}
