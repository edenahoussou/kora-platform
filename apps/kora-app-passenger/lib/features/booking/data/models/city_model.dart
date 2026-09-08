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
/// leur `uuid` que réclame `bus/itineraries`. Le premier est retenu : une
/// ville en a presque toujours un seul, et rien dans le contrat ne permet
/// de préférer l'un des autres. Le jour où une ville en desservira
/// plusieurs pour de bon, il faudra les proposer au voyageur plutôt que
/// d'en choisir un à sa place.
class CityModel {
  const CityModel({
    required this.city,
    required this.country,
    required this.stopsCount,
    this.physicalStopId,
  });

  factory CityModel.fromJson(Map<String, dynamic> json) {
    final stops = ApiJson.list(json['physical_stops']);

    return CityModel(
      city: ApiJson.string(json['city']),
      country: ApiJson.string(json['country']),
      stopsCount: ApiJson.intOrNull(json['stops_count']) ?? stops.length,
      physicalStopId: stops.isEmpty
          ? null
          : ApiJson.stringOrNull(stops.first['uuid']),
    );
  }

  /// Nom de la ville (`Cotonou`) — sert aussi de clé auprès de l'API.
  final String city;

  /// Code pays ISO à deux lettres (`BJ`, `CI`).
  final String country;

  /// Nombre d'arrêts KORA actifs dans cette ville.
  final int stopsCount;

  /// L'arrêt physique retenu pour chercher un itinéraire depuis ou vers
  /// cette ville. `null` si le serveur n'en annonce aucun.
  final String? physicalStopId;

  City toEntity() => City(
    id: city,
    name: city,
    code: codeFor(city),
    // Une ville peut desservir plusieurs arrêts ([stopsCount]) : aucun
    // n'est « la » gare de la ville. Le nom de l'arrêt n'arrive qu'avec un
    // départ précis, via `origin_stop` / `destination_stop` (voir
    // `StopModel`).
    station: null,
    physicalStopId: physicalStopId,
  );

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
