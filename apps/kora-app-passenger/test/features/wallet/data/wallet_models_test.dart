// La lecture de `GET /api/v1/wallet` et de son relevé.
//
// Rien de ce que l'écran affiche n'est calculé par l'app : soldes,
// échéance, règles et motifs viennent tous de là. Ces tests tiennent le
// contrat (MEZ-160) et le comportement quand un champ manque.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/wallet/data/models/wallet_models.dart';
import 'package:kora_app_passenger/features/wallet/domain/entities/wallet_movement.dart';

void main() {
  group('le Wallet', () {
    test('lit les deux soldes, l’échéance et le code parrain', () {
      final account = WalletAccountModel.fromJson({
        'uuid': 'w-1',
        'cash_balance': 18750,
        'points_balance': 2480,
        'points_expires_at': '2027-04-30T23:59:59+00:00',
        'referral_code': 'KORA-4F2A',
        'referred_by': true,
        'settings': {
          'wallet_cash_enabled': true,
          'wallet_points_enabled': true,
          'point_value_fcfa': 1,
          'points_min_spend': 100,
          'points_expiry_months': 6,
          'topup_min_amount': 500,
          'topup_max_amount': 200000,
          'cash_balance_cap': 500000,
          'topup_amount_presets': [2000, 5000, 10000, 20000],
        },
      });

      expect(account.id, 'w-1');
      expect(account.cash, const Price(18750));
      expect(account.points, 2480);
      expect(account.pointsExpireAt?.year, 2027);
      expect(account.referralCode, 'KORA-4F2A');
      expect(account.hasReferrer, isTrue);

      expect(account.rules.pointsMinSpend, 100);
      expect(account.rules.topupMin, 500);
      expect(account.rules.topupMax, 200000);
      expect(account.rules.cashBalanceCap, 500000);
      expect(account.rules.topupPresets, [2000, 5000, 10000, 20000]);
    });

    test('ne complète rien quand la réponse est vide', () {
      // Mieux vaut un écran qui annonce zéro que des montants inventés :
      // tout ce qui manque reste neutre.
      final account = WalletAccountModel.fromJson({});

      expect(account.cash, const Price(0));
      expect(account.points, 0);
      expect(account.pointsExpireAt, isNull);
      expect(account.referralCode, isNull);
      expect(account.hasReferrer, isFalse);
      expect(account.rules.cashEnabled, isFalse);
      expect(account.rules.pointsEnabled, isFalse);
      expect(account.rules.topupPresets, isEmpty);
      // Sans seuil annoncé, aucune alerte d'expiration n'est possible.
      expect(account.rules.expiryReminderDays, isEmpty);
    });

    test('garde un point à un franc quand le taux manque', () {
      // Zéro ferait afficher une contre-valeur nulle sur un solde qui en
      // a une ; un franc est la valeur du contrat MEZ-49.
      final account = WalletAccountModel.fromJson({
        'points_balance': 300,
        'settings': {'wallet_points_enabled': true},
      });

      expect(account.rules.pointValue, 1);
      expect(account.pointsValue, const Price(300));
    });

    test('lit les seuils d’alerte le jour où le serveur les expose', () {
      final account = WalletAccountModel.fromJson({
        'settings': {
          'points_expiry_reminder_days': [30, 7, 1],
        },
      });

      expect(account.rules.expiryReminderDays, [30, 7, 1]);
    });
  });

  group('un mouvement', () {
    test('porte son solde, son sens et son motif', () {
      final movement = WalletMovementModel.fromJson({
        'uuid': 'm-1',
        'balance_type': 'points',
        'direction': 'credit',
        'amount': 375,
        'reason': 'points_earn',
        'reference': {'type': 'ticket', 'uuid': 't-9'},
        'created_at': '2026-10-28T09:12:00+00:00',
      });

      expect(movement.kind, WalletBalanceKind.points);
      expect(movement.direction, WalletDirection.credit);
      expect(movement.isCredit, isTrue);
      expect(movement.amount, 375);
      expect(movement.reason, WalletReason.pointsEarn);
      expect(movement.referenceType, 'ticket');
      expect(movement.referenceId, 't-9');
    });

    test('garde un montant positif, le sens vit à part', () {
      // Le serveur rend une valeur positive ; une négative qui passerait
      // quand même afficherait « − − 6 000 ».
      final movement = WalletMovementModel.fromJson({
        'balance_type': 'cash',
        'direction': 'debit',
        'amount': -6000,
        'reason': 'purchase',
      });

      expect(movement.amount, 6000);
      expect(movement.isCredit, isFalse);
    });

    test('reconnaît les quatorze motifs du serveur', () {
      const codes = {
        'topup': WalletReason.topUp,
        'purchase': WalletReason.purchase,
        'refund_cash': WalletReason.refundCash,
        'refund_points': WalletReason.refundPoints,
        'points_earn': WalletReason.pointsEarn,
        'bonus_welcome': WalletReason.bonusWelcome,
        'bonus_referral': WalletReason.bonusReferral,
        'bonus_anniversary': WalletReason.bonusAnniversary,
        'bonus_usage': WalletReason.bonusUsage,
        'points_expire': WalletReason.pointsExpire,
        'points_correction': WalletReason.pointsCorrection,
        'compensation_offset': WalletReason.compensationOffset,
        'restitution': WalletReason.restitution,
        'adjustment': WalletReason.adjustment,
      };

      codes.forEach((code, reason) {
        expect(WalletReason.of(code), reason, reason: code);
      });
    });

    test('un motif inconnu ne fait pas tomber le relevé', () {
      // Un motif ajouté côté serveur après cette version s'affiche
      // sobrement plutôt que de vider l'historique.
      expect(WalletReason.of('cashback_2027'), WalletReason.unknown);
    });
  });
}
