import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecases/usecase.dart';
import '../entities/city.dart';
import '../repositories/departure_repository.dart';

/// Couche DOMAIN : récupère les villes desservies par le réseau Bus.
class GetCitiesUseCase implements UseCase<List<City>, NoParams> {
  GetCitiesUseCase(this._repository);

  final DepartureRepository _repository;

  @override
  Future<Either<Failure, List<City>>> call(NoParams params) {
    return _repository.getCities();
  }
}
