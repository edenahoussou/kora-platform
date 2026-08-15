import 'package:equatable/equatable.dart';

import 'city.dart';
import 'trip_segment.dart';

/// Couche DOMAIN : une ligne régulière du réseau KORA BUS.
///
/// Une ligne décrit un trajet **indépendamment de l'horaire** : Cotonou →
/// Malanville, 733 km, environ 9 h 45, en desservant Bohicon, Parakou et
/// Kandi. Ce sont les [Departure] qui déclinent une ligne en départs datés.
class Line extends Equatable {
  const Line({
    required this.id,
    required this.origin,
    required this.destination,
    required this.duration,
    required this.distanceKm,
    this.segments = const [],
  });

  final String id;
  final City origin;
  final City destination;

  /// Temps de trajet théorique entre [origin] et [destination].
  final Duration duration;

  final int distanceKm;

  /// Le trajet découpé en tronçons, d'[origin] à [destination]. Une ligne
  /// directe n'en a qu'un seul.
  ///
  /// Les arrêts intermédiaires ne sont pas stockés à part : ils se
  /// déduisent des tronçons (voir [stops]), ce qui évite d'avoir deux
  /// représentations du même trajet qui pourraient diverger.
  final List<TripSegment> segments;

  /// Arrêts intermédiaires, dans l'ordre du trajet ([origin] et
  /// [destination] non comprises). Vide pour une ligne directe.
  List<City> get stops =>
      segments.skip(1).map((segment) => segment.origin).toList();

  @override
  List<Object?> get props => [
    id,
    origin,
    destination,
    duration,
    distanceKm,
    segments,
  ];
}
