import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/quote.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/seat_hold.dart';
import '../models/bus_json.dart';
import '../models/quote_model.dart';
import '../models/seat_hold_model.dart';

/// Couche DATA : devis, blocage et libération des sièges.
///
/// Les trois endpoints exigent une session (`auth:sanctum`) — contrairement
/// au catalogue, qui est public. Comme les autres data sources, celui-ci
/// laisse remonter les `DioException` : c'est le repository qui les traduit
/// en `Failure`.
class ReservationRemoteDataSource {
  const ReservationRemoteDataSource(this._dio);

  final Dio _dio;

  Future<Quote> quote({
    required Departure departure,
    required int passengers,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.busQuotes,
      data: {
        'departure_uuid': departure.id,
        'origin_stop_uuid': departure.originStopId,
        'destination_stop_uuid': departure.destinationStopId,
        'passenger_count': passengers,
      },
    );

    return QuoteModel.fromJson(BusJson.map(_payload(response.data))).toEntity();
  }

  Future<SeatHold> hold({
    required Departure departure,
    required Quote quote,
    required List<Seat> seats,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.busHolds,
      data: {
        'departure_uuid': departure.id,
        'origin_stop_uuid': departure.originStopId,
        'destination_stop_uuid': departure.destinationStopId,
        'price_quote_uuid': quote.id,
        // `passenger_index` suit l'ordre de sélection : le premier siège
        // choisi ira au premier voyageur du formulaire suivant.
        'seats': [
          for (final (index, seat) in seats.indexed)
            {'seat_uuid': seat.id, 'passenger_index': index},
        ],
      },
    );

    return SeatHoldModel.fromJson(
      BusJson.map(_payload(response.data)),
    ).toEntity();
  }

  Future<void> release(String reservationId) =>
      _dio.delete<dynamic>(ApiConstants.hold(reservationId));
}

/// Déballe l'enveloppe `{ "data": … }` quand elle est là.
Object? _payload(Object? body) {
  if (body is Map && body['data'] != null) return body['data'];
  return body;
}
