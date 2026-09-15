import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/boarding_outcome.dart';
import '../models/boarding_dtos.dart';

/// Erreur métier renvoyée par `POST /ops/boarding` sous la forme
/// `{ "error": { "code": "qr_unknown", "message": "…" } }` — seul cas où
/// l'API répond hors de l'enveloppe `data` (voir `BoardingTicketUnknown
/// Exception`, 404) : les autres issues métier (annulé, mauvais départ,
/// déjà embarqué, conflit) sont des 200/409/422 **avec** `data`, gérées
/// directement par `BoardingDtos.outcome`, pas par cette exception.
class BoardingApiException implements Exception {
  const BoardingApiException(this.code, this.message);

  static const String qrUnknown = 'qr_unknown';

  final String code;
  final String message;

  @override
  String toString() => 'BoardingApiException($code): $message';
}

abstract class BoardingRemoteDataSource {
  Future<BoardingOutcome> scan({
    required String departureUuid,
    String? qrPayload,
    String? shortCode,
    required String operationUuid,
    required DateTime localRecordedAt,
  });
}

class BoardingRemoteDataSourceImpl implements BoardingRemoteDataSource {
  BoardingRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<BoardingOutcome> scan({
    required String departureUuid,
    String? qrPayload,
    String? shortCode,
    required String operationUuid,
    required DateTime localRecordedAt,
  }) async {
    try {
      final response = await _dio.post<dynamic>(
        ApiConstants.opsBoarding,
        data: <String, dynamic>{
          'departure_uuid': departureUuid,
          if (qrPayload != null) 'qr_payload': qrPayload,
          if (shortCode != null) 'short_code': shortCode,
          'operation_uuid': operationUuid,
          'local_recorded_at': localRecordedAt.toIso8601String(),
        },
        // 409 (conflit/déjà embarqué) et 422 (annulé/mauvais départ) sont
        // des réponses métier normales, avec un corps `data` exploitable
        // — pas des erreurs à faire remonter comme des DioException. Seul
        // 404 (`qr_unknown`, pas de `data` à parser) reste géré via
        // l'enveloppe `error` ci-dessous.
        options: Options(
          validateStatus: (int? status) =>
              status != null && status < 500 && status != 404,
        ),
      );

      return BoardingDtos.outcome(response.data);
    } on DioException catch (error) {
      final code = BoardingDtos.errorCode(error.response?.data);
      if (code == null) rethrow;

      throw BoardingApiException(
        code,
        BoardingDtos.errorMessage(error.response?.data) ?? 'Billet inconnu.',
      );
    }
  }
}
