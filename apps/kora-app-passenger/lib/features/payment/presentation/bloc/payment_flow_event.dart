part of 'payment_flow_bloc.dart';

sealed class PaymentFlowEvent extends Equatable {
  const PaymentFlowEvent();

  @override
  List<Object?> get props => [];
}

/// Le voyageur lance le paiement.
///
/// Deux sources possibles, et une seule à la fois : une réservation à
/// régler ([reservationId]), ou une recharge du Wallet
/// ([topUpAmount]). Le reste du parcours est identique — même attente,
/// même relecture, mêmes écrans — parce que c'est le même paiement
/// provider ; seule la façon de l'ouvrir diffère.
class PaymentFlowStarted extends PaymentFlowEvent {
  const PaymentFlowStarted({
    required this.provider,
    this.reservationId,
    this.topUpAmount,
    this.method = PaymentCatalog.mobileMoneyCode,
    this.network,
    this.phone,
    this.walletAmount,
    this.pointsAmount,
  }) : assert(
         (reservationId == null) != (topUpAmount == null),
         'Un paiement règle une réservation ou alimente le Wallet, '
         'jamais les deux ni aucun des deux.',
       );

  /// La réservation à régler. `null` pour une recharge.
  final String? reservationId;

  /// Le montant d'une recharge du Wallet. `null` pour un billet.
  final int? topUpAmount;

  /// L'encaisseur (`fedapay`, `kkiapay`), déduit du réseau par le
  /// catalogue — ou imposé par le moyen, pour la carte.
  final String provider;

  /// `mobile_money` ou `card`.
  final String method;

  /// Le réseau du voyageur (`mtn_momo`, `moov_flooz`…).
  ///
  /// `null` quand l'encaisseur le recueille lui-même, dans son widget,
  /// et pour une carte qui n'en a pas.
  final String? network;

  /// Numéro à débiter, indicatif compris. `null` pour les mêmes raisons
  /// que [network].
  final String? phone;

  /// La part du billet réglée sur le solde Wallet, en francs.
  final int? walletAmount;

  /// La part réglée en points de fidélité, en points.
  ///
  /// Le serveur la retranche du total en francs : c'est sa règle, et
  /// elle ne vaut que tant qu'un point vaut un franc.
  final int? pointsAmount;

  @override
  List<Object?> get props => [
    reservationId,
    topUpAmount,
    provider,
    method,
    network,
    phone,
    walletAmount,
    pointsAmount,
  ];
}

/// Un tour de relecture, émis par la minuterie interne.
class PaymentFlowPolled extends PaymentFlowEvent {
  const PaymentFlowPolled();
}

/// Le voyageur affirme avoir payé : on demande au serveur d'interroger le
/// provider sans attendre le prochain tour.
class PaymentFlowVerifyRequested extends PaymentFlowEvent {
  const PaymentFlowVerifyRequested({this.transactionId});

  /// Celui que le widget KKiaPay vient de rendre, quand c'est lui qui a
  /// encaissé. `null` pour un provider qui encaisse côté serveur : il
  /// n'y a alors rien à joindre, le serveur sait déjà quoi interroger.
  final String? transactionId;

  @override
  List<Object?> get props => [transactionId];
}

/// Reprend le paiement laissé en cours au lancement précédent.
///
/// Émis à l'entrée du tunnel : le Bloc du paiement y naît et y meurt,
/// donc une app fermée pendant l'attente perd tout ce qu'il savait. Le
/// serveur, lui, a pu confirmer entre-temps.
class PaymentFlowResumed extends PaymentFlowEvent {
  const PaymentFlowResumed();
}

class PaymentFlowReset extends PaymentFlowEvent {
  const PaymentFlowReset();
}
