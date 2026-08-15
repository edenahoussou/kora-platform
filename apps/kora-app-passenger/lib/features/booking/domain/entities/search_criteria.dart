import 'package:equatable/equatable.dart';

import 'city.dart';

/// Couche DOMAIN : ce que l'utilisateur a saisi dans le formulaire de
/// recherche de l'accueil, et qui sera transmis à l'écran de résultats.
///
/// Volontairement immuable et complet (aucun champ nullable) : tant que
/// l'utilisateur n'a pas rempli départ / arrivée / date, il n'y a pas de
/// critère de recherche du tout — c'est l'état de l'écran d'accueil
/// (`HomeState`) qui porte la saisie en cours, avec ses champs
/// potentiellement vides.
class SearchCriteria extends Equatable {
  const SearchCriteria({
    required this.origin,
    required this.destination,
    required this.date,
    this.passengers = 1,
  });

  final City origin;
  final City destination;

  /// Jour de départ souhaité (l'heure n'est pas significative).
  final DateTime date;

  final int passengers;

  @override
  List<Object?> get props => [origin, destination, date, passengers];
}
