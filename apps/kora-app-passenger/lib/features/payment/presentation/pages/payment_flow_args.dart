import '../../../booking/domain/entities/departure.dart';
import '../../../booking/domain/entities/price.dart';

/// Ce que porte un paiement en cours, du moment où il est lancé jusqu'à sa
/// confirmation.
///
/// Les champs facultatifs sont ceux que rendra `POST payments/initiate` :
/// [reference] (`provider_reference`), [expiresAt] et [pollingInterval].
/// Tant que cet appel n'est pas branché, ils restent nuls et les écrans
/// taisent ce qu'ils ne savent pas — un délai inventé serait pire qu'un
/// délai absent, puisque c'est justement ce délai qui dit au voyageur
/// combien de temps il peut attendre avant de s'inquiéter.
class PaymentFlowArgs {
  const PaymentFlowArgs({
    required this.total,
    required this.providerLabel,
    this.reference,
    this.expiresAt,
    this.pollingInterval,
    this.departure,
    this.seatLabel,
  });

  final Price total;

  /// Le nom lisible de l'opérateur (`MTN MoMo`) — l'écran de validation en
  /// fait son surtitre.
  final String providerLabel;

  /// `provider_reference` : la référence à citer au support.
  final String? reference;

  /// L'échéance de la **demande de paiement**, distincte de celle du
  /// blocage des sièges : la fenêtre de l'opérateur se ferme bien avant.
  final DateTime? expiresAt;

  /// `polling_interval_seconds` : à quelle cadence interroger le serveur.
  final Duration? pollingInterval;

  /// De quoi récapituler le voyage sur l'écran de confirmation.
  final Departure? departure;
  final String? seatLabel;
}
