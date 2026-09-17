import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';

/// Une opération prête à être envoyée dans un lot — forme générique
/// commune aux 3 types (`boarding.scan`, `seat_release`, `boarding.
/// no_show`, voir `SyncOperationType` côté backend). Construite par
/// `SyncRepositoryImpl` à partir de `PendingBoardingOperation`/
/// `PendingSeatReleaseOperation` — ce datasource ne connaît aucun des
/// deux types métier, seulement le format du lot.
class SyncWireOperation {
  const SyncWireOperation({
    required this.operationUuid,
    required this.operationType,
    required this.localRecordedAt,
    required this.payload,
  });

  final String operationUuid;
  final String operationType;
  final DateTime localRecordedAt;
  final Map<String, dynamic> payload;
}

/// Résultat brut d'une opération individuelle, tel que renvoyé par le
/// lot — miroir de `SyncBatchResource.operations[].result`, non encore
/// traduit en `BoardingOutcome`/`SeatReleaseOutcome` (voir
/// `SyncRepositoryImpl`, qui sait lequel des deux appliquer selon la
/// provenance de `operationUuid`).
class SyncOperationRawResult {
  const SyncOperationRawResult({
    required this.operationUuid,
    this.status,
    this.code,
    this.message,
    this.recommendedAction,
  });

  final String operationUuid;
  final String? status;
  final String? code;
  final String? message;
  final String? recommendedAction;
}

class SyncBatchFetchResult {
  const SyncBatchFetchResult(this.processedAt, this.results);

  final DateTime processedAt;
  final List<SyncOperationRawResult> results;
}

abstract class SyncRemoteDataSource {
  Future<SyncBatchFetchResult> submitBatch({
    required String batchUuid,
    required List<SyncWireOperation> operations,
  });
}

class SyncRemoteDataSourceImpl implements SyncRemoteDataSource {
  SyncRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<SyncBatchFetchResult> submitBatch({
    required String batchUuid,
    required List<SyncWireOperation> operations,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.opsSyncBatches,
      data: <String, dynamic>{
        'batch_uuid': batchUuid,
        'operations': operations
            .map((op) => <String, dynamic>{
                  'operation_uuid': op.operationUuid,
                  'operation_type': op.operationType,
                  'local_recorded_at': op.localRecordedAt.toIso8601String(),
                  'payload': op.payload,
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

  SyncOperationRawResult _resultOf(Map<String, dynamic> op) {
    final Object? rawResult = op['result'];
    final Map<String, dynamic> result =
        rawResult is Map ? Map<String, dynamic>.from(rawResult) : const {};

    return SyncOperationRawResult(
      operationUuid: '${op['operationUuid']}',
      status: result['status'] as String?,
      code: result['code'] as String?,
      message: result['message'] as String?,
      recommendedAction: result['recommendedAction'] as String?,
    );
  }
}
