import 'package:equatable/equatable.dart';

import 'seat.dart';

/// Couche DOMAIN : l'identité d'un voyageur pour un billet donné.
///
/// Rattachée au [seat] qu'elle occupe : chaque passager a son siège et son
/// QR Code, même si un seul paiement couvre la réservation.
///
/// Deux jeux de champs selon le trajet. Sur une liaison intérieure,
/// prénom, nom, téléphone et courriel facultatif suffisent. Au-delà d'une
/// frontière s'ajoutent la nationalité, la date de naissance et, quand le
/// voyageur en dispose, sa pièce d'identité.
///
/// **La pièce reste facultative** depuis la revue Kora du 29 août 2026 :
/// un voyageur dont le document est expiré, perdu ou en cours de
/// renouvellement doit pouvoir réserver. C'est la confirmation
/// d'exactitude, cochée à la fin du formulaire, qui engage le voyageur —
/// pas la présence d'un numéro de passeport.
///
/// Ils sont portés par la même classe plutôt que par deux : c'est le même
/// voyageur sur le même billet, et le tunnel qui suit — blocage, paiement,
/// billet — n'a aucune raison de connaître deux formes de passager.
class Passenger extends Equatable {
  const Passenger({
    required this.firstName,
    required this.lastName,
    required this.phone,
    required this.seat,
    this.email,
    this.documentType,
    this.documentNumber,
    this.nationality,
    this.birthDate,
    this.yellowFeverVaccinated = false,
  });

  final String firstName;
  final String lastName;

  /// Numéro joignable le jour du départ : c'est par là que passent les
  /// avis de retard.
  final String phone;

  final Seat seat;

  /// Facultatif — sert à l'envoi du billet par courriel.
  final String? email;

  /// Pièce présentée à la frontière. `null` sur une liaison intérieure.
  final TravelDocumentType? documentType;

  /// Numéro de cette pièce (`BJ0427851`).
  final String? documentNumber;

  /// Code pays ISO de la nationalité déclarée (`BJ`, `TG`).
  final String? nationality;

  final DateTime? birthDate;

  /// Le voyageur déclare être vacciné contre la fièvre jaune.
  ///
  /// Une déclaration, pas une preuve : KORA ne voit pas le carnet, et la
  /// case sert à l'agent qui contrôle à l'embarquement. Propre à chaque
  /// voyageur — deux personnes du même billet n'ont pas le même carnet —
  /// et indépendante du document présenté : on peut être vacciné sans
  /// passeport, et l'inverse.
  final bool yellowFeverVaccinated;

  String get fullName => '$firstName $lastName';

  /// Le voyageur a présenté une pièce complète — type **et** numéro.
  ///
  /// Un type sans numéro ne vaut rien pour le manifeste, et l'inverse non
  /// plus : depuis que les deux champs sont facultatifs, l'un peut arriver
  /// sans l'autre.
  bool get hasTravelDocument =>
      documentType != null && (documentNumber?.isNotEmpty ?? false);

  @override
  List<Object?> get props => [
    firstName,
    lastName,
    phone,
    seat,
    email,
    documentType,
    documentNumber,
    nationality,
    birthDate,
    yellowFeverVaccinated,
  ];
}

/// Les pièces acceptées au passage d'une frontière.
///
/// Les valeurs du serveur, pas les deux de la maquette : c'est lui qui
/// refuse (422) une pièce qu'il ne connaît pas, et un voyageur muni d'une
/// carte consulaire n'a pas à choisir « passeport » par défaut de mieux.
///
/// Le CIP a été retiré à la revue Kora du 29 août 2026. Le serveur
/// l'accepte encore — son `in:` le liste — mais l'app cesse de le
/// proposer : c'est une décision métier, pas une contrainte technique.
///
/// [wire] est ce que transporte `POST bookings/{uuid}/passengers` ; il ne
/// se déduit pas du nom Dart, qui suit la casse du langage.
enum TravelDocumentType {
  nationalId('national_id'),
  biometricCard('biometric_card'),
  passport('passport'),
  consularCard('consular_card');

  const TravelDocumentType(this.wire);

  final String wire;
}
