import '../../../../core/network/api_json.dart';
import '../../domain/entities/line.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/trip_segment.dart';
import 'stop_model.dart';

/// Couche DATA : une ligne du réseau, telle que la renvoie l'API dans
/// `GET /api/v1/bus/lines` et dans le champ `line` d'un départ.
///
/// ```json
/// {
///   "uuid": "6ff8f7f6-…",
///   "code": "COT-ABJ",
///   "name": "Cotonou — Abidjan",
///   "countries_crossed": ["BJ", "CI"],
///   "distance_km": 1050.5,
///   "estimated_duration_minutes": 480,
///   "status": "active",
///   "stops": [ … ]
/// }
/// ```
///
/// [stops] n'est renseigné que par l'endpoint des lignes : la ligne
/// imbriquée dans un départ ne les porte pas, l'API donnant à la place
/// l'arrêt d'origine et de destination du segment cherché.
class LineModel {
  const LineModel({
    required this.uuid,
    required this.code,
    required this.name,
    required this.countriesCrossed,
    required this.distanceKm,
    required this.estimatedDurationMinutes,
    required this.status,
    required this.stops,
  });

  factory LineModel.fromJson(Map<String, dynamic> json) {
    return LineModel(
      uuid: ApiJson.string(json['uuid']),
      code: ApiJson.string(json['code']),
      name: ApiJson.string(json['name']),
      countriesCrossed: ApiJson.strings(json['countries_crossed']),
      distanceKm: ApiJson.doubleOrNull(json['distance_km']) ?? 0,
      estimatedDurationMinutes:
          ApiJson.intOrNull(json['estimated_duration_minutes']) ?? 0,
      status: ApiJson.string(json['status']),
      stops: [
        for (final stop in ApiJson.list(json['stops']))
          StopModel.fromJson(stop),
      ],
    );
  }

  final String uuid;

  /// Code de la ligne (`COT-ABJ`).
  final String code;

  /// Libellé de la ligne (`Cotonou — Abidjan`).
  final String name;

  /// Codes pays traversés, dans l'ordre du trajet.
  final List<String> countriesCrossed;

  /// Longueur totale de la ligne, en kilomètres.
  final double distanceKm;

  /// Temps de parcours théorique de la ligne **entière**.
  final int estimatedDurationMinutes;

  /// `active`, `suspended`... Non interprété pour l'instant : la recherche
  /// ne renvoie que des lignes exploitées.
  final String status;

  /// Arrêts ordonnés, vides quand la ligne arrive imbriquée dans un départ.
  final List<StopModel> stops;

  /// La [Line] du domaine pour la portion réellement voyagée, d'[origin] à
  /// [destination].
  ///
  /// **Pourquoi la portion et non la ligne entière ?** Parce que c'est ce
  /// que l'écran affiche : quelqu'un qui cherche Cotonou → Bohicon doit
  /// lire « Cotonou → Bohicon, ≈ 55 min », pas le terminus d'Abidjan à
  /// 8 h. L'API donne la durée de la ligne complète ; on la ramène au
  /// prorata de la distance de la portion, faute d'horaire par arrêt dans
  /// cette réponse.
  ///
  /// L'heure d'arrivée n'est pas reprise du champ `estimated_arrival_at`
  /// de l'API : il est daté en UTC alors que `departure_time` est une
  /// heure locale, et mélanger les deux décalerait l'arrivée d'un fuseau.
  /// La durée, elle, ne dépend d'aucun fuseau.
  Line toEntity({
    required StopModel origin,
    required StopModel destination,
    required Price price,
  }) {
    final segmentKm = (destination.kmFromOrigin - origin.kmFromOrigin).abs();
    final travelledKm = segmentKm > 0 ? segmentKm : distanceKm;
    final duration = _durationFor(travelledKm);

    return Line(
      id: uuid,
      code: code.isEmpty ? null : code,
      origin: origin.toCity(),
      destination: destination.toCity(),
      duration: duration,
      distanceKm: travelledKm.round(),
      // Un seul tronçon : les arrêts intermédiaires ne figurent pas dans
      // la réponse des départs. L'écran de détail affiche donc le trajet
      // comme direct, ce qu'il est du point de vue du voyageur — il monte
      // à [origin] et descend à [destination].
      segments: [
        TripSegment(
          origin: origin.toCity(),
          destination: destination.toCity(),
          departureOffset: Duration.zero,
          arrivalOffset: duration,
          distanceKm: travelledKm.round(),
          price: price,
        ),
      ],
    );
  }

  /// Le temps de parcours de [travelledKm], au prorata de la ligne
  /// entière. Retombe sur la durée annoncée quand les distances sont
  /// inutilisables (portion aussi longue que la ligne, ou données à zéro).
  Duration _durationFor(double travelledKm) {
    final full = Duration(minutes: estimatedDurationMinutes);
    if (distanceKm <= 0 || travelledKm <= 0 || travelledKm >= distanceKm) {
      return full;
    }

    return Duration(
      milliseconds: (full.inMilliseconds * travelledKm / distanceKm).round(),
    );
  }
}
