import 'package:equatable/equatable.dart';

/// Qui règle la réservation.
///
/// Le ticket demande de distinguer clairement le **payeur** des voyageurs
/// bénéficiaires : sur un achat groupé, celui qui paie n'est pas
/// nécessairement de la partie, et il peut engager le compte de son
/// entreprise plutôt que le sien.
enum GroupPayerType {
  /// Le titulaire du compte KORA, à titre personnel.
  personal('personal'),

  /// Un compte entreprise dont le titulaire est membre actif.
  company('company');

  const GroupPayerType(this.wire);

  /// Ce que transporte `POST bus/itinerary-holds`.
  final String wire;
}

/// Le crédit entreprise est-il proposé au voyageur ?
///
/// **Non, et c'est délibéré.** Le serveur sait réserver le crédit d'un
/// compte entreprise au blocage des sièges — `CompanyCreditService::reserve`
/// — et il sait le convertir en dépense à la confirmation du paiement. Mais
/// `POST payments/initiate` n'accepte que `mobile_money` et `wallet` : un
/// paiement `company_credit` ne naît qu'au guichet.
///
/// Offrir le choix quand même menait à ceci : le crédit de l'entreprise
/// était bloqué, le voyageur payait de sa poche en Mobile Money, et la
/// réservation de crédit expirait sans avoir servi. Le bouton promettait
/// une facturation entreprise et livrait un débit personnel.
///
/// Remettre à `true` le jour où `initiate` accepte `company_credit` : tout
/// le reste — le choix, l'envoi au blocage, l'affichage du crédit restant —
/// est déjà en place et attend derrière.
const bool kCompanyPayerEnabled = false;

/// Couche DOMAIN : le payeur d'une réservation de groupe.
class GroupPayer extends Equatable {
  const GroupPayer._(this.type, this.companyAccountId, this.companyName);

  /// Le titulaire paie lui-même.
  const GroupPayer.personal() : this._(GroupPayerType.personal, null, null);

  /// Le compte entreprise règle la note.
  ///
  /// [companyName] n'est là que pour l'affichage : le serveur ne connaît
  /// que l'identifiant.
  const GroupPayer.company({required String accountId, String? name})
    : this._(GroupPayerType.company, accountId, name);

  final GroupPayerType type;

  /// `company_account_uuid`, exigé par le serveur quand [type] vaut
  /// [GroupPayerType.company].
  final String? companyAccountId;

  final String? companyName;

  bool get isCompany => type == GroupPayerType.company;

  /// Le bloc `payer` de la requête, ou `null` pour laisser le serveur
  /// retomber sur son défaut.
  ///
  /// Un paiement personnel n'a rien à déclarer : `payer` est `sometimes`
  /// côté serveur, et taire le bloc vaut mieux qu'envoyer un
  /// `user_uuid` que l'app devine.
  Map<String, dynamic>? toJson() {
    if (!isCompany) return null;

    return {'type': type.wire, 'company_account_uuid': companyAccountId};
  }

  @override
  List<Object?> get props => [type, companyAccountId, companyName];
}
