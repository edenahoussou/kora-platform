import 'package:equatable/equatable.dart';

/// Couche DOMAIN : une ville desservie par le réseau KORA BUS.
///
/// C'est l'unité de base de la recherche : l'écran d'accueil demande une
/// ville de départ et une ville d'arrivée, et une [Line] relie deux
/// villes entre elles.
///
/// [station] est le nom de la gare KORA de la ville (ex: `St Michel` à
/// Cotonou). Il vaut `null` pour les villes qui n'ont qu'un seul point
/// d'arrêt — dans ce cas la maquette n'affiche que le nom de la ville
/// (`Malanville`), sans séparateur.
class City extends Equatable {
  const City({
    required this.id,
    required this.name,
    required this.code,
    this.station,
  });

  /// Ville désignée par son seul nom.
  ///
  /// L'API filtre les départs sur `from` et `to`, qui sont des noms :
  /// c'est tout ce qu'une recherche a besoin de connaître d'une ville.
  /// Le code à trois lettres s'en déduit, faute d'être exposé.
  factory City.named(String name) => City(
    id: name,
    name: name,
    code: name.length >= 3
        ? name.substring(0, 3).toUpperCase()
        : name.toUpperCase(),
  );

  final String id;

  /// Nom affiché de la ville (ex: `Cotonou`).
  final String name;

  /// Code à 3 lettres imprimé sur le billet (ex: `CTN`).
  final String code;

  /// Gare KORA desservie dans cette ville (ex: `St Michel`), ou `null`.
  final String? station;

  /// Libellé complet tel qu'affiché dans le champ de recherche :
  /// `Cotonou · St Michel`, ou simplement `Malanville` sans gare.
  String get label => station == null ? name : '$name · $station';

  @override
  List<Object?> get props => [id, name, code, station];
}
