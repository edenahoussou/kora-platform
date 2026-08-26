import '../../domain/entities/price.dart';
import '../../domain/entities/seat_hold.dart';
import 'bus_json.dart';
import 'quote_model.dart';

/// Couche DATA : la réservation `held` renvoyée par `POST bus/holds`.
///
/// ```json
/// {
///   "uuid": "9b1deb4d-…",
///   "status": "held",
///   "expires_at": "2026-08-20T08:15:00Z",
///   "total_amount": 12500,
///   "currency": "XOF",
///   "segments": [
///     { "seat_uuid": "…", "seat_code": "4A", "status": "held" }
///   ]
/// }
/// ```
///
/// Un segment par siège **et par tronçon** : le même siège réservé de
/// Cotonou à Malanville peut arriver en plusieurs lignes si la liaison est
/// découpée. D'où la déduplication des numéros — sans elle, le voyageur
/// lirait « 4A, 4A » sous le plan.
class SeatHoldModel {
  const SeatHoldModel({
    required this.uuid,
    required this.expiresAt,
    required this.totalAmount,
    required this.currency,
    required this.seatCodes,
  });

  factory SeatHoldModel.fromJson(Map<String, dynamic> json) {
    final seen = <String>{};
    final codes = <String>[];
    for (final segment in BusJson.list(json['segments'])) {
      final code = BusJson.string(segment['seat_code']);
      if (code.isNotEmpty && seen.add(code)) codes.add(code);
    }

    return SeatHoldModel(
      uuid: BusJson.string(json['uuid']),
      expiresAt: BusJson.stringOrNull(json['expires_at']),
      totalAmount: BusJson.intOrNull(json['total_amount']) ?? 0,
      currency: BusJson.string(json['currency'], 'XOF'),
      seatCodes: codes,
    );
  }

  final String uuid;
  final String? expiresAt;
  final int totalAmount;
  final String currency;
  final List<String> seatCodes;

  SeatHold toEntity() {
    return SeatHold(
      id: uuid,
      expiresAt: parseExpiry(expiresAt),
      seatLabels: seatCodes,
      total: Price(
        totalAmount,
        currency: currency.toUpperCase() == 'XOF' ? 'FCFA' : currency,
      ),
    );
  }
}
