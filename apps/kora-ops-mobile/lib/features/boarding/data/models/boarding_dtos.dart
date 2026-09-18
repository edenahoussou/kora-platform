import '../../domain/entities/boarding_outcome.dart';
import '../../domain/entities/boarding_outcome_status.dart';

/// Couche DATA : lecture des réponses JSON de `POST /ops/boarding` —
/// miroir de `BoardingEventResource` côté backend.
abstract final class BoardingDtos {
  static Map<String, dynamic> unwrap(Object? body) {
    if (body is! Map) return const <String, dynamic>{};

    final data = body['data'];
    if (data is Map) return Map<String, dynamic>.from(data);

    return Map<String, dynamic>.from(body);
  }

  /// `status` (`accepted`/`rejected`/`conflict`/`pending`) ne suffit pas à
  /// lui seul : `rejected` recouvre `ticket_cancelled`/`wrong_departure`/
  /// `leg_not_active`/`ticket_already_boarded` (voir
  /// `ProcessBoardingScanAction`) — c'est `code` qui distingue.
  static BoardingOutcome outcome(Object? body) {
    final data = unwrap(body);
    final String? code = data['code'] as String?;
    final String? message = data['message'] as String?;

    return BoardingOutcome(
      status: _statusOf(data['status'] as String?, code),
      message:
          message ?? _statusOf(data['status'] as String?, code).defaultMessage,
      ticketUuid: data['ticketUuid'] as String?,
      operationUuid: data['operationUuid'] as String?,
    );
  }

  static BoardingOutcomeStatus _statusOf(String? status, String? code) {
    if (status == 'accepted') return BoardingOutcomeStatus.valid;
    if (status == 'conflict') return BoardingOutcomeStatus.conflict;

    // `rejected` : le code métier distingue le motif.
    return switch (code) {
      'ticket_cancelled' => BoardingOutcomeStatus.cancelled,
      'wrong_departure' ||
      'leg_not_active' =>
        BoardingOutcomeStatus.wrongDeparture,
      'ticket_already_boarded' => BoardingOutcomeStatus.alreadyBoarded,
      _ => BoardingOutcomeStatus.error,
    };
  }

  /// Code métier d'une erreur hors enveloppe `data` (`qr_unknown`, 404),
  /// dans `{ "error": { "code" } }` — même convention que les autres DTOs.
  static String? errorCode(Object? body) {
    if (body is! Map) return null;

    final error = body['error'];
    if (error is! Map) return null;

    final code = error['code'];
    return code is String ? code : null;
  }

  static String? errorMessage(Object? body) {
    if (body is! Map) return null;

    final error = body['error'];
    if (error is Map && error['message'] is String) {
      return error['message'] as String;
    }

    return null;
  }
}
