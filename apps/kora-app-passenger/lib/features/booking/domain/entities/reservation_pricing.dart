import 'package:equatable/equatable.dart';

import 'group_quote.dart';
import 'price.dart';

/// Couche DOMAIN : le prix d'une réservation **déjà retenue**, tel que le
/// serveur le recalcule quand un code promo est appliqué ou retiré
/// (`PUT bookings/{uuid}/promo-code`).
///
/// Ni devis ni blocage : les sièges, les voyageurs et l'échéance ne
/// bougent pas — seul le montant change, et c'est le serveur qui le dit.
class ReservationPricing extends Equatable {
  const ReservationPricing({
    required this.total,
    required this.subtotal,
    this.promotion,
    this.expiresAt,
  });

  /// Ce qu'il y a réellement à payer, remise déduite.
  final Price total;

  /// Le montant avant remise.
  final Price subtotal;

  /// La promotion retenue, `null` quand le code vient d'être retiré.
  final QuotePromotion? promotion;

  /// L'échéance du blocage, inchangée par l'opération — le serveur la
  /// redonne, on la reprend plutôt que de garder une date devinée.
  final DateTime? expiresAt;

  @override
  List<Object?> get props => [total, subtotal, promotion, expiresAt];
}
