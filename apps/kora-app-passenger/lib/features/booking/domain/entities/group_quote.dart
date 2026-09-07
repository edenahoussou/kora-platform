import 'package:equatable/equatable.dart';

import 'price.dart';

/// Couche DOMAIN : le devis d'un groupe, avec le détail que le voyageur a
/// le droit de voir.
///
/// Les trois montants du ticket d'achat groupé viennent d'ici : le tarif
/// unitaire, la remise obtenue, et le total à payer. Ils sont **calculés
/// par le serveur** et repris tels quels — recalculer une remise côté app
/// afficherait un montant que la caisse ne reconnaîtrait pas.
class GroupQuote extends Equatable {
  const GroupQuote({
    required this.id,
    required this.subtotal,
    required this.total,
    required this.passengerCount,
    this.discount = GroupDiscount.none,
    this.expiresAt,
  });

  /// `itinerary_quote_uuid` : ce que réclame le blocage atomique.
  final String id;

  /// Le montant avant remise, tous voyageurs confondus.
  final Price subtotal;

  /// Ce qu'il y a réellement à payer.
  final Price total;

  final int passengerCount;

  final GroupDiscount discount;

  /// Le devis expire avant le blocage, et plus tôt.
  final DateTime? expiresAt;

  /// Le tarif d'un voyageur, avant remise.
  ///
  /// Arrondi à l'entier inférieur : le serveur ne publie que le sous-total,
  /// et un centime de plus afficherait un prix unitaire qui, multiplié,
  /// dépasserait le total annoncé.
  Price get unitPrice =>
      passengerCount > 0 ? Price(subtotal.amount ~/ passengerCount) : subtotal;

  @override
  List<Object?> get props => [
    id,
    subtotal,
    total,
    passengerCount,
    discount,
    expiresAt,
  ];
}

/// La remise de groupe telle que le serveur l'a appliquée.
class GroupDiscount extends Equatable {
  const GroupDiscount({
    required this.applied,
    this.threshold,
    this.percentage = 0,
    this.amount = const Price(0),
  });

  /// Aucune remise — un voyage individuel, ou un groupe sous le seuil.
  static const GroupDiscount none = GroupDiscount(applied: false);

  final bool applied;

  /// Nombre de voyageurs à partir duquel la remise s'applique. Renseigné
  /// par le serveur, jamais deviné : c'est une règle métier qui change
  /// sans que l'app en soit prévenue.
  final int? threshold;

  final double percentage;

  /// Le montant retiré du sous-total.
  final Price amount;

  /// Vrai quand il y a quelque chose à montrer au voyageur : une remise
  /// annoncée mais nulle ne mérite pas sa ligne dans le détail du prix.
  bool get isVisible => applied && amount.amount > 0;

  /// Le serveur connaît une règle, mais le groupe n'atteint pas encore son
  /// seuil.
  ///
  /// Distinct d'une absence de règle : là il y a quelque chose à dire au
  /// voyageur — combien de voyageurs il lui manque —, alors qu'un serveur
  /// sans barème n'a rien à annoncer du tout.
  bool get announcesThreshold => !applied && threshold != null;

  /// Vrai quand un écran consacré à la remise a matière à exister.
  ///
  /// Faux quand le serveur ne connaît aucune règle : un écran intitulé
  /// « Avantage appliqué » qui n'annonce aucun avantage ment au voyageur,
  /// et mieux vaut alors le sauter.
  bool get hasSomethingToSay => isVisible || announcesThreshold;

  @override
  List<Object?> get props => [applied, threshold, percentage, amount];
}
