import 'package:equatable/equatable.dart';

import 'seat.dart';

/// Couche DOMAIN : l'identité d'un voyageur pour un billet donné.
///
/// Rattachée au [seat] qu'elle occupe : chaque passager a son siège et son
/// QR Code, même si un seul paiement couvre la réservation.
///
/// Volontairement minimale — prénom, nom, téléphone, e-mail — ce que
/// demande le formulaire national de la maquette. Le formulaire
/// international (pièce d'identité, nationalité, date de naissance) est un
/// autre écran, et ajoutera ses champs le jour où il sera écrit.
class Passenger extends Equatable {
  const Passenger({
    required this.firstName,
    required this.lastName,
    required this.phone,
    required this.seat,
    this.email,
  });

  final String firstName;
  final String lastName;

  /// Numéro joignable le jour du départ : c'est par là que passent les
  /// avis de retard.
  final String phone;

  final Seat seat;

  /// Facultatif — sert à l'envoi du billet par courriel.
  final String? email;

  String get fullName => '$firstName $lastName';

  @override
  List<Object?> get props => [firstName, lastName, phone, seat, email];
}
