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
/// domaine. C'est aussi lui qui part en `from_city` / `to_city`.
///
/// **Une entrée par ville, jamais une par arrêt.** Les arrêts physiques
/// ont été, un temps, la clé de la recherche : une ville à deux arrêts
/// donnait alors deux entrées dans le sélecteur, et choisir la mauvaise
/// valait « Aucun voyage ce jour-là » alors que les départs étaient sur
/// l'autre. Le serveur explore maintenant lui-même tous les points
/// d'arrêt de la ville ; `physical_stops` ne sert donc plus ici qu'à
/// savoir si la ville est desservie.
class CityModel {
  const CityModel({
    required this.city,
    required this.country,
    this.servedStops,
    this.stopNames = const [],
  });

  factory CityModel.fromJson(Map<String, dynamic> json) {
    // Absent de la réponse : on ne sait rien, et ne rien savoir ne doit
    // pas vider le sélecteur. Présent et vide : le catalogue dit que la
    // ville n'est desservie par aucun arrêt.
    final stops =
        json.containsKey('physical_stops') || json.containsKey('stops_count')
        ? ApiJson.intOrNull(json['stops_count']) ??
              ApiJson.list(json['physical_stops']).length
        : null;

    return CityModel(
      city: ApiJson.string(json['city']),
      country: ApiJson.string(json['country']),
      servedStops: stops,
      stopNames: [
        for (final stop in ApiJson.list(json['physical_stops']))
          if (ApiJson.string(stop['name']).isNotEmpty)
            ApiJson.string(stop['name']),
      ],
    );
  }

  /// Nom de la ville (`Cotonou`) — sert aussi de clé auprès de l'API.
  final String city;

  /// Code pays ISO à deux lettres (`BJ`, `CI`).
  final String country;

  /// Nombre d'arrêts KORA desservant la ville, ou `null` quand la réponse
  /// n'en dit rien.
  final int? servedStops;

  /// Les noms des points d'arrêt, dans l'ordre du serveur.
  ///
  /// Le sélecteur les affiche sous la ville : « Cotonou » ne dit pas d'où
  /// part le bus, « Gare de Dantokpa » si. Ils ne servent qu'à ça — la
  /// recherche continue de partir au nom de la ville.
  final List<String> stopNames;

  /// L'entrée à proposer au voyageur, ou `null` quand il n'y en a pas :
  /// une ville sans nom, ou que le catalogue annonce sans arrêt.
  City? toEntity() {
    if (city.isEmpty || servedStops == 0) return null;

    return City(id: city, name: city, code: codeFor(city), stops: stopNames);
  }

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
