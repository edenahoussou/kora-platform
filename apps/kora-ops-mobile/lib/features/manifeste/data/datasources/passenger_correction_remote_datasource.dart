import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';

/// Refus définitif du serveur (validation 422, droit 403, billet ou départ
/// introuvable 404) : inutile de réessayer tel quel, [message] est
/// affichable au convoyeur.
class PassengerCorrectionRejectedException implements Exception {
  const PassengerCorrectionRejectedException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => 'PassengerCorrectionRejectedException($statusCode)';
}

abstract class PassengerCorrectionRemoteDataSource {
  /// `PATCH /ops/departures/{departure}/tickets/{ticket}/passenger`.
  /// Lève [PassengerCorrectionRejectedException] pour un refus définitif ;
  /// laisse remonter la `DioException` pour tout le reste (réseau, 5xx).
  Future<void> send({
    required String departureUuid,
    required String ticketUuid,
    required Map<String, Object?> changes,
    String? reason,
  });
}

class PassengerCorrectionRemoteDataSourceImpl
    implements PassengerCorrectionRemoteDataSource {
  PassengerCorrectionRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<void> send({
    required String departureUuid,
    required String ticketUuid,
    required Map<String, Object?> changes,
    String? reason,
  }) async {
    try {
      await _dio.patch<dynamic>(
        ApiConstants.opsPassengerCorrection(departureUuid, ticketUuid),
        data: <String, Object?>{
          ...changes,
          if (reason != null && reason.trim().isNotEmpty) 'reason': reason,
        },
      );
    } on DioException catch (error) {
      final int? status = error.response?.statusCode;
      if (status == 403 || status == 404 || status == 422) {
        throw PassengerCorrectionRejectedException(
          _messageOf(error.response?.data, status),
          statusCode: status,
        );
      }
      rethrow;
    }
  }

  /// Message lisible : `error.message` (contrat OPS), sinon première erreur
  /// de validation Laravel (`errors`), sinon `message`, sinon un défaut.
  String _messageOf(Object? body, int? status) {
    if (body is Map) {
      final Object? error = body['error'];
      if (error is Map && error['message'] is String) {
        return error['message'] as String;
      }
      final Object? errors = body['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final Object? first = errors.values.first;
        if (first is List && first.isNotEmpty) return '${first.first}';
        if (first is String) return first;
      }
      if (body['message'] is String) return body['message'] as String;
    }
    return switch (status) {
      403 => 'Vous n\'êtes pas autorisé à corriger ce passager.',
      404 => 'Passager ou départ introuvable.',
      _ => 'Informations refusées par le serveur.',
    };
  }
}
