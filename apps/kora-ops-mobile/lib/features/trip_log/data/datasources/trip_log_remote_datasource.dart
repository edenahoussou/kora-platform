import 'package:dio/dio.dart';
import 'package:path/path.dart' as p;

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/trip_log.dart';
import '../models/trip_log_dtos.dart';

/// Couche DATA : carnet de bord côté serveur (`OpsTripLogController`).
/// Tout est idempotent : `client_uuid` pour les dépenses, et un relevé
/// compteur unique par (départ, type) qu'une nouvelle saisie corrige —
/// un rejeu après coupure ne crée jamais de doublon.
abstract class TripLogRemoteDataSource {
  /// `GET ops/departures/{uuid}/trip-log` → objet `data` brut (mis en
  /// cache tel quel).
  Future<Map<String, dynamic>> fetch(String departureUuid);

  /// `PUT ops/departures/{uuid}/odometer` → carnet mis à jour (`data`).
  Future<Map<String, dynamic>> recordOdometer({
    required String departureUuid,
    required OdometerKind kind,
    required int valueKm,
    required DateTime recordedAt,
    required String clientUuid,
  });

  /// `POST ops/departures/{uuid}/trip-expenses` (multipart si photo) →
  /// dépense créée (`data`).
  Future<Map<String, dynamic>> submitExpense(TripExpenseDraft draft);
}

class TripLogRemoteDataSourceImpl implements TripLogRemoteDataSource {
  TripLogRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<Map<String, dynamic>> fetch(String departureUuid) async {
    final Response<dynamic> response = await _dio
        .get<dynamic>(ApiConstants.opsDepartureTripLog(departureUuid));
    return TripLogDtos.unwrap(response.data);
  }

  @override
  Future<Map<String, dynamic>> recordOdometer({
    required String departureUuid,
    required OdometerKind kind,
    required int valueKm,
    required DateTime recordedAt,
    required String clientUuid,
  }) async {
    final Response<dynamic> response = await _dio.put<dynamic>(
      ApiConstants.opsDepartureOdometer(departureUuid),
      data: <String, dynamic>{
        'kind': kind.value,
        'value_km': valueKm,
        'client_uuid': clientUuid,
        // .toUtc() : voir IncidentRemoteDataSourceImpl.report (sinon une
        // heure locale UTC+1 viole `before_or_equal:+5 minutes`).
        'recorded_at': recordedAt.toUtc().toIso8601String(),
      },
    );
    return TripLogDtos.unwrap(response.data);
  }

  @override
  Future<Map<String, dynamic>> submitExpense(TripExpenseDraft draft) async {
    final Map<String, dynamic> fields = <String, dynamic>{
      'client_uuid': draft.clientUuid,
      'kind': draft.kind.value,
      'occurred_at': draft.occurredAt.toUtc().toIso8601String(),
      'payment_channel': draft.paymentChannel.value,
      if (draft.amount != null) 'amount': '${draft.amount}',
      if (draft.liters != null) 'liters': draft.liters!.toStringAsFixed(2),
      if (draft.unitPrice != null) 'unit_price': '${draft.unitPrice}',
      if (_filled(draft.stationLabel)) 'station_label': draft.stationLabel,
      if (_filled(draft.checkpointLabel))
        'checkpoint_label': draft.checkpointLabel,
      if (_filled(draft.countryCode)) 'country_code': draft.countryCode,
      if (draft.odometerKm != null) 'odometer_km': '${draft.odometerKm}',
      if (_filled(draft.note)) 'note': draft.note,
    };

    final String? photoPath = draft.photoPath;
    if (photoPath != null) {
      fields['photo'] = await MultipartFile.fromFile(
        photoPath,
        filename: p.basename(photoPath),
      );
    }

    final Response<dynamic> response = await _dio.post<dynamic>(
      ApiConstants.opsDepartureTripExpenses(draft.departureUuid),
      data: FormData.fromMap(fields),
    );
    return TripLogDtos.unwrap(response.data);
  }

  bool _filled(String? value) => value != null && value.trim().isNotEmpty;
}
