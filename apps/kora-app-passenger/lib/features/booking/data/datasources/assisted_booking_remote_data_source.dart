import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/assisted_booking.dart';
import '../../domain/entities/seat_hold.dart';
import '../models/assisted_booking_model.dart';

/// Couche DATA : la réservation assistée (MEZ-80).
///
/// Tous ces endpoints exigent une session. Comme les autres data sources,
/// celui-ci laisse remonter les `DioException` : c'est le repository qui
/// les traduit en `Failure`.
class AssistedBookingRemoteDataSource {
  const AssistedBookingRemoteDataSource(this._dio);

  final Dio _dio;

  Future<AssistedBooking> request({
    required String reservationId,
    required AssistanceChannel channel,
    required String contactName,
    required String contactPhone,
    required String idempotencyKey,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.bookingAssistance(reservationId),
      data: {
        'channel': channel.wire,
        'contact_name': contactName,
        'contact_phone': contactPhone,
        'idempotency_key': idempotencyKey,
      },
    );

    return assistedBookingFromJson(ApiJson.map(_payload(response.data)));
  }

  Future<AssistedBooking> fetch(String reservationId) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.bookingAssistance(reservationId),
    );

    return assistedBookingFromJson(ApiJson.map(_payload(response.data)));
  }

  /// Chaque voyageur retrouve ses sièges, tronçon par tronçon : la forme de
  /// `seat_assignments` est celle du blocage d'itinéraire.
  Future<SeatHold> resumeOnline({
    required String reservationId,
    required List<AssistanceSeat> seats,
    required String idempotencyKey,
  }) async {
    final byPassenger = <int, List<AssistanceSeat>>{};
    for (final seat in seats) {
      byPassenger.putIfAbsent(seat.passengerIndex, () => []).add(seat);
    }
    final indexes = byPassenger.keys.toList()..sort();

    final response = await _dio.post<dynamic>(
      ApiConstants.bookingAssistanceResume(reservationId),
      data: {
        'idempotency_key': idempotencyKey,
        'passengers': [
          for (final index in indexes)
            {
              'passenger_index': index,
              'seat_assignments': [
                for (final seat in byPassenger[index]!)
                  {'leg_order': seat.legOrder, 'seat_uuid': seat.seatId},
              ],
            },
        ],
      },
    );

    return resumedHoldFromJson(ApiJson.map(_payload(response.data)));
  }
}

/// Déballe l'enveloppe `{ "data": … }` quand elle est là.
Object? _payload(Object? body) {
  if (body is Map && body['data'] != null) return body['data'];
  return body;
}
