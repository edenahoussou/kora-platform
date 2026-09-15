import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../boarding/domain/entities/boarding_outcome.dart';
import '../../../boarding/domain/entities/boarding_outcome_status.dart';
import '../../../boarding/domain/entities/pending_boarding_operation.dart';

/// Résultat d'une opération individuelle telle que renvoyée par le lot —
/// miroir de `SyncBatchResource.operations[]`.
class SyncOperationResult {
  const SyncOperationResult(this.operationUuid, this.outcome);

  final String operationUuid;
  final BoardingOutcome outcome;
}

class SyncBatchFetchResult {
  const SyncBatchFetchResult(this.processedAt, this.results);

  final DateTime processedAt;
  final List<SyncOperationResult> results;
}

abstract class SyncRemoteDataSource {
  Future<SyncBatchFetchResult> submitBatch({
    required String batchUuid,
    required List<PendingBoardingOperation> operations,
  });
}

class SyncRemoteDataSourceImpl implements SyncRemoteDataSource {
  SyncRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<SyncBatchFetchResult> submitBatch({
    required String batchUuid,
    required List<PendingBoardingOperation> operations,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.opsSyncBatches,
      data: <String, dynamic>{
        'batch_uuid': batchUuid,
        'operations': operations
            .map((op) => <String, dynamic>{
                  'operation_uuid': op.operationUuid,
                  'operation_type': 'boarding.scan',
                  'local_recorded_at': op.localRecordedAt.toIso8601String(),
                  'payload': <String, dynamic>{
                    'departure_uuid': op.departureUuid,
                    if (op.qrPayload != null) 'qr_payload': op.qrPayload,
                    if (op.shortCode != null) 'short_code': op.shortCode,
                  },
                })
            .toList(),
      },
    );

    final Map<String, dynamic> data = _unwrap(response.data);
    final List<dynamic> rawOperations =
        (data['operations'] as List<dynamic>?) ?? const <dynamic>[];

    return SyncBatchFetchResult(
      DateTime.tryParse('${data['processedAt']}') ?? DateTime.now(),
      rawOperations
          .whereType<Map<dynamic, dynamic>>()
          .map((raw) => _resultOf(Map<String, dynamic>.from(raw)))
          .toList(),
    );
  }

  Map<String, dynamic> _unwrap(Object? body) {
    if (body is! Map) return const <String, dynamic>{};
    final data = body['data'];
    if (data is Map) return Map<String, dynamic>.from(data);
    return Map<String, dynamic>.from(body);
  }

  SyncOperationResult _resultOf(Map<String, dynamic> op) {
    final Object? rawResult = op['result'];
    final Map<String, dynamic> result =
        rawResult is Map ? Map<String, dynamic>.from(rawResult) : const {};
    final String? status = result['status'] as String?;
    final String? code = result['code'] as String?;
    final String? message = result['message'] as String?;

    final BoardingOutcomeStatus outcomeStatus = switch (status) {
      'accepted' => BoardingOutcomeStatus.valid,
      'conflict' => BoardingOutcomeStatus.conflict,
      'pending' => BoardingOutcomeStatus.pendingSync,
      _ => switch (code) {
          'ticket_cancelled' => BoardingOutcomeStatus.cancelled,
          'wrong_departure' ||
          'leg_not_active' =>
            BoardingOutcomeStatus.wrongDeparture,
          'ticket_already_boarded' => BoardingOutcomeStatus.alreadyBoarded,
          _ => BoardingOutcomeStatus.error,
        },
    };

    return SyncOperationResult(
      '${op['operationUuid']}',
      BoardingOutcome(
        status: outcomeStatus,
        message: message ?? outcomeStatus.defaultMessage,
        // `entityUuid` est l'identifiant de l'événement d'embarquement créé
        // (`entityType: boarding_event`), pas le `ticketUuid` — le lot ne
        // le renvoie pas (voir SyncBatchResource). Le manifeste local est
        // donc rafraîchi en bloc après synchro plutôt que patché par
        // billet — voir `SyncCubit.syncNow`.
        ticketUuid: null,
        operationUuid: '${op['operationUuid']}',
      ),
    );
  }
}
