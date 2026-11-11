import '../../../../core/network/api_json.dart';
import '../../../booking/domain/entities/price.dart';
import '../../domain/entities/wallet_account.dart';
import '../../domain/entities/wallet_movement.dart';
import '../../domain/entities/wallet_top_up.dart';

/// Couche DATA : le Wallet tel que `GET /api/v1/wallet` le rend.
///
/// ```json
/// {
///   "uuid": "…",
///   "cash_balance": 18750,
///   "points_balance": 2480,
///   "points_expires_at": "2027-04-30T23:59:59+00:00",
///   "points_compensation_due": 0,
///   "referral_code": "KORA-4F2A",
///   "referred_by": false,
///   "settings": { "point_value_fcfa": 1, "points_min_spend": 100, … }
/// }
/// ```
///
/// Tout ce que l'écran affiche vient de là. Rien n'est recalculé ni
/// complété par défaut : un solde faux est pire qu'un écran vide.
abstract final class WalletAccountModel {
  static WalletAccount fromJson(Map<String, dynamic> json) {
    return WalletAccount(
      id: ApiJson.string(json['uuid']),
      cash: Price(ApiJson.intOrNull(json['cash_balance']) ?? 0),
      points: ApiJson.intOrNull(json['points_balance']) ?? 0,
      pointsExpireAt: _date(json['points_expires_at']),
      compensationDue: ApiJson.intOrNull(json['points_compensation_due']) ?? 0,
      referralCode: ApiJson.stringOrNull(json['referral_code']),
      hasReferrer: json['referred_by'] == true,
      rules: _rules(ApiJson.map(json['settings'])),
    );
  }

  static WalletRules _rules(Map<String, dynamic> json) {
    return WalletRules(
      cashEnabled: json['wallet_cash_enabled'] == true,
      pointsEnabled: json['wallet_points_enabled'] == true,
      // Un point vaut un franc par défaut (voir MEZ-49) ; zéro ferait
      // afficher une contre-valeur nulle sur un solde qui en a une.
      pointValue: ApiJson.intOrNull(json['point_value_fcfa']) ?? 1,
      pointsMinSpend: ApiJson.intOrNull(json['points_min_spend']) ?? 0,
      pointsExpiryMonths: ApiJson.intOrNull(json['points_expiry_months']) ?? 0,
      topupMin: ApiJson.intOrNull(json['topup_min_amount']),
      topupMax: ApiJson.intOrNull(json['topup_max_amount']),
      cashBalanceCap: ApiJson.intOrNull(json['cash_balance_cap']),
      topupPresets: _ints(json['topup_amount_presets']),
      // Absent de la réponse aujourd'hui : l'app n'affiche alors aucune
      // alerte d'expiration plutôt que d'en inventer le seuil.
      expiryReminderDays: _ints(json['points_expiry_reminder_days']),
    );
  }

  static List<int> _ints(Object? raw) {
    if (raw is! List) return const [];

    return [for (final item in raw) ?ApiJson.intOrNull(item)];
  }

  static DateTime? _date(Object? raw) {
    final value = ApiJson.stringOrNull(raw);
    return value == null ? null : DateTime.tryParse(value)?.toLocal();
  }
}

/// Couche DATA : une écriture de `GET /api/v1/wallet/transactions`.
///
/// ```json
/// {
///   "uuid": "…",
///   "balance_type": "points",
///   "direction": "credit",
///   "amount": 375,
///   "reason": "points_earn",
///   "reference": { "type": "ticket", "uuid": "…" },
///   "created_at": "2026-10-28T09:12:00+00:00"
/// }
/// ```
abstract final class WalletMovementModel {
  static WalletMovement fromJson(Map<String, dynamic> json) {
    final reference = ApiJson.map(json['reference']);

    return WalletMovement(
      id: ApiJson.string(json['uuid']),
      kind: WalletBalanceKind.of(ApiJson.string(json['balance_type'])),
      direction: WalletDirection.of(ApiJson.string(json['direction'])),
      // Le serveur rend un montant positif et porte le sens dans
      // `direction` ; une valeur négative qui passerait quand même ne
      // doit pas s'afficher avec deux signes.
      amount: (ApiJson.intOrNull(json['amount']) ?? 0).abs(),
      reason: WalletReason.of(ApiJson.string(json['reason'])),
      createdAt:
          DateTime.tryParse(ApiJson.string(json['created_at']))?.toLocal() ??
          DateTime.now(),
      referenceType: ApiJson.stringOrNull(reference['type']),
      referenceId: ApiJson.stringOrNull(reference['uuid']),
    );
  }
}

/// Couche DATA : une recharge, telle que `POST /api/v1/wallet/topups` et
/// `GET /api/v1/wallet/topups/{uuid}` la rendent.
///
/// ```json
/// {
///   "uuid": "…",
///   "amount": 5000,
///   "status": "pending",
///   "payment": {
///     "uuid": "…", "status": "pending",
///     "provider": "kkiapay", "provider_reference": "…"
///   },
///   "created_at": "2026-11-08T00:12:00+00:00"
/// }
/// ```
///
/// Le bloc `payment` est **descriptif** : il n'a ni `next_action`, ni
/// `widget`, ni `polling_interval_seconds`, que `PaymentResource` rend
/// pour l'achat d'un billet. On garde donc son identifiant pour aller
/// relire le paiement là où ces champs existent.
abstract final class WalletTopUpModel {
  static WalletTopUp fromJson(Map<String, dynamic> json) {
    final payment = ApiJson.map(json['payment']);

    return WalletTopUp(
      id: ApiJson.string(json['uuid']),
      amount: Price(ApiJson.intOrNull(json['amount']) ?? 0),
      status: WalletTopUpStatus.of(ApiJson.string(json['status'])),
      paymentId: ApiJson.stringOrNull(payment['uuid']),
      paymentStatus: ApiJson.stringOrNull(payment['status']),
      provider: ApiJson.stringOrNull(payment['provider']),
      createdAt: _date(json['created_at']),
    );
  }

  static DateTime? _date(Object? raw) {
    final value = ApiJson.stringOrNull(raw);
    return value == null ? null : DateTime.tryParse(value)?.toLocal();
  }
}
