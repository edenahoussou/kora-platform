part of 'payment_flow_bloc.dart';

sealed class PaymentFlowEvent extends Equatable {
  const PaymentFlowEvent();

  @override
  List<Object?> get props => [];
}

/// Le voyageur lance le paiement.
class PaymentFlowStarted extends PaymentFlowEvent {
  const PaymentFlowStarted({
    required this.reservationId,
    required this.provider,
    this.method = PaymentCatalog.mobileMoneyCode,
    this.network,
    this.phone,
  });

  final String reservationId;

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

  @override
  List<Object?> get props => [reservationId, provider, method, network, phone];
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
