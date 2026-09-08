import '../../../../core/network/api_json.dart';
import '../../domain/entities/amenity.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/line.dart';
import '../../domain/entities/price.dart';

/// Couche DATA : un itinéraire de `GET bus/itineraries`.
///
/// Remplace `GET bus/departures`, retiré par MEZ-63. Le contrat n'est plus
/// le même : la recherche rend des **itinéraires**, chacun composé d'un ou
/// plusieurs tronçons, là où l'ancienne rendait des départs à plat.
///
/// L'app ne sait afficher qu'un trajet direct. Un itinéraire à
/// correspondance est donc écarté par [toEntity] plutôt que réduit à son
/// premier tronçon — montrer « Cotonou → Abidjan » pour un trajet qui
/// impose un changement à Lomé serait un mensonge sur le voyage vendu. Les
/// écrans de correspondance de la maquette (BUS-24, BUS-25) restent à
/// faire.
class ItineraryModel {
  const ItineraryModel._(this._json);

  factory ItineraryModel.fromJson(Map<String, dynamic> json) =>
      ItineraryModel._(json);

  final Map<String, dynamic> _json;

  /// Le nombre de correspondances annoncé par le serveur.
  int get connectionsCount =>
      ApiJson.intOrNull(_json['connections_count']) ?? 0;

  /// Le départ correspondant, ou `null` si l'itinéraire n'est pas direct
  /// ou si sa date est illisible.
  Departure? toEntity() {
    final legs = ApiJson.list(_json['legs']);
    if (connectionsCount != 0 || legs.length != 1) return null;

    final leg = legs.first;
    final departsAt = _dateOrNull(leg['departure_at']);
    if (departsAt == null) return null;

    final origin = ApiJson.map(leg['origin_stop']);
    final destination = ApiJson.map(leg['destination_stop']);
    final line = ApiJson.map(leg['line']);
    final bus = ApiJson.map(leg['bus']);

    final originCountry = ApiJson.stringOrNull(origin['country']);
    final destinationCountry = ApiJson.stringOrNull(destination['country']);

    return Departure(
      id: ApiJson.string(leg['departure_uuid']),
      line: Line(
        id: ApiJson.string(line['uuid']),
        code: ApiJson.string(line['code']),
        origin: _cityOf(origin),
        destination: _cityOf(destination),
        duration: Duration(
          minutes: ApiJson.intOrNull(line['estimated_duration_minutes']) ?? 0,
        ),
        // `bus/itineraries` ne publie pas la distance : elle ne sert qu'au
        // détail de la ligne, que cet écran-ci n'affiche pas.
        distanceKm: ApiJson.intOrNull(line['distance_km']) ?? 0,
      ),
      departsAt: departsAt,
      comfort: _comfortOf(ApiJson.string(leg['service_class_code'])),
      price: Price(ApiJson.intOrNull(leg['amount']) ?? 0),
      seatsTotal: ApiJson.intOrNull(bus['seat_count']) ?? 0,
      seatsAvailable: ApiJson.intOrNull(leg['available_seats_count']) ?? 0,
      originStopId: ApiJson.stringOrNull(origin['uuid']),
      destinationStopId: ApiJson.stringOrNull(destination['uuid']),
      originCountry: originCountry,
      destinationCountry: destinationCountry,
      amenities: [
        for (final amenity in ApiJson.list(bus['amenities']))
          if (ApiJson.string(amenity['code']).isNotEmpty)
            Amenity(
              code: ApiJson.string(amenity['code']),
              label: ApiJson.string(amenity['label']),
            ),
      ],
      busLabel: _busLabel(bus),
    );
  }

  static City _cityOf(Map<String, dynamic> stop) {
    final city = ApiJson.string(stop['city']);
    final name = ApiJson.string(stop['name']);

    return City(
      id: city.isNotEmpty ? city : name,
      name: city.isNotEmpty ? city : name,
      code: _codeOf(city.isNotEmpty ? city : name),
      // Le nom de l'arrêt n'est affiché que s'il diffère de la ville :
      // « Cotonou · Cotonou » n'apprendrait rien.
      station: name.isNotEmpty && name != city ? name : null,
    );
  }

  /// La gamme commerciale, depuis `service_class_code`.
  ///
  /// Un code inconnu retombe sur la gamme d'entrée plutôt que de faire
  /// échouer la lecture : une gamme ajoutée côté serveur ne doit pas vider
  /// la liste des résultats.
  static BusComfort _comfortOf(String code) => switch (code) {
    'vip' => BusComfort.vip,
    'confort' => BusComfort.confort,
    _ => BusComfort.classique,
  };

  /// `KORA Coach 55`, ou `null` si l'API ne dit rien du véhicule.
  static String? _busLabel(Map<String, dynamic> bus) {
    final parts = [
      ApiJson.string(bus['brand']),
      ApiJson.string(bus['model']),
    ].where((part) => part.isNotEmpty);

    return parts.isEmpty ? null : parts.join(' ');
  }

  static String _codeOf(String name) {
    final letters = name.toUpperCase().replaceAll(RegExp('[^A-Z]'), '');
    if (letters.isEmpty) return name.toUpperCase();

    return letters.length <= 3 ? letters : letters.substring(0, 3);
  }

  static DateTime? _dateOrNull(Object? raw) {
    final text = ApiJson.stringOrNull(raw);
    if (text == null) return null;

    return DateTime.tryParse(text)?.toLocal();
  }
}
