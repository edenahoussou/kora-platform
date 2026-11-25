import 'package:equatable/equatable.dart';

/// Couche DOMAIN : une ville desservie par le réseau KORA BUS.
///
/// C'est l'unité de base de la recherche : l'écran d'accueil demande une
/// ville de départ et une ville d'arrivée, et une [Line] relie deux
/// villes entre elles.
///
/// [station] est le nom de l'arrêt KORA d'où part ou arrive le bus (ex:
/// `St Michel` à Cotonou). Il ne sert qu'à **décrire un trajet trouvé** —
/// le point d'embarquement d'un tronçon — et vaut `null` partout
/// ailleurs, notamment dans le sélecteur, qui ne montre que des villes.
///
/// La recherche, elle, se fait au **nom de la ville** : `bus/itineraries`
/// accepte `from_city` et `to_city`, et c'est le serveur qui explore tous
/// les points d'arrêt de la ville. L'app n'a donc plus à choisir une gare
/// pour le voyageur — c'est ce choix impossible qui lui faisait annoncer
/// « Aucun voyage ce jour-là » quand la ville en avait deux et que les
/// départs étaient sur l'autre.
/// Couche DOMAIN : un point d'arrêt KORA dans une ville.
///
/// L'identifiant compte autant que le nom : c'est lui que
/// `bus/itineraries` attend en `from_physical_stop_uuid` quand le
/// voyageur précise d'où il part.
class CityStop extends Equatable {
  const CityStop({required this.uuid, required this.name});

  final String uuid;
  final String name;

  @override
  List<Object?> get props => [uuid, name];
}

class City extends Equatable {
  const City({
    required this.id,
    required this.name,
    required this.code,
    this.station,
    this.stops = const [],
    this.stopUuid,
  });

  /// Ville désignée par son seul nom.
  ///
  /// Sert aux jeux de démonstration et aux villes reconstruites depuis un
  /// billet. Elle vaut une ville du catalogue pour chercher : le serveur
  /// compare les noms sans tenir compte de la casse ni des accents.
  factory City.named(String name) => City(
    id: name,
    name: name,
    code: name.length >= 3
        ? name.substring(0, 3).toUpperCase()
        : name.toUpperCase(),
  );

  final String id;

  /// Nom affiché de la ville (ex: `Cotonou`).
  ///
  /// C'est aussi la clé envoyée à l'API : il part **tel que reçu** de
  /// `bus/cities`.
  final String name;

  /// Code à 3 lettres imprimé sur le billet (ex: `CTN`).
  final String code;

  /// Arrêt KORA d'un tronçon trouvé (ex: `St Michel`), ou `null`.
  final String? station;

  /// Les points d'arrêt KORA de la ville, tels que `bus/cities` les
  /// rend.
  ///
  /// Ils disent au voyageur où l'on monte. Il peut en retenir un quand
  /// la ville en porte plusieurs — voir [atStop] — mais rien ne l'y
  /// oblige : sans précision, la recherche part au nom de la ville et
  /// c'est le serveur qui explore tous les points. C'est ce repli qui
  /// évite le « Aucun voyage ce jour-là » d'une ville à deux gares dont
  /// les départs partaient de l'autre.
  final List<CityStop> stops;

  /// L'arrêt retenu, quand le voyageur en a choisi un.
  ///
  /// Part en `from_physical_stop_uuid` / `to_physical_stop_uuid` : le
  /// serveur ne cherche alors que les départs de ce point-là.
  final String? stopUuid;

  /// La même ville, ramenée à [stop].
  ///
  /// Le nom de l'arrêt passe dans [station], donc dans [label] : le
  /// voyageur relit son choix dans le champ de recherche au lieu de
  /// devoir s'en souvenir.
  City atStop(CityStop stop) => City(
    id: id,
    name: name,
    code: code,
    station: stop.name,
    stops: stops,
    stopUuid: stop.uuid,
  );

  /// Vrai quand cette ville peut servir de point de départ ou d'arrivée à
  /// une recherche : il suffit qu'elle ait un nom.
  bool get isSearchable => name.isNotEmpty;

  /// Libellé complet tel qu'affiché dans le champ de recherche :
  /// `Cotonou · St Michel`, ou simplement `Malanville` sans gare.
  String get label => station == null ? name : '$name · $station';

  @override
  List<Object?> get props => [id, name, code, station, stops, stopUuid];
}
