import 'package:equatable/equatable.dart';

import 'tracking_position_source.dart';
import 'tracking_position_status.dart';

/// Une position (ou un heartbeat `gpsUnavailable`) mesurée par le
/// convoyeur — en file locale ou déjà envoyée (MEZ-101). Voir
/// `IngestTrackingPositionsRequest` côté backend pour le contrat exact.
class TrackingPosition extends Equatable {
  const TrackingPosition({
    required this.positionUuid,
    required this.departureUuid,
    required this.sequence,
    required this.status,
    required this.source,
    required this.recordedAt,
    required this.pendingSync,
    this.latitude,
    this.longitude,
    this.accuracyMeters,
    this.speedKmh,
    this.headingDegrees,
  });

  /// Généré côté mobile — clé d'idempotence (`position_uuid` côté
  /// backend), y compris entre deux tentatives après une coupure réseau.
  final String positionUuid;
  final String departureUuid;

  /// Compteur strictement croissant par session de suivi (pas un
  /// timestamp) — voir `tracking_session_state.last_sequence`. Permet au
  /// serveur de ne jamais laisser un point en retard régresser l'état
  /// affiché, même si les lots arrivent dans le désordre.
  final int sequence;

  final TrackingPositionStatus status;
  final TrackingPositionSource source;

  /// Heure locale de la mesure (pas de l'envoi) — voir docstring
  /// `TrackingRemoteDataSource.sendBatch` sur l'UTC.
  final DateTime recordedAt;

  final double? latitude;
  final double? longitude;
  final double? accuracyMeters;
  final double? speedKmh;
  final double? headingDegrees;

  /// `true` tant que la position n'a pas été confirmée par le serveur —
  /// même convention que `Incident.pendingSync`.
  final bool pendingSync;

  @override
  List<Object?> get props => <Object?>[
        positionUuid,
        departureUuid,
        sequence,
        status,
        source,
        recordedAt,
        latitude,
        longitude,
        accuracyMeters,
        speedKmh,
        headingDegrees,
        pendingSync,
      ];
}
