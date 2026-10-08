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
    required this.network,
    required this.phone,
  });

  final String reservationId;

  /// L'encaisseur (`fedapay`), déduit du réseau par le catalogue.
  final String provider;

  /// Le réseau du voyageur (`mtn_momo`, `moov_flooz`, `celtiis_cash`).
  final String network;

  /// Numéro à débiter, indicatif compris.
  final String phone;

  @override
  List<Object?> get props => [reservationId, provider, network, phone];
}

/// Un tour de relecture, émis par la minuterie interne.
class PaymentFlowPolled extends PaymentFlowEvent {
  const PaymentFlowPolled();
}

/// Le voyageur affirme avoir payé : on demande au serveur d'interroger le
/// provider sans attendre le prochain tour.
class PaymentFlowVerifyRequested extends PaymentFlowEvent {
  const PaymentFlowVerifyRequested();
}

/// Repart de zéro — un nouvel essai après échec.
class PaymentFlowReset extends PaymentFlowEvent {
  const PaymentFlowReset();
}
