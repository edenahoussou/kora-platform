import 'package:equatable/equatable.dart';

import 'city.dart';

/// Couche DOMAIN : ce que l'utilisateur a saisi dans le formulaire de
/// recherche de l'accueil, et qui sera transmis à l'écran de résultats.
///
/// Volontairement immuable, et sans champ nullable hors la date de
/// retour : tant que l'utilisateur n'a pas rempli départ / arrivée /
/// date, il n'y a pas de critère de recherche du tout — c'est l'état de
/// l'écran d'accueil (`HomeState`) qui porte la saisie en cours, avec ses
/// champs potentiellement vides. Le retour, lui, est bien une absence :
/// un aller simple n'en a pas.
class SearchCriteria extends Equatable {
  const SearchCriteria({
    required this.origin,
    required this.destination,
    required this.date,
    this.passengers = 1,
    this.returnDate,
    this.roundTrip = false,
  });

  final City origin;
  final City destination;

  /// Jour de départ souhaité (l'heure n'est pas significative).
  final DateTime date;

  final int passengers;

  /// Jour du retour souhaité, ou `null` quand le voyageur ne l'a pas
  /// encore arrêté.
  ///
  /// **Son absence ne fait pas un aller simple** : le serveur cherche un
  /// aller-retour sans date, et propose alors les retours des jours qui
  /// suivent. C'est [roundTrip] qui tranche, et lui seul.
  final DateTime? returnDate;

  /// Le voyageur a demandé un aller-retour.
  ///
  /// Distinct de [returnDate] : on choisit souvent son aller avant de
  /// savoir quand on rentre. Confondre les deux ramenait la recherche à
  /// un aller simple dès que la date manquait — sans le dire, et en
  /// faisant disparaître les retours que le serveur avait pourtant
  /// renvoyés.
  final bool roundTrip;

  /// Vrai dès que l'un des deux le dit : l'intention, ou une date de
  /// retour venue d'ailleurs (un lien profond, un billet relu).
  bool get isRoundTrip => roundTrip || returnDate != null;

  @override
  List<Object?> get props => [
    origin,
    destination,
    date,
    passengers,
    returnDate,
    roundTrip,
  ];
}
