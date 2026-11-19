import 'package:equatable/equatable.dart';

import '../../../booking/domain/entities/price.dart';
import 'wallet_movement.dart';

/// Les règles du Wallet, arrêtées côté serveur.
///
/// Aucune n'est écrite dans l'app : le minimum de points utilisable, les
/// paliers de recharge ou la valeur d'un point se règlent dans
/// l'administration et changent sans republier l'application.
/// Ce que rapporte chaque bonus, tel que l'administration le règle.
///
/// L'écran Bonus ne montrait que ce qui avait déjà été crédité : le
/// barème n'était pas exposé, et l'écrire dans l'app aurait promis des
/// points qu'un changement de réglage aurait démentis. Le serveur le
/// rend maintenant.
class WalletBonusScale extends Equatable {
  const WalletBonusScale({
    this.welcomePoints = 0,
    this.referralPoints = 0,
    this.anniversaryPoints = 0,
    this.usagePoints = 0,
    this.usageThreshold = 0,
  });

  final int welcomePoints;
  final int referralPoints;
  final int anniversaryPoints;

  /// Ce que rapporte la [usageThreshold]-ième utilisation du mois.
  final int usagePoints;
  final int usageThreshold;

  /// Ce que rapporte [reason], ou `null` quand le serveur n'en dit rien.
  int? pointsFor(WalletReason reason) {
    final value = switch (reason) {
      WalletReason.bonusWelcome => welcomePoints,
      WalletReason.bonusReferral => referralPoints,
      WalletReason.bonusAnniversary => anniversaryPoints,
      WalletReason.bonusUsage => usagePoints,
      _ => 0,
    };

    return value > 0 ? value : null;
  }

  @override
  List<Object?> get props => [
    welcomePoints,
    referralPoints,
    anniversaryPoints,
    usagePoints,
    usageThreshold,
  ];
}

class WalletRules extends Equatable {
  const WalletRules({
    this.cashEnabled = false,
    this.pointsEnabled = false,
    this.pointValue = 1,
    this.pointsMinSpend = 0,
    this.pointsExpiryMonths = 0,
    this.topupMin,
    this.topupMax,
    this.cashBalanceCap,
    this.topupPresets = const [],
    this.expiryReminderDays = const [],
    this.bonuses = const WalletBonusScale(),
  });

  /// Les deux soldes s'activent séparément : la fidélité peut tourner
  /// sans que la recharge en espèces soit ouverte.
  final bool cashEnabled;
  final bool pointsEnabled;

  /// Ce que vaut un point en francs CFA à l'usage.
  final int pointValue;

  /// En dessous, les points ne sont pas utilisables sur un paiement.
  final int pointsMinSpend;

  final int pointsExpiryMonths;

  /// Bornes de la recharge, `null` quand le serveur n'en impose pas.
  final int? topupMin;
  final int? topupMax;
  final int? cashBalanceCap;

  /// Les montants proposés en un geste sur l'écran de recharge.
  final List<int> topupPresets;

  /// À combien de jours de l'expiration le serveur veut qu'on prévienne.
  ///
  /// Vide tant que le serveur ne l'expose pas : l'app n'invente alors
  /// aucun seuil et n'affiche pas d'alerte, plutôt que d'annoncer une
  /// échéance selon une règle qui ne serait pas la sienne.
  final List<int> expiryReminderDays;

  /// Ce que rapporte chaque bonus de fidélité.
  final WalletBonusScale bonuses;

  /// La contre-valeur en francs d'un nombre de points.
  Price valueOf(int points) => Price(points * pointValue);

  @override
  List<Object?> get props => [
    cashEnabled,
    pointsEnabled,
    pointValue,
    pointsMinSpend,
    pointsExpiryMonths,
    topupMin,
    topupMax,
    cashBalanceCap,
    topupPresets,
    expiryReminderDays,
    bonuses,
  ];
}

/// Couche DOMAIN : le Wallet d'un voyageur — deux soldes, une échéance
/// et un code de parrainage.
///
/// Les deux soldes ne se mélangent pas : le cash est de l'argent rechargé
/// ou remboursé, les points sont une fidélité qui ne se retire pas, ne se
/// transfère pas et expire après une période d'inactivité (voir
/// `docs/mez-49-compte-voyageur-wallet-fidelite.md`).
class WalletAccount extends Equatable {
  const WalletAccount({
    required this.id,
    required this.cash,
    required this.points,
    required this.rules,
    this.pointsExpireAt,
    this.referralCode,
    this.hasReferrer = false,
  });

  final String id;

  /// Solde en espèces, dans la même monnaie que les prix du tunnel.
  final Price cash;

  /// Solde de fidélité, en points.
  final int points;

  final WalletRules rules;

  /// Quand les points tombent, faute d'activité. `null` s'il n'y a pas
  /// d'échéance — un solde vide, ou une expiration désactivée.
  final DateTime? pointsExpireAt;

  /// Le code à partager pour parrainer. `null` tant que le serveur n'en
  /// a pas attribué.
  final String? referralCode;

  /// Vrai quand le voyageur a déjà été rattaché à un parrain : le champ
  /// de saisie n'a alors plus lieu d'être.
  final bool hasReferrer;

  /// La contre-valeur du solde de points, en francs.
  Price get pointsValue => rules.valueOf(points);

  /// Vrai quand le Wallet n'est ouvert sur rien : ni espèces, ni
  /// fidélité. L'écran le dit plutôt que d'afficher deux zéros.
  bool get isDormant => !rules.cashEnabled && !rules.pointsEnabled;

  /// Le nombre de jours avant l'expiration des points, ou `null` s'il
  /// n'y a rien à compter.
  int? daysBeforeExpiry(DateTime now) {
    final expiry = pointsExpireAt;
    if (expiry == null || points <= 0) return null;

    // On compare des jours, pas des instants : à quelques heures près,
    // « dans 7 jours » ne doit pas devenir « dans 6 ».
    final from = DateTime(now.year, now.month, now.day);
    final to = DateTime(expiry.year, expiry.month, expiry.day);

    return to.difference(from).inDays;
  }

  /// Vrai quand l'échéance mérite une alerte, selon les seuils du
  /// serveur et eux seuls.
  bool needsExpiryWarning(DateTime now) {
    final days = daysBeforeExpiry(now);
    if (days == null || rules.expiryReminderDays.isEmpty) return false;

    final threshold = rules.expiryReminderDays.reduce((a, b) => a > b ? a : b);

    return days <= threshold;
  }

  @override
  List<Object?> get props => [
    id,
    cash,
    points,
    rules,
    pointsExpireAt,
    referralCode,
    hasReferrer,
  ];
}
