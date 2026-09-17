import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/seat_release_outcome.dart';
import '../models/seat_release_dtos.dart';

/// Erreur métier renvoyée par les endpoints `ops/seat-releases/*` sous la
/// forme `{ "error": { "code": "ticket_unknown", ... } }` — seul cas où
/// l'API répond hors de l'enveloppe `data` (`ticket_unknown`, 404, un
/// billet ne pouvant tout simplement pas être résolu) — même convention
/// que `BoardingApiException`.
class SeatReleaseApiException implements Exception {
  const SeatReleaseApiException(this.code, this.message, this.recommendedAction);

  static const String ticketUnknown = 'ticket_unknown';

  final String code;
  final String message;
  final String? recommendedAction;

  @override
  String toString() => 'SeatReleaseApiException($code): $message';
}

abstract class SeatReleaseRemoteDataSource {
  Future<SeatReleaseOutcome> release({
    required String departureUuid,
    required String ticketUuid,
    required String actualStopUuid,
    required String operationUuid,
    required DateTime localRecordedAt,
  });

  Future<List<SeatReleaseOutcome>> closeNoShows({
    required String departureUuid,
    required String operationUuid,
    required DateTime localRecordedAt,
  });
}

class SeatReleaseRemoteDataSourceImpl implements SeatReleaseRemoteDataSource {
  SeatReleaseRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<SeatReleaseOutcome> release({
    required String departureUuid,
    required String ticketUuid,
    required String actualStopUuid,
    required String operationUuid,
    required DateTime localRecordedAt,
  }) async {
    try {
      final response = await _dio.post<dynamic>(
        ApiConstants.opsSeatReleases,
        data: <String, dynamic>{
          'departure_uuid': departureUuid,
          'ticket_uuid': ticketUuid,
          'actual_stop_uuid': actualStopUuid,
          'operation_uuid': operationUuid,
          'local_recorded_at': localRecordedAt.toIso8601String(),
        },
        // 409 (conflit/déjà libéré) et 422 (arrêt incompatible, annulé…)
        // sont des réponses métier normales avec un corps `data`
        // exploitable — seul 404 (`ticket_unknown`) reste géré via
        // l'enveloppe `error` ci-dessous.
        options: Options(
          validateStatus: (int? status) =>
              status != null && status < 500 && status != 404,
        ),
      );
      return SeatReleaseDtos.single(response.data);
    } on DioException catch (error) {
      final code = SeatReleaseDtos.errorCode(error.response?.data);
      if (code == null) rethrow;

      throw SeatReleaseApiException(
        code,
        SeatReleaseDtos.errorMessage(error.response?.data) ?? 'Billet inconnu.',
        SeatReleaseDtos.errorRecommendedAction(error.response?.data),
      );
    }
  }

  @override
  Future<List<SeatReleaseOutcome>> closeNoShows({
    required String departureUuid,
    required String operationUuid,
    required DateTime localRecordedAt,
  }) async {
    try {
      final response = await _dio.post<dynamic>(
        ApiConstants.opsSeatReleasesNoShowClosure,
        data: <String, dynamic>{
          'departure_uuid': departureUuid,
          'operation_uuid': operationUuid,
          'local_recorded_at': localRecordedAt.toIso8601String(),
        },
      );
      return SeatReleaseDtos.list(response.data);
    } on DioException catch (error) {
      final code = SeatReleaseDtos.errorCode(error.response?.data);
      if (code == null) rethrow;

      throw SeatReleaseApiException(
        code,
        SeatReleaseDtos.errorMessage(error.response?.data) ?? 'Billet inconnu.',
        SeatReleaseDtos.errorRecommendedAction(error.response?.data),
      );
    }
  }
}
