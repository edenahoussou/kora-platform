// Deux recharges identiques ne sont pas la même demande.
//
// La clé d'idempotence ne décrivait que le contenu : montant,
// passerelle, répartition. Recharger 5 000 F par KKiaPay une seconde
// fois — le lendemain, ou après une première recharge confirmée —
// rendait donc la **même** clé. Le serveur, fidèle à son rôle, rejouait
// le paiement déjà réglé : l'app affichait « Recharge confirmée » sans
// que la feuille de paiement s'ouvre, et le solde ne bougeait pas d'un
// franc.
//
// Vu sur le téléphone le 12 novembre 2026 : solde à 28 300 F avant et
// après, et aucune ligne nouvelle au relevé.

import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_attempt.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_flow_bloc.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_account.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_movement.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_top_up.dart';
import 'package:kora_app_passenger/features/wallet/domain/repositories/wallet_repository.dart';

import '../../../../support/seat_hold_harness.dart';

/// Le Wallet réduit à ce que la recharge lui demande.
class _Wallet implements WalletRepository {
  /// Les clés d'idempotence reçues, dans l'ordre des demandes.
  final List<String> keys = [];

  @override
  Future<Either<Failure, WalletTopUp>> initiateTopUp({
    required int amount,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? phone,
    String? network,
  }) async {
    keys.add(idempotencyKey);

    return Right(
      WalletTopUp(
        id: 'top-${keys.length}',
        amount: Price(amount),
        status: WalletTopUpStatus.pending,
        attempt: const PaymentAttempt(
          id: 'pay-1',
          status: PaymentAttemptStatus.pending,
          nextAction: 'wait',
        ),
      ),
    );
  }

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

/// Une recharge de 5 000 F par KKiaPay.
const _topUp = PaymentFlowStarted(topUpAmount: 5000, provider: 'kkiapay');

/// Le paiement que le serveur rend une fois la recharge réglée.
const _confirmed = PaymentAttempt(
  id: 'pay-1',
  status: PaymentAttemptStatus.confirmed,
  nextAction: 'done',
);

void main() {
  test(
    'une recharge identique au lancement suivant est une demande neuve',
    () async {
      // Deux blocs : l'app fermée puis rouverte. Même montant, même
      // passerelle — et pourtant une recharge que le serveur ne doit pas
      // confondre avec celle d'hier.
      final keys = <String>[];

      for (var launch = 0; launch < 2; launch++) {
        final wallet = _Wallet();
        final bloc = PaymentFlowBloc(
          StubPaymentRepository(attempt: _confirmed),
          null,
          wallet,
        );

        bloc.add(_topUp);
        await Future<void>.delayed(Duration.zero);
        await bloc.close();

        keys.addAll(wallet.keys);
      }

      expect(keys, hasLength(2));
      expect(keys.first, isNot(equals(keys.last)));
    },
  );

  test('recharger deux fois le même montant reste possible', () async {
    // Après une recharge confirmée, le verrou qui protège un billet
    // d'un double paiement bloquait la recharge suivante du même
    // montant : « Payer » n'envoyait plus rien.
    final wallet = _Wallet();
    final bloc = PaymentFlowBloc(
      StubPaymentRepository(attempt: _confirmed),
      null,
      wallet,
    );
    addTearDown(bloc.close);

    bloc.add(_topUp);
    await Future<void>.delayed(Duration.zero);
    expect(bloc.state.attempt?.isConfirmed, isTrue);

    bloc.add(_topUp);
    await Future<void>.delayed(Duration.zero);

    expect(wallet.keys, hasLength(2));
    expect(wallet.keys.first, isNot(equals(wallet.keys.last)));
  });
}
