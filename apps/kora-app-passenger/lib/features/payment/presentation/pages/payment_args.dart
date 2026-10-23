import '../../../booking/domain/entities/departure.dart';
import '../../../booking/domain/entities/price.dart';

/// Ce que les écrans de paiement ont besoin de savoir.
///
/// Le montant est calculé une fois, au récapitulatif, puis transporté : le
/// recalculer sur chaque écran ferait dépendre le total du chemin
/// emprunté, et deux écrans du même parcours pourraient annoncer deux
/// sommes différentes.
class PaymentArgs {
  const PaymentArgs({
    required this.total,
    this.provider,
    this.phone,
    this.departure,
    this.seatLabel,
  });

  /// Le montant dû pour l'ensemble des voyageurs.
  final Price total;

  /// L'encaisseur choisi sur l'écran des moyens (`fedapay`, `kkiapay`).
  ///
  /// Deux passerelles servent le Mobile Money, et elles n'encaissent pas
  /// de la même façon : FedaPay pousse la demande sur le téléphone,
  /// KKiaPay ouvre sa feuille dans l'app. Le choix est fait sur la carte,
  /// pas déduit du réseau — sans quoi l'écran suivant ne saurait pas
  /// laquelle le voyageur a touchée.
  final String? provider;

  /// Le numéro du compte, proposé par défaut au débit Mobile Money.
  final String? phone;

  /// De quoi récapituler le voyage une fois le paiement confirmé. Porté
  /// jusqu'au bout plutôt que relu : à ce stade la réservation est faite,
  /// et rappeler l'API pour réafficher ce qu'on sait déjà serait gratuit.
  final Departure? departure;
  final String? seatLabel;
}
