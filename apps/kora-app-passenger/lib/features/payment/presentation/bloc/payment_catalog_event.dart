part of 'payment_catalog_bloc.dart';

/// Couche PRESENTATION : ce qui peut arriver au catalogue de paiement.
sealed class PaymentCatalogEvent extends Equatable {
  const PaymentCatalogEvent();

  @override
  List<Object?> get props => [];
}

/// Chargement du catalogue. Sert aussi de « Réessayer » : c'est la même
/// action, et l'écran d'erreur n'a rien de plus à retenir.
class PaymentCatalogRequested extends PaymentCatalogEvent {
  const PaymentCatalogRequested({this.force = false});

  /// Recharge même si le catalogue est déjà en mémoire.
  ///
  /// Faux à l'ouverture d'un écran — inutile de rappeler le serveur —,
  /// vrai depuis un bouton « Réessayer », où l'intention est justement de
  /// refaire l'appel.
  final bool force;

  @override
  List<Object?> get props => [force];
}
