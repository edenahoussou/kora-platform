import '../../domain/entities/seat_release_outcome.dart';
import '../../domain/entities/seat_release_outcome_status.dart';

/// Couche DATA : lecture des réponses JSON de `POST /ops/seat-releases`
/// et `POST /ops/seat-releases/no-show-closure` — miroir de
/// `SeatReleaseEventResource` côté backend.
abstract final class SeatReleaseDtos {
  static Map<String, dynamic> unwrap(Object? body) {
    if (body is! Map) return const <String, dynamic>{};
    final data = body['data'];
    if (data is Map) return Map<String, dynamic>.from(data);
    return Map<String, dynamic>.from(body);
  }

  static SeatReleaseOutcome outcome(Map<String, dynamic> row) {
    final SeatReleaseOutcomeStatus status = _statusOf(row['status'] as String?);
    return SeatReleaseOutcome(
      status: status,
      message: row['message'] as String? ?? status.defaultMessage,
      recommendedAction: row['recommendedAction'] as String?,
      ticketUuid: row['ticketUuid'] as String?,
      operationUuid: row['operationUuid'] as String?,
    );
  }

  /// `POST /ops/seat-releases` — un seul objet dans `data`.
  static SeatReleaseOutcome single(Object? body) => outcome(unwrap(body));

  /// `POST /ops/seat-releases/no-show-closure` — un tableau dans `data`,
  /// un objet par billet clôturé.
  static List<SeatReleaseOutcome> list(Object? body) {
    if (body is! Map) return const <SeatReleaseOutcome>[];
    final rows = body['data'];
    if (rows is! List) return const <SeatReleaseOutcome>[];

    return rows
        .whereType<Map<dynamic, dynamic>>()
        .map((row) => outcome(Map<String, dynamic>.from(row)))
        .toList();
  }

  static SeatReleaseOutcomeStatus _statusOf(String? status) => switch (status) {
        'accepted' => SeatReleaseOutcomeStatus.accepted,
        'conflict' => SeatReleaseOutcomeStatus.conflict,
        'pending' => SeatReleaseOutcomeStatus.pendingSync,
        'rejected' => SeatReleaseOutcomeStatus.rejected,
        _ => SeatReleaseOutcomeStatus.error,
      };

  /// Code métier d'une erreur hors enveloppe `data` (`ticket_unknown`,
  /// 404) — même convention que les autres DTOs.
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

  static String? errorRecommendedAction(Object? body) {
    if (body is! Map) return null;
    final error = body['error'];
    if (error is Map && error['recommendedAction'] is String) {
      return error['recommendedAction'] as String;
    }
    return null;
  }
}
