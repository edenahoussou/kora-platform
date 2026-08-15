import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecases/usecase.dart';
import '../entities/upcoming_trip.dart';
import '../repositories/booking_repository.dart';

/// Couche DOMAIN : récupère le prochain voyage réservé, affiché sur
/// l'accueil. Retourne `null` quand l'utilisateur n'a aucun voyage à venir.
class GetNextTripUseCase implements UseCase<UpcomingTrip?, NoParams> {
  GetNextTripUseCase(this._repository);

  final BookingRepository _repository;

  @override
  Future<Either<Failure, UpcomingTrip?>> call(NoParams params) {
    return _repository.getNextTrip();
  }
}
