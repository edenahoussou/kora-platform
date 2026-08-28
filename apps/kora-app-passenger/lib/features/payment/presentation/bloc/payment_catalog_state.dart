part of 'payment_catalog_bloc.dart';

/// Les états du chargement du catalogue. Pas d'état « vide » : un serveur
/// sans moyen actif est un succès dont le contenu est vide, et c'est
/// [PaymentCatalogState.hasMobileMoney] qui permet à l'écran de le dire.
enum PaymentCatalogStatus { initial, loading, success, failure }

/// Couche PRESENTATION : le catalogue et son état de chargement.
class PaymentCatalogState extends Equatable {
  const PaymentCatalogState({
    this.status = PaymentCatalogStatus.initial,
    this.catalog = PaymentCatalog.empty,
    this.errorMessage,
  });

  final PaymentCatalogStatus status;
  final PaymentCatalog catalog;

  /// Renseigné uniquement quand [status] vaut [PaymentCatalogStatus.failure].
  final String? errorMessage;

  bool get isLoading =>
      status == PaymentCatalogStatus.initial ||
      status == PaymentCatalogStatus.loading;

  bool get hasFailed => status == PaymentCatalogStatus.failure;

  /// Vrai quand le serveur accepte au moins un paiement Mobile Money.
  bool get hasMobileMoney => catalog.mobileMoneyNetworks.isNotEmpty;

  /// Les réseaux à proposer dans le sélecteur d'opérateur.
  List<String> get mobileMoneyNetworks => catalog.mobileMoneyNetworks;

  PaymentCatalogState copyWith({
    PaymentCatalogStatus? status,
    PaymentCatalog? catalog,
    String? errorMessage,
    bool clearError = false,
  }) {
    return PaymentCatalogState(
      status: status ?? this.status,
      catalog: catalog ?? this.catalog,
      // `copyWith` ne sait pas remettre un champ à `null` : un rechargement
      // qui réussit doit pouvoir effacer l'erreur précédente, sinon
      // l'écran garde un message qui ne vaut plus rien.
      errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
    );
  }

  @override
  List<Object?> get props => [status, catalog, errorMessage];
}
