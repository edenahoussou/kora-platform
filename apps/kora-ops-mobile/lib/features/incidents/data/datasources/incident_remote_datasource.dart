import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/incident.dart';

abstract class IncidentRemoteDataSource {
  /// `POST /ops/incidents` — idempotent via [operationUuid]
  /// (`incident_uuid`) : un rejeu après coupure renvoie l'incident déjà
  /// créé (200) sans doublon ni nouvelle alerte côté serveur.
  Future<void> report({
    required String operationUuid,
    required String departureUuid,
    required IncidentType type,
    required String description,
    required DateTime occurredAt,
    double? latitude,
    double? longitude,
  });
}

class IncidentRemoteDataSourceImpl implements IncidentRemoteDataSource {
  IncidentRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<void> report({
    required String operationUuid,
    required String departureUuid,
    required IncidentType type,
    required String description,
    required DateTime occurredAt,
    double? latitude,
    double? longitude,
  }) async {
    await _dio.post<dynamic>(
      ApiConstants.opsIncidents,
      data: <String, dynamic>{
        'incident_uuid': operationUuid,
        'departure_uuid': departureUuid,
        'type': type.name,
        'description': description,
        'occurred_at': occurredAt.toIso8601String(),
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
      },
    );
  }
}
