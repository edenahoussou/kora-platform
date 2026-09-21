import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/depart.dart';
import '../models/departure_dtos.dart';

abstract class DepartureRemoteDataSource {
  /// `GET /ops/departures` — départs affectés à l'utilisateur connecté
  /// (voir `CrewDepartureScope` côté backend, filtrage déjà fait côté
  /// serveur). [date] au format `YYYY-MM-DD`.
  Future<List<Depart>> list({String? date, String? status});

  /// `GET /ops/departures/{uuid}` — détail complet (arrêts, équipage,
  /// `manifestVersion`).
  Future<Depart> show(String departureUuid);

  /// `POST /ops/departures/{uuid}/status` — voir
  /// `DepartureRepository.updateStatus`. Retourne le statut confirmé par
  /// le serveur (pas un `Depart` complet : la réponse de cet endpoint ne
  /// porte que le statut/les horodatages, pas les arrêts/l'équipage).
  Future<String> updateStatus(
    String departureUuid,
    String status, {
    DateTime? occurredAt,
  });

  /// `PATCH /ops/departures/{uuid}/delay` — enregistre un retard (`0` pour
  /// l'annuler). Réservé au départ programmé ou en embarquement (422
  /// `invalid_delay` sinon, voir `RecordDepartureDelayAction`).
  Future<void> recordDelay(
    String departureUuid,
    int delayMinutes,
    String delayReason,
  );
}

class DepartureRemoteDataSourceImpl implements DepartureRemoteDataSource {
  DepartureRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<List<Depart>> list({String? date, String? status}) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.opsDepartures,
      queryParameters: <String, dynamic>{
        'per_page': 200,
        if (date != null) 'date': date,
        if (status != null) 'status': status,
      },
    );
    return DepartureDtos.list(response.data);
  }

  @override
  Future<Depart> show(String departureUuid) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.opsDepartureShow(departureUuid),
    );
    return DepartureDtos.fromShow(response.data);
  }

  @override
  Future<String> updateStatus(
    String departureUuid,
    String status, {
    DateTime? occurredAt,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.opsDepartureStatus(departureUuid),
      data: <String, dynamic>{
        'status': status,
        if (occurredAt != null) 'occurred_at': occurredAt.toIso8601String(),
      },
    );
    final Object? body = response.data;
    final Object? data = body is Map ? body['data'] : null;
    final Map<dynamic, dynamic> map = data is Map ? data : const {};
    return map['status'] as String? ?? status;
  }

  @override
  Future<void> recordDelay(
    String departureUuid,
    int delayMinutes,
    String delayReason,
  ) async {
    await _dio.patch<dynamic>(
      ApiConstants.opsDepartureDelay(departureUuid),
      data: <String, dynamic>{
        'delay_minutes': delayMinutes,
        'delay_reason': delayReason,
      },
    );
  }
}
