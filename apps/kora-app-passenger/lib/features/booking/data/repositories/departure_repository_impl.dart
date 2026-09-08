import 'package:dartz/dartz.dart';

import '../../../../core/error/api_failure_mapper.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/search_criteria.dart';
import '../../domain/repositories/departure_repository.dart';
import '../datasources/booking_data_source.dart';

/// Couche DATA : la recherche de départs, adossée au catalogue Bus.
///
/// Elle passe par le même [BookingDataSource] que le reste du parcours :
/// c'est lui qui décide, dans le service locator, si les données viennent
/// de l'API ou du jeu de démonstration. Deux sources parallèles auraient
/// laissé l'accueil parler à l'API pendant que la liste lisait des mocks.
class DepartureRepositoryImpl
    with ApiFailureMapper
    implements DepartureRepository {
  DepartureRepositoryImpl(this._dataSource);

  final BookingDataSource _dataSource;

  @override
  Future<Either<Failure, List<City>>> getCities() =>
      attempt(_dataSource.getCities);

  @override
  Future<Either<Failure, List<Departure>>> search(SearchCriteria criteria) =>
      attempt(() => _dataSource.searchDepartures(criteria));
}
