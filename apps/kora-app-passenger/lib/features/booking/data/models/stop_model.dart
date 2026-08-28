import '../../../../core/network/api_json.dart';
import '../../domain/entities/city.dart';
import 'city_model.dart';

/// Couche DATA : un arrêt d'une ligne, tel que le renvoie l'API dans
/// `line.stops`, `origin_stop` et `destination_stop`.
///
/// ```json
/// {
///   "uuid": "6b72fe4a-…",
///   "name": "Gare de Cotonou",
///   "city": "Cotonou",
///   "country": "BJ",
///   "stop_order": 1,
///   "km_from_origin": 0.0
/// }
/// ```
///
/// [uuid] n'a pas d'équivalent dans le domaine aujourd'hui, mais il est
/// conservé ici : c'est lui qu'exigera l'endpoint des sièges
/// (`origin_stop_uuid` / `destination_stop_uuid`, voir
/// `ApiConstants.busDepartureSeats`).
class StopModel {
  const StopModel({
    required this.uuid,
    required this.name,
    required this.city,
    required this.country,
    required this.stopOrder,
    required this.kmFromOrigin,
  });

  factory StopModel.fromJson(Map<String, dynamic> json) {
    return StopModel(
      uuid: ApiJson.string(json['uuid']),
      name: ApiJson.string(json['name']),
      city: ApiJson.string(json['city']),
      country: ApiJson.string(json['country']),
      stopOrder: ApiJson.intOrNull(json['stop_order']) ?? 0,
      kmFromOrigin: ApiJson.doubleOrNull(json['km_from_origin']) ?? 0,
    );
  }

  final String uuid;

  /// Nom de l'arrêt (`Gare de Cotonou`).
  final String name;

  /// Ville dans laquelle se trouve l'arrêt (`Cotonou`).
  final String city;

  final String country;

  /// Rang de l'arrêt sur la ligne, à partir de 1.
  final int stopOrder;

  /// Distance depuis l'origine de la ligne, en kilomètres.
  final double kmFromOrigin;

  /// La ville du domaine correspondante, avec le nom de l'arrêt en gare.
  ///
  /// L'identifiant reste le **nom de la ville** — le même que celui de
  /// `GET /bus/cities` (voir [CityModel.toEntity]) — pour qu'une ville
  /// venue d'un départ et la même venue du sélecteur ne fassent jamais
  /// deux entrées différentes.
  City toCity() => City(
    id: city,
    name: city,
    code: CityModel.codeFor(city),
    // Les villes à un seul arrêt le nomment souvent comme elles-mêmes
    // (`"name": "Cotonou"` dans la ville `Cotonou`) : le reprendre
    // afficherait « Cotonou · Cotonou ». Sans gare distincte, il n'y a
    // rien à préciser.
    station: name == city ? null : ApiJson.stringOrNull(name),
  );
}
