import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/tracking_position.dart';

/// Réponse brute du lot — miroir de `IngestTrackingPositionsBatchAction`
/// côté backend (MEZ-100). `rejectedUuids` : positions refusées
/// définitivement (horodatage impossible) — à marquer synchronisées côté
/// mobile pour ne plus les réessayer (voir docstring
/// `TrackingLocalDataSource.markSynced`), distinctement de celles restées
/// en file après une coupure réseau totale (aucune réponse reçue).
class TrackingBatchResult {
  const TrackingBatchResult({
    required this.sessionUuid,
    required this.accepted,
    required this.duplicate,
    required this.rejectedUuids,
  });

  final String sessionUuid;
  final int accepted;
  final int duplicate;
  final List<String> rejectedUuids;
}

abstract class TrackingRemoteDataSource {
  /// `POST /ops/tracking/positions/batch` — idempotent par
  /// `position_uuid` : un rejeu après coupure renvoie les points déjà
  /// acceptés en `duplicate` sans les recompter ni créer de doublon côté
  /// serveur.
  Future<TrackingBatchResult> sendBatch({
    required String departureUuid,
    required List<TrackingPosition> positions,
  });
}

class TrackingRemoteDataSourceImpl implements TrackingRemoteDataSource {
  TrackingRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<TrackingBatchResult> sendBatch({
    required String departureUuid,
    required List<TrackingPosition> positions,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.opsTrackingPositionsBatch,
      data: <String, dynamic>{
        'departure_uuid': departureUuid,
        'positions': positions.map(_wireOf).toList(),
      },
    );

    final Map<String, dynamic> data = _unwrap(response.data);
    final List<dynamic> rawRejected =
        (data['rejected_details'] as List<dynamic>?) ?? const <dynamic>[];

    return TrackingBatchResult(
      sessionUuid: '${data['session_uuid'] ?? ''}',
      accepted: (data['accepted'] as num?)?.toInt() ?? 0,
      duplicate: (data['duplicate'] as num?)?.toInt() ?? 0,
      rejectedUuids: rawRejected
          .whereType<Map<dynamic, dynamic>>()
          .map((raw) => '${raw['uuid']}')
          .toList(),
    );
  }

  Map<String, dynamic> _wireOf(TrackingPosition position) => <String, dynamic>{
        'position_uuid': position.positionUuid,
        'sequence': position.sequence,
        // .toUtc() est essentiel — même piège que pour les incidents
        // (voir `IncidentRemoteDataSourceImpl.report`) : DateTime.now()
        // est en heure locale de l'appareil, .toIso8601String() seul ne
        // porte ni « Z » ni offset, ce que Carbon interprète à tort comme
        // déjà en UTC côté serveur.
        'recorded_at': position.recordedAt.toUtc().toIso8601String(),
        'status': position.status.value,
        'source': position.source.name,
        if (position.latitude != null) 'latitude': position.latitude,
        if (position.longitude != null) 'longitude': position.longitude,
        if (position.accuracyMeters != null)
          'accuracy_meters': position.accuracyMeters,
        if (position.speedKmh != null) 'speed_kmh': position.speedKmh,
        if (position.headingDegrees != null)
          'heading_degrees': position.headingDegrees,
      };

  Map<String, dynamic> _unwrap(Object? body) {
    if (body is! Map) return const <String, dynamic>{};
    final Object? data = body['data'];
    if (data is Map) return Map<String, dynamic>.from(data);
    return Map<String, dynamic>.from(body);
  }
}
