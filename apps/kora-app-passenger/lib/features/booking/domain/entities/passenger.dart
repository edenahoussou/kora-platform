import 'package:equatable/equatable.dart';

import 'seat.dart';

/// Couche DOMAIN : l'identité d'un voyageur pour un billet donné.
///
/// Rattachée au [seat] qu'elle occupe : chaque passager a son siège et son
/// QR Code, même si un seul paiement couvre la réservation.
///
/// Deux jeux de champs selon le trajet. Sur une liaison intérieure,
/// prénom, nom, téléphone et courriel facultatif suffisent. Dès que le
/// voyage franchit une frontière, le manifeste réclame une pièce
/// d'identité, une nationalité et une date de naissance — les quatre
/// derniers champs, alors obligatoires.
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

  String get fullName => '$firstName $lastName';

  /// Le voyageur porte les informations du manifeste de frontière.
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
  ];
}

/// Les pièces acceptées au passage d'une frontière.
///
/// Les cinq valeurs du serveur, pas les deux de la maquette : c'est lui
/// qui refuse (422) une pièce qu'il ne connaît pas, et un voyageur muni
/// d'une carte consulaire n'a pas à choisir « passeport » par défaut de
/// mieux.
///
/// [wire] est ce que transporte `POST bookings/{uuid}/passengers` ; il ne
/// se déduit pas du nom Dart, qui suit la casse du langage.
enum TravelDocumentType {
  nationalId('national_id'),
  biometricCard('biometric_card'),
  passport('passport'),
  consularCard('consular_card'),
  cip('cip');

  const TravelDocumentType(this.wire);

  final String wire;
}
