import 'package:dartz/dartz.dart';

import '../../../../core/error/exceptions.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/search_criteria.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/upcoming_trip.dart';
import '../../domain/repositories/booking_repository.dart';
import '../datasources/booking_data_source.dart';

/// Couche DATA : implémentation du contrat [BookingRepository].
///
/// Son seul travail est de traduire les exceptions techniques de la source
/// de données en [Failure] métier. Pas de vérification `NetworkInfo` ici,
/// contrairement au modèle du GUIDE (§9) : la source du Sprint 1 est
/// locale, donc il n'y a rien à vérifier. Le jour où
/// [BookingDataSource] appellera l'API, ce test reviendra ici — c'est
/// exactement le rôle du repository de décider ça, et pas des Blocs.
class BookingRepositoryImpl implements BookingRepository {
  BookingRepositoryImpl(this._dataSource);

  final BookingDataSource _dataSource;

  @override
  Future<Either<Failure, List<City>>> getCities() async {
    try {
      return Right(await _dataSource.getCities());
    } on ServerException catch (e) {
      return Left(ServerFailure(e.message));
    } on NetworkException {
      return const Left(NetworkFailure());
    }
  }

  @override
  Future<Either<Failure, List<City>>> getRecentCities() async {
    try {
      return Right(await _dataSource.getRecentCities());
    } on ServerException catch (e) {
      return Left(ServerFailure(e.message));
    } on NetworkException {
      return const Left(NetworkFailure());
    }
  }

  @override
  Future<Either<Failure, List<Departure>>> searchDepartures(
    SearchCriteria criteria,
  ) async {
    try {
      return Right(await _dataSource.searchDepartures(criteria));
    } on ServerException catch (e) {
      return Left(ServerFailure(e.message));
    } on NetworkException {
      return const Left(NetworkFailure());
    }
  }

  @override
  Future<Either<Failure, List<Seat>>> getSeats(Departure departure) async {
    try {
      return Right(await _dataSource.getSeats(departure));
    } on ServerException catch (e) {
      return Left(ServerFailure(e.message));
    } on NetworkException {
      return const Left(NetworkFailure());
    }
  }

  @override
  Future<Either<Failure, UpcomingTrip?>> getNextTrip() async {
    try {
      return Right(await _dataSource.getNextTrip());
    } on ServerException catch (e) {
      return Left(ServerFailure(e.message));
    } on NetworkException {
      return const Left(NetworkFailure());
    }
  }
}
