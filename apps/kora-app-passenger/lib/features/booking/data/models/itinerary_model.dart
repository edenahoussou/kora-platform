import '../../../../core/network/api_json.dart';
import '../../domain/entities/amenity.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/itinerary.dart';
import '../../domain/entities/line.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/trip_stop.dart';

/// Couche DATA : un itinéraire de `GET bus/itineraries`.
///
/// Remplace `GET bus/departures`, retiré par MEZ-63. Le contrat n'est plus
/// le même : la recherche rend des **itinéraires**, chacun composé d'un ou
/// plusieurs tronçons, là où l'ancienne rendait des départs à plat.
///
/// Les correspondances sont rendues telles quelles : un itinéraire à
/// deux tronçons devient un [Itinerary] à deux tronçons, et c'est le
/// tunnel qui en fait une seule réservation. Les écarter — ce que
/// faisait cette classe tant que l'app ne savait pas les afficher —
/// revenait à annoncer « Aucun voyage ce jour-là » sur un Porto-Novo →
/// Parakou que le serveur savait composer.
class ItineraryModel {
  const ItineraryModel._(this._json);

  factory ItineraryModel.fromJson(Map<String, dynamic> json) =>
      ItineraryModel._(json);

  final Map<String, dynamic> _json;

  /// Le nombre de correspondances annoncé par le serveur.
  int get connectionsCount =>
      ApiJson.intOrNull(_json['connections_count']) ?? 0;

  /// Le voyage correspondant, ou `null` quand la réponse ne permet pas de
  /// le lire : aucun tronçon, ou un tronçon sans date.
  ///
  /// Un tronçon illisible écarte **tout l'itinéraire** : en garder la
  /// moitié vendrait un voyage qui ne mène pas où il est annoncé.
  Itinerary? toEntity() {
    final raw = ApiJson.list(_json['legs']);
    if (raw.isEmpty) return null;

    final legs = <Departure>[];
    for (final leg in raw) {
      final departure = _legOf(leg);
      if (departure == null) return null;
      legs.add(departure);
    }

    // Le serveur donne les heures du voyage entier : elles comprennent
    // l'attente aux correspondances, qu'aucune addition de durées de
    // lignes ne donnerait.
    final departsAt =
        _dateOrNull(_json['departure_at']) ?? legs.first.departsAt;
    final arrivesAt = _dateOrNull(_json['arrival_at']) ?? legs.last.arrivesAt;

    final margin = ApiJson.intOrNull(_json['connection_margin_minutes']);

    return Itinerary(
      legs: legs,
      // `total_amount` porte le voyage entier ; l'addition des tronçons
      // n'en est qu'un repli quand le serveur se tait.
      total: Price(
        ApiJson.intOrNull(_json['total_amount']) ??
            legs.fold(0, (sum, leg) => sum + leg.price.amount),
      ),
      departsAt: departsAt,
      arrivesAt: arrivesAt,
      connectionMargin: margin == null || legs.length == 1
          ? null
          : Duration(minutes: margin),
    );
  }

  /// Un arrêt du trajet : le point physique d'abord, l'arrêt de ligne
  /// en repli.
  ///
  /// `physical_stop` est nul sur les arrêts hérités, pas encore
  /// rattachés à un point : on reprend alors `name` et `city`, comme
  /// l'app le faisait avant que le serveur ne publie les points.
  static TripStop _tripStopOf(Map<String, dynamic> stop) {
    final physical = ApiJson.map(stop['physical_stop']);

    final name = ApiJson.stringOrNull(physical['name']);
    final city = ApiJson.stringOrNull(physical['city']);
    final km = stop['km_from_origin'];

    return TripStop(
      name: name?.isNotEmpty == true ? name! : ApiJson.string(stop['name']),
      city: city?.isNotEmpty == true
          ? city
          : ApiJson.stringOrNull(stop['city']),
      kmFromOrigin: km is num ? km.toDouble() : double.tryParse('$km'),
      departureAt: _wallClockOrNull(stop['departure_at']),
      arrivalAt: _wallClockOrNull(stop['arrival_at']),
      timezone: ApiJson.stringOrNull(stop['timezone']),
    );
  }

  /// L'heure **telle qu'on la lit à l'arrêt**, sans la ramener au fuseau
  /// du téléphone.
  ///
  /// Le serveur date déjà chaque passage dans le fuseau de l'arrêt
  /// (`2026-11-24T07:31:00+01:00`). `DateTime.parse` convertirait cet
  /// instant à l'heure de l'appareil : un voyageur béninois lirait 08:31
  /// pour un bus qui passe à 07:31 devant la gare d'Abidjan. On garde
  /// donc la partie murale de l'horodatage et on laisse tomber l'offset,
  /// que le serveur a déjà appliqué.
  static DateTime? _wallClockOrNull(Object? raw) {
    final text = ApiJson.stringOrNull(raw);
    if (text == null || text.isEmpty) return null;

    final local = RegExp(
      r'^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?',
    ).stringMatch(text);
    if (local == null) return null;

    return DateTime.tryParse(local.replaceFirst(' ', 'T'));
  }

  /// Un tronçon, ou `null` si sa date est illisible.
  static Departure? _legOf(Map<String, dynamic> leg) {
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
      arrival: _dateOrNull(leg['arrival_at']),
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
      tripStops: [
        for (final stop in ApiJson.list(leg['trip_stops'])) _tripStopOf(stop),
      ],
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
