import 'package:equatable/equatable.dart';

/// Lequel des deux soldes le mouvement touche.
enum WalletBalanceKind {
  cash,
  points;

  static WalletBalanceKind of(String raw) =>
      raw == 'points' ? WalletBalanceKind.points : WalletBalanceKind.cash;
}

/// Dans quel sens.
enum WalletDirection {
  credit,
  debit;

  static WalletDirection of(String raw) =>
      raw == 'debit' ? WalletDirection.debit : WalletDirection.credit;
}

/// Pourquoi le solde a bougé.
///
/// Les quatorze motifs du serveur, plus [unknown] : un motif ajouté côté
/// back après cette version doit s'afficher sobrement, pas faire tomber
/// l'historique.
enum WalletReason {
  topUp,
  purchase,
  refundCash,
  refundPoints,
  pointsEarn,
  bonusWelcome,
  bonusReferral,
  bonusAnniversary,
  bonusUsage,
  pointsExpire,
  pointsCorrection,
  compensationOffset,
  restitution,
  adjustment,
  unknown;

  static WalletReason of(String raw) => switch (raw) {
    'topup' => WalletReason.topUp,
    'purchase' => WalletReason.purchase,
    'refund_cash' => WalletReason.refundCash,
    'refund_points' => WalletReason.refundPoints,
    'points_earn' => WalletReason.pointsEarn,
    'bonus_welcome' => WalletReason.bonusWelcome,
    'bonus_referral' => WalletReason.bonusReferral,
    'bonus_anniversary' => WalletReason.bonusAnniversary,
    'bonus_usage' => WalletReason.bonusUsage,
    'points_expire' => WalletReason.pointsExpire,
    'points_correction' => WalletReason.pointsCorrection,
    'compensation_offset' => WalletReason.compensationOffset,
    'restitution' => WalletReason.restitution,
    'adjustment' => WalletReason.adjustment,
    _ => WalletReason.unknown,
  };
}

/// Couche DOMAIN : une écriture du Wallet.
///
/// Les mouvements cash et points vivent dans la **même** liste, dans
/// l'ordre où ils sont arrivés : c'est le relevé du compte, et le
/// voyageur n'a pas à recoller deux historiques pour comprendre ce qui
/// s'est passé après un paiement mixte.
class WalletMovement extends Equatable {
  const WalletMovement({
    required this.id,
    required this.kind,
    required this.direction,
    required this.amount,
    required this.reason,
    required this.createdAt,
    this.referenceType,
    this.referenceId,
  });

  final String id;
  final WalletBalanceKind kind;
  final WalletDirection direction;

  /// Toujours positif : c'est [direction] qui porte le sens.
  final int amount;

  final WalletReason reason;
  final DateTime createdAt;

  /// Ce à quoi le mouvement se rattache — un billet, un paiement — quand
  /// le serveur le dit.
  final String? referenceType;
  final String? referenceId;

  bool get isCredit => direction == WalletDirection.credit;

  @override
  List<Object?> get props => [
    id,
    kind,
    direction,
    amount,
    reason,
    createdAt,
    referenceType,
    referenceId,
  ];
}
