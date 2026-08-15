import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecases/usecase.dart';
import '../entities/city.dart';
import '../repositories/booking_repository.dart';

/// Couche DOMAIN : les dernières villes recherchées, proposées en
/// raccourci dans le sélecteur de ville.
class GetRecentCitiesUseCase implements UseCase<List<City>, NoParams> {
  GetRecentCitiesUseCase(this._repository);

  final BookingRepository _repository;

  @override
  Future<Either<Failure, List<City>>> call(NoParams params) {
    return _repository.getRecentCities();
  }
}
