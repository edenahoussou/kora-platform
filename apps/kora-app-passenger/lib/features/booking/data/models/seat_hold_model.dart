import '../../../../core/network/api_json.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/seat_hold.dart';
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
    // Deux endpoints rendent cette même réservation, et ne nomment pas les
    // sièges au même endroit : `segments` pour le blocage, `passengers`
    // une fois les identités enregistrées. On lit les deux plutôt que
    // d'écrire un second modèle pour une différence de clé.
    final seen = <String>{};
    final codes = <String>[];
    for (final source in [json['segments'], json['passengers']]) {
      for (final entry in ApiJson.list(source)) {
        final code = ApiJson.string(entry['seat_code']);
        if (code.isNotEmpty && seen.add(code)) codes.add(code);
      }
    }

    return SeatHoldModel(
      uuid: ApiJson.string(json['uuid']),
      expiresAt: ApiJson.stringOrNull(json['expires_at']),
      totalAmount: ApiJson.intOrNull(json['total_amount']) ?? 0,
      currency: ApiJson.string(json['currency'], 'XOF'),
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
