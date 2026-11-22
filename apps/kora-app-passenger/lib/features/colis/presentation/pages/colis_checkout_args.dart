import '../../data/datasources/colis_network_mock.dart';

/// Ce qu'un écran d'encaissement du colis a besoin de savoir.
///
/// Le montant est arrêté sur l'écran des moyens — solde Wallet déduit —
/// puis transporté, comme pour un billet : le recalculer à chaque écran
/// ferait dépendre la somme du chemin emprunté, et deux écrans du même
/// parcours pourraient en annoncer deux.
class ColisCheckoutArgs {
  const ColisCheckoutArgs({
    required this.gatewayId,
    required this.due,
    this.walletApplied = 0,
    this.network,
    this.payerLabel,
    this.payerPhone,
    this.startedAt,
  });

  /// L'encaisseur choisi : `fedapay`, `kkiapay` ou `card`.
  final String gatewayId;

  /// Le reste à payer, en francs, une fois le solde KORA déduit.
  final int due;

  /// Ce que le solde KORA a déjà couvert — rappelé sur le reçu.
  final int walletApplied;

  /// Le réseau Mobile Money choisi (`mtn_momo`), quand c'est l'app qui
  /// le demande. KKiaPay le recueille lui-même : il reste nul.
  final String? network;

  /// Qui encaisse, tel qu'on l'annonce pendant l'attente (« MTN MoMo »,
  /// « KKiaPay »).
  final String? payerLabel;

  /// Le numéro débité, quand l'app l'a demandé. Rappelé pendant
  /// l'attente : c'est sur ce téléphone que l'invite est partie, et le
  /// payeur doit pouvoir vérifier qu'il regarde le bon.
  final String? payerPhone;

  /// L'heure où la demande est partie. L'attente ne se juge pas sans
  /// elle : « il y a deux minutes » et « il y a un quart d'heure »
  /// n'appellent pas le même geste.
  final DateTime? startedAt;

  ColisGateway get gateway => colisGatewayOf(gatewayId);

  /// Le même règlement, une fois le payeur connu.
  ColisCheckoutArgs withPayer({
    String? network,
    String? payerLabel,
    String? payerPhone,
    DateTime? startedAt,
  }) => ColisCheckoutArgs(
    gatewayId: gatewayId,
    due: due,
    walletApplied: walletApplied,
    network: network ?? this.network,
    payerLabel: payerLabel ?? this.payerLabel,
    payerPhone: payerPhone ?? this.payerPhone,
    startedAt: startedAt ?? this.startedAt,
  );
}
