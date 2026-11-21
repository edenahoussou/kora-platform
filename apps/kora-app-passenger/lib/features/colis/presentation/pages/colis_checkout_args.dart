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

  ColisGateway get gateway => colisGatewayOf(gatewayId);

  /// Le même règlement, une fois le payeur connu.
  ColisCheckoutArgs withPayer({String? network, String? payerLabel}) =>
      ColisCheckoutArgs(
        gatewayId: gatewayId,
        due: due,
        walletApplied: walletApplied,
        network: network ?? this.network,
        payerLabel: payerLabel ?? this.payerLabel,
      );
}
