// Rattacher le code d'un parrain — point 5 du ticket MEZ-160.
//
// Le code se **partage** depuis l'app depuis le début ; il ne s'y
// **rattachait** pas : la route `POST /v1/wallet/referral` était
// déclarée et personne ne l'appelait. Un filleul n'avait donc aucun
// moyen de déclarer son parrain, et le bonus de parrainage ne pouvait
// jamais tomber.
//
// Les règles — son propre code, un parrain déjà rattaché, un compte qui
// a déjà voyagé, un code inconnu — appartiennent au serveur. Ces tests
// tiennent ce que l'app en fait : elle envoie, et montre la réponse.

import 'package:dartz/dartz.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/constants/api_constants.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/wallet/data/datasources/wallet_remote_data_source.dart';
import 'package:kora_app_passenger/features/wallet/data/repositories/wallet_repository_impl.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_account.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_movement.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_top_up.dart';
import 'package:kora_app_passenger/features/wallet/domain/repositories/wallet_repository.dart';
import 'package:kora_app_passenger/features/wallet/presentation/bloc/wallet_bonus_cubit.dart';
import 'package:kora_app_passenger/features/wallet/presentation/pages/wallet_bonuses_page.dart';
import 'package:mocktail/mocktail.dart';

class _MockDio extends Mock implements Dio {}

const _rules = WalletRules(
  cashEnabled: true,
  pointsEnabled: true,
  pointValue: 1,
);

WalletAccount _account({bool hasReferrer = false}) => WalletAccount(
  id: 'w-1',
  cash: const Price(0),
  points: 0,
  referralCode: 'KLV5SA3H',
  hasReferrer: hasReferrer,
  rules: _rules,
);

/// Un dépôt qui note le code reçu et rend ce que le test décide.
class _StubWallet implements WalletRepository {
  _StubWallet({this.refusal});

  /// Le refus du serveur, s'il refuse.
  final Failure? refusal;

  final List<String> codes = [];

  WalletAccount account = _account();

  @override
  Future<Either<Failure, WalletAccount>> attachReferral(String code) async {
    codes.add(code);

    final refused = refusal;
    if (refused != null) return Left(refused);

    account = _account(hasReferrer: true);

    return Right(account);
  }

  @override
  Future<Either<Failure, WalletAccount>> fetchAccount() async => Right(account);

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
  }) async => throw UnimplementedError();

  @override
  Future<Either<Failure, WalletTopUp>> fetchTopUp(String uuid) async =>
      throw UnimplementedError();
}

void main() {
  setUpAll(() async {
    await AppLocalization.preloadAll();
  });

  setUp(() {
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

  test('la demande ne porte que le code parrain', () async {
    final dio = _MockDio();
    when(() => dio.post<dynamic>(any(), data: any(named: 'data'))).thenAnswer(
      (_) async => Response<dynamic>(
        requestOptions: RequestOptions(path: ApiConstants.walletReferral),
        statusCode: 200,
        data: {
          'data': {
            'uuid': 'w-1',
            'cash_balance': 0,
            'points_balance': 0,
            'referral_code': 'KLV5SA3H',
            'referred_by': true,
            'settings': {'wallet_points_enabled': true},
          },
        },
      ),
    );

    final repository = WalletRepositoryImpl(WalletRemoteDataSource(dio));
    final result = await repository.attachReferral('AMI12345');

    final captured = verify(
      () => dio.post<dynamic>(
        ApiConstants.walletReferral,
        data: captureAny(named: 'data'),
      ),
    ).captured.single;

    expect(Map<String, dynamic>.from(captured as Map), {
      'referral_code': 'AMI12345',
    });

    // La réponse porte le Wallet à jour : c'est lui qui dit que le
    // parrain est rattaché, pas une supposition de l'app.
    expect(result.getOrElse(() => _account()).hasReferrer, isTrue);
  });

  Future<_StubWallet> pump(WidgetTester tester, _StubWallet repository) async {
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
        home: WalletBonusesPage(cubit: WalletBonusCubit(repository)),
      ),
    );
    await tester.pumpAndSettle();

    return repository;
  }

  group('l’écran des bonus', () {
    testWidgets('envoie le code en majuscules', (tester) async {
      // Le serveur compare des majuscules : « klv5sa3h » lui serait
      // inconnu alors que le code existe.
      final repository = await pump(tester, _StubWallet());

      await tester.enterText(find.byType(TextField), 'ami12345');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Rattacher'));
      await tester.pumpAndSettle();

      expect(repository.codes, ['AMI12345']);

      // Le rattachement réussi lève un toast, qui s'efface sur minuterie :
      // la laisser courir évite de clore le test sur un timer en vol.
      await tester.pumpAndSettle(const Duration(seconds: 3));
    });

    testWidgets('remplace le champ par la confirmation du serveur', (
      tester,
    ) async {
      await pump(tester, _StubWallet());

      await tester.enterText(find.byType(TextField), 'AMI12345');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Rattacher'));
      await tester.pumpAndSettle();

      expect(find.byType(TextField), findsNothing);
      expect(
        find.text('Un parrain est déjà rattaché à votre compte.'),
        findsOneWidget,
      );

      await tester.pumpAndSettle(const Duration(seconds: 3));
    });

    testWidgets('ne propose rien quand un parrain est déjà rattaché', (
      tester,
    ) async {
      final repository = _StubWallet()..account = _account(hasReferrer: true);
      await pump(tester, repository);

      expect(find.byType(TextField), findsNothing);
      expect(repository.codes, isEmpty);
    });

    testWidgets('montre la raison du refus, telle que le serveur l’écrit', (
      tester,
    ) async {
      // « Ce code de parrainage est inconnu. », « Le code doit être
      // saisi avant la première utilisation. » : ces phrases sont
      // écrites pour le voyageur, et l'app n'a rien de mieux à dire.
      await pump(
        tester,
        _StubWallet(
          refusal: const ValidationFailure(
            'Ce code de parrainage est inconnu.',
          ),
        ),
      );

      await tester.enterText(find.byType(TextField), 'ZZZ');
      await tester.pumpAndSettle();
      await tester.tap(find.text('Rattacher'));
      await tester.pumpAndSettle();

      expect(find.text('Ce code de parrainage est inconnu.'), findsOneWidget);
      expect(find.byType(TextField), findsOneWidget);
    });

    testWidgets('ne part pas sur un champ vide', (tester) async {
      final repository = await pump(tester, _StubWallet());

      await tester.tap(find.text('Rattacher'));
      await tester.pumpAndSettle();

      expect(repository.codes, isEmpty);
    });
  });
}
