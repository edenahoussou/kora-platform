import '../../domain/entities/trip_log.dart';

/// (Dé)sérialisation du carnet de bord — contrat
/// `OpsTripLogController`/`TripLogReadService` côté backend, et format de
/// la file locale (`trip_log_operations`).
abstract final class TripLogDtos {
  /// Corps `{ data: {...} }` → `data`, sinon le corps lui-même.
  static Map<String, dynamic> unwrap(Object? body) {
    if (body is Map) {
      final Object? data = body['data'];
      if (data is Map) return Map<String, dynamic>.from(data);
      return Map<String, dynamic>.from(body);
    }
    return <String, dynamic>{};
  }

  static TripLog tripLog(Map<String, dynamic> json) {
    final Map<String, dynamic> odometer = _map(json['odometer']);
    final Map<String, dynamic> bus = _map(json['bus']);

    return TripLog(
      departureUuid: '${json['departure_uuid'] ?? ''}',
      busRegistration: bus['registration_number'] as String?,
      lastKnownOdometerKm: _int(json['last_known_odometer_km']),
      start: _reading(OdometerKind.start, odometer['start']),
      end: _reading(OdometerKind.end, odometer['end']),
      expenses: (json['expenses'] is List ? json['expenses'] as List : const [])
          .whereType<Map>()
          .map((Map raw) => expense(Map<String, dynamic>.from(raw)))
          .toList(),
    );
  }

  static TripExpense expense(Map<String, dynamic> json) {
    return TripExpense(
      clientUuid: '${json['client_uuid'] ?? json['uuid'] ?? ''}',
      reference: json['reference'] as String?,
      kind: TripExpenseKind.fromValue(json['kind'] as String?),
      amount: _int(json['amount']) ?? 0,
      liters: _double(json['liters']),
      unitPrice: _int(json['unit_price']),
      stationLabel: json['station_label'] as String?,
      checkpointLabel: json['checkpoint_label'] as String?,
      countryCode: json['country_code'] as String?,
      odometerKm: _int(json['odometer_km']),
      paymentChannel:
          TripPaymentChannel.fromValue(json['payment_channel'] as String?),
      occurredAt: _date(json['occurred_at']) ?? DateTime.now(),
      status: json['status'] as String? ?? 'pending',
      rejectionReason: json['rejection_reason'] as String?,
      hasPhoto: json['has_photo'] == true,
      submittedBy: json['submitted_by'] as String?,
    );
  }

  /// Payload d'une dépense en file locale (toutes les infos du brouillon,
  /// photo exclue — son chemin vit dans sa propre colonne).
  static Map<String, dynamic> draftToJson(TripExpenseDraft draft) =>
      <String, dynamic>{
        'client_uuid': draft.clientUuid,
        'departure_uuid': draft.departureUuid,
        'kind': draft.kind.value,
        'occurred_at': draft.occurredAt.toIso8601String(),
        'amount': draft.amount,
        'liters': draft.liters,
        'unit_price': draft.unitPrice,
        'station_label': draft.stationLabel,
        'checkpoint_label': draft.checkpointLabel,
        'country_code': draft.countryCode,
        'odometer_km': draft.odometerKm,
        'payment_channel': draft.paymentChannel.value,
        'note': draft.note,
      };

  static TripExpenseDraft draftFromJson(
    Map<String, dynamic> json, {
    String? photoPath,
  }) {
    return TripExpenseDraft(
      clientUuid: '${json['client_uuid']}',
      departureUuid: '${json['departure_uuid']}',
      kind: TripExpenseKind.fromValue(json['kind'] as String?),
      occurredAt: _date(json['occurred_at']) ?? DateTime.now(),
      amount: _int(json['amount']),
      liters: _double(json['liters']),
      unitPrice: _int(json['unit_price']),
      stationLabel: json['station_label'] as String?,
      checkpointLabel: json['checkpoint_label'] as String?,
      countryCode: json['country_code'] as String?,
      odometerKm: _int(json['odometer_km']),
      paymentChannel:
          TripPaymentChannel.fromValue(json['payment_channel'] as String?),
      note: json['note'] as String?,
      photoPath: photoPath,
    );
  }

  static OdometerReading? _reading(OdometerKind kind, Object? raw) {
    if (raw is! Map) return null;
    final int? value = _int(raw['value_km']);
    if (value == null) return null;
    return OdometerReading(
      kind: kind,
      valueKm: value,
      recordedAt: _date(raw['recorded_at']) ?? DateTime.now(),
      recordedBy: raw['recorded_by'] as String?,
    );
  }

  static Map<String, dynamic> _map(Object? raw) =>
      raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};

  static int? _int(Object? raw) => switch (raw) {
        final int value => value,
        final num value => value.toInt(),
        final String value => int.tryParse(value),
        _ => null,
      };

  static double? _double(Object? raw) => switch (raw) {
        final num value => value.toDouble(),
        final String value => double.tryParse(value),
        _ => null,
      };

  static DateTime? _date(Object? raw) =>
      raw is String ? DateTime.tryParse(raw)?.toLocal() : null;
}
