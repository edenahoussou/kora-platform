import 'package:dartz/dartz.dart';
import 'package:dio/dio.dart';

import '../../../../core/error/api_failure_mapper.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/quote.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/seat_hold.dart';
import '../../domain/repositories/reservation_repository.dart';
import '../datasources/reservation_remote_data_source.dart';

class ReservationRepositoryImpl
    with ApiFailureMapper
    implements ReservationRepository {
  const ReservationRepositoryImpl(this._dataSource);

  final ReservationRemoteDataSource _dataSource;

  @override
  Future<Either<Failure, Quote>> quote({
    required Departure departure,
    required int passengers,
  }) => attempt(
    () => _dataSource.quote(departure: departure, passengers: passengers),
  );

  @override
  Future<Either<Failure, SeatHold>> hold({
    required Departure departure,
    required Quote quote,
    required List<Seat> seats,
  }) async {
    SeatUnavailableFailure? conflict;

    final result = await attempt(() async {
      try {
        return await _dataSource.hold(
          departure: departure,
          quote: quote,
          seats: seats,
        );
      } on DioException catch (error) {
        // Le 409 est relevé ici, avant que le mapper commun ne le réduise
        // à son statut : lui perdrait le siège que le serveur nomme, et
        // c'est pourtant la seule information qui permette à l'écran de
        // repeindre ce siège en occupé plutôt que de tout recommencer.
        conflict = _seatConflict(error);
        rethrow;
      }
    });

    final captured = conflict;
    return captured == null ? result : Left(captured);
  }

  @override
  Future<Either<Failure, SeatHold>> savePassengers({
    required String reservationId,
    required List<Passenger> passengers,
  }) => attempt(
    () => _dataSource.savePassengers(
      reservationId: reservationId,
      passengers: passengers,
    ),
  );

  @override
  Future<Either<Failure, void>> release(String reservationId) =>
      attempt(() => _dataSource.release(reservationId));
}

/// Lit un 409 « siège plus disponible », ou rend `null` si c'en est un
/// autre.
SeatUnavailableFailure? _seatConflict(DioException error) {
  if (error.response?.statusCode != 409) return null;

  final body = ApiJson.map(error.response?.data);
  final label = ApiJson.stringOrNull(body['seat_code']);

  return SeatUnavailableFailure(
    ApiJson.string(
      body['message'],
      label == null
          ? 'Ce siège vient d’être pris.'
          : 'Le siège $label vient d’être pris.',
    ),
    seatId: ApiJson.stringOrNull(body['conflicting_seat_uuid']),
    seatLabel: label,
  );
}
