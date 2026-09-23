import 'package:equatable/equatable.dart';

import '../entities/tracking_position_source.dart';

/// Une mesure brute renvoyée par [LocationProvider] — pas encore un
/// [TrackingPosition] (pas d'UUID ni de séquence, attribués par
/// `TrackingTaskHandler` au moment de l'enfilage).
class LocationFix extends Equatable {
  const LocationFix({
    required this.latitude,
    required this.longitude,
    required this.accuracyMeters,
    required this.timestamp,
    required this.source,
    this.speedKmh,
    this.headingDegrees,
  });

  final double latitude;
  final double longitude;
  final double accuracyMeters;
  final DateTime timestamp;
  final TrackingPositionSource source;

  /// En km/h — converti depuis les m/s renvoyés par la plateforme (voir
  /// `GeolocatorLocationProvider._fixOf`).
  final double? speedKmh;
  final double? headingDegrees;

  @override
  List<Object?> get props => <Object?>[
        latitude,
        longitude,
        accuracyMeters,
        timestamp,
        source,
        speedKmh,
        headingDegrees,
      ];
}
