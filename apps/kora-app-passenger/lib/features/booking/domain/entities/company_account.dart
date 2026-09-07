import 'package:equatable/equatable.dart';

import 'price.dart';

/// Couche DOMAIN : un compte entreprise dont le voyageur est membre actif.
///
/// `GET /company-accounts` ne rend au passager que **ses** comptes : sans
/// la permission `company-credit.view`, le serveur filtre sur son
/// appartenance, et masque les plafonds. D'où [monthlyLimit] et
/// [availableCredit] nullables — leur absence n'est pas une erreur, c'est
/// la règle pour un voyageur ordinaire.
class CompanyAccount extends Equatable {
  const CompanyAccount({
    required this.id,
    required this.name,
    this.reference,
    this.monthlyLimit,
    this.availableCredit,
  });

  final String id;
  final String name;

  /// Référence interne de l'entreprise (`STB-2026-001`).
  final String? reference;

  /// `null` pour qui n'a pas le droit de voir les plafonds.
  final Price? monthlyLimit;
  final Price? availableCredit;

  /// Vrai quand le serveur a bien voulu montrer le crédit restant.
  bool get showsCredit => availableCredit != null;

  @override
  List<Object?> get props => [
    id,
    name,
    reference,
    monthlyLimit,
    availableCredit,
  ];
}
