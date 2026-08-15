import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecases/usecase.dart';
import '../entities/departure.dart';
import '../entities/seat.dart';
import '../repositories/booking_repository.dart';

/// Couche DOMAIN : récupère le plan des sièges d'un départ.
class GetSeatsUseCase implements UseCase<List<Seat>, Departure> {
  GetSeatsUseCase(this._repository);

  final BookingRepository _repository;

  @override
  Future<Either<Failure, List<Seat>>> call(Departure params) {
    return _repository.getSeats(params);
  }
}
