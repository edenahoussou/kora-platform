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
  });

  final City origin;
  final City destination;

  /// Jour de départ souhaité (l'heure n'est pas significative).
  final DateTime date;

  final int passengers;

  /// Jour du retour, ou `null` pour un aller simple.
  ///
  /// Le serveur sait aussi chercher un aller-retour **sans** date de
  /// retour — il propose alors les départs de la semaine qui suit. L'app
  /// ne s'en sert pas : son formulaire demande toujours une date, et un
  /// chemin que rien n'emprunte ne se teste pas.
  final DateTime? returnDate;

  bool get isRoundTrip => returnDate != null;

  @override
  List<Object?> get props => [
    origin,
    destination,
    date,
    passengers,
    returnDate,
  ];
}
