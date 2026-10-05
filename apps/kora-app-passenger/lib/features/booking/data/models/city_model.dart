import '../../../../core/network/api_json.dart';
import '../../domain/entities/city.dart';

/// Couche DATA : une ville telle que la renvoie `GET /api/v1/bus/cities`.
///
/// ```json
/// {
///   "city": "Cotonou",
///   "country": "BJ",
///   "physical_stops": [{ "uuid": "74703fce-…", "name": "Cotonou" }]
/// }
/// ```
///
/// L'API ne renvoie **ni identifiant, ni code de ville** : le nom sert de
/// clé d'affichage, et [toEntity] en fait l'identifiant de la [City] du
/// domaine.
///
/// Depuis MEZ-63, elle rend en revanche les **arrêts physiques**, et c'est
/// leur `uuid` que réclame `bus/itineraries`.
///
/// **Une ville peut en porter plusieurs, et on ne choisit pas pour le
/// voyageur.** Retenir le premier de la liste a été tenté : il suffit
/// qu'une ville gagne un second arrêt — doublon hérité ou vraie seconde
/// gare — pour que l'app interroge le mauvais, et annonce « Aucun voyage
/// ce jour-là » alors que les départs existent sur l'autre. Rien dans le
/// contrat ne permet de préférer l'un des arrêts ; le seul à pouvoir
/// trancher est celui qui prend le bus. Chaque arrêt devient donc sa
/// propre entrée dans le sélecteur, nommée par sa gare.
class CityModel {
  const CityModel({
    required this.city,
    required this.country,
    required this.stopsCount,
    this.stops = const [],
  });

  factory CityModel.fromJson(Map<String, dynamic> json) {
    final raw = ApiJson.list(json['physical_stops']);

    final stops = [
      for (final stop in raw)
        if (ApiJson.stringOrNull(stop['uuid']) case final String uuid
            when uuid.isNotEmpty)
          CityStop(uuid: uuid, name: ApiJson.string(stop['name'])),
    ];

    return CityModel(
      city: ApiJson.string(json['city']),
      country: ApiJson.string(json['country']),
      stopsCount: ApiJson.intOrNull(json['stops_count']) ?? raw.length,
      stops: stops,
    );
  }

  /// Nom de la ville (`Cotonou`) — sert aussi de clé auprès de l'API.
  final String city;

  /// Code pays ISO à deux lettres (`BJ`, `CI`).
  final String country;

  /// Nombre d'arrêts KORA actifs dans cette ville.
  final int stopsCount;

  /// Les arrêts physiques que cette ville dessert, dans l'ordre du
  /// serveur. Vide si le catalogue n'en rattache aucun.
  final List<CityStop> stops;

  /// Les entrées à proposer au voyageur : **une par arrêt physique**.
  ///
  /// Une ville à un seul arrêt donne une seule entrée, nommée par la ville
  /// — c'est le cas courant, et le voyageur n'a rien à arbitrer. Une ville
  /// à plusieurs arrêts en donne autant, chacune portant le nom de sa
  /// gare, parce qu'elles ne mènent pas aux mêmes départs.
  ///
  /// Une ville sans arrêt ne donne rien : la recherche travaille sur des
  /// arrêts, et la proposer mènerait à une impasse.
  List<City> toEntities() => [
    for (final stop in stops)
      City(
        // Deux gares d'une même ville sont deux points de départ
        // distincts : l'identifiant doit les distinguer, sans quoi le
        // sélecteur les confondrait.
        id: stops.length == 1 ? city : stop.uuid,
        name: city,
        code: codeFor(city),
        // Nommer la gare n'a de sens que s'il y a un choix à faire — et
        // « Cotonou · Cotonou » n'apprend rien à personne.
        station: stops.length == 1 || stop.name == city ? null : stop.name,
        physicalStopId: stop.uuid,
      ),
  ];

  /// Code à trois lettres affiché sur un billet (`Cotonou` → `COT`).
  ///
  /// **Dérivé du nom, faute de mieux** : l'API n'expose pas de code de
  /// ville (seules les *lignes* en ont un, `COT-ABJ`). Le jour où elle en
  /// renverra un, seule cette méthode disparaîtra. En attendant, c'est
  /// une valeur d'affichage : rien dans l'app ne s'en sert pour
  /// identifier une ville — c'est le rôle de [City.id].
  static String codeFor(String name) {
    final letters = name.toUpperCase().replaceAll(RegExp('[^A-Z]'), '');
    if (letters.isEmpty) return name.toUpperCase();
    return letters.length <= 3 ? letters : letters.substring(0, 3);
  }
}

/// Un arrêt physique du catalogue : l'identifiant qu'attend
/// `bus/itineraries`, et le nom sous lequel le voyageur le reconnaît.
class CityStop {
  const CityStop({required this.uuid, required this.name});

  final String uuid;
  final String name;
}
