import '../../domain/entities/city.dart';
import 'bus_json.dart';

/// Couche DATA : une ville telle que la renvoie `GET /api/v1/bus/cities`.
///
/// ```json
/// { "city": "Cotonou", "country": "BJ", "stops_count": 3 }
/// ```
///
/// L'API ne renvoie **ni identifiant, ni code de ville** : c'est le nom
/// qui sert de clé, et c'est lui qu'attendent les filtres `from` / `to`
/// des endpoints lignes et départs. [toEntity] en fait donc l'identifiant
/// de la [City] du domaine — ce qui garantit qu'une ville choisie dans le
/// sélecteur peut être renvoyée telle quelle à l'API.
class CityModel {
  const CityModel({
    required this.city,
    required this.country,
    required this.stopsCount,
  });

  factory CityModel.fromJson(Map<String, dynamic> json) {
    return CityModel(
      city: BusJson.string(json['city']),
      country: BusJson.string(json['country']),
      stopsCount: BusJson.intOrNull(json['stops_count']) ?? 0,
    );
  }

  /// Nom de la ville (`Cotonou`) — sert aussi de clé auprès de l'API.
  final String city;

  /// Code pays ISO à deux lettres (`BJ`, `CI`).
  final String country;

  /// Nombre d'arrêts KORA actifs dans cette ville.
  final int stopsCount;

  City toEntity() => City(
    id: city,
    name: city,
    code: codeFor(city),
    // Une ville peut desservir plusieurs arrêts ([stopsCount]) : aucun
    // n'est « la » gare de la ville. Le nom de l'arrêt n'arrive qu'avec un
    // départ précis, via `origin_stop` / `destination_stop` (voir
    // `StopModel`).
    station: null,
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
