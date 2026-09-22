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
        // .toUtc() est essentiel : DateTime.now() est en heure locale de
        // l'appareil, et son .toIso8601String() ne porte ni « Z » ni
        // offset. Carbon côté serveur interprète alors cette chaîne comme
        // déjà en UTC — sur un appareil à UTC+1 (Bénin/Togo), l'heure
        // envoyée apparaît donc 1h dans le "futur", ce qui viole
        // `before_or_equal:+5 minutes` (422). .toUtc() ajoute le
        // suffixe « Z » et corrige l'instant envoyé.
        'occurred_at': occurredAt.toUtc().toIso8601String(),
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
      },
    );
  }
}
