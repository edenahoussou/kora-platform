import 'package:dartz/dartz.dart';

import '../../../../core/error/api_failure_mapper.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/ticket.dart';
import '../../domain/entities/upcoming_trip.dart';
import '../../domain/repositories/booking_repository.dart';
import '../datasources/booking_data_source.dart';

/// Couche DATA : implémentation du contrat [BookingRepository].
///
/// Son seul travail est de traduire les erreurs techniques de la source de
/// données en [Failure] métier — c'est la frontière au-delà de laquelle
/// plus personne ne connaît Dio ni les codes HTTP.
///
/// Deux vocabulaires d'erreurs arrivent ici, parce que deux sources
/// coexistent le temps du branchement :
/// * les [ApiException] du package réseau, portées par un [DioException],
///   quand les données viennent de l'API (`BookingRemoteDataSourceImpl`) ;
/// * les [ServerException] / [NetworkException] de `core/error`, levées
///   par les sources locales.
///
/// Pas de vérification `NetworkInfo` avant l'appel : une absence de réseau
/// se voit très bien à l'échec de la requête ([NetworkApiException]), et
/// la tester d'abord ajouterait un aller-retour système à chaque appel
/// pour un résultat qui peut de toute façon changer entre-temps.
class BookingRepositoryImpl with ApiFailureMapper implements BookingRepository {
  BookingRepositoryImpl(this._dataSource);

  final BookingDataSource _dataSource;

  @override
  Future<Either<Failure, List<City>>> getRecentCities() =>
      attempt(_dataSource.getRecentCities);

  @override
  Future<Either<Failure, List<Seat>>> getSeats(Departure departure) =>
      attempt(() => _dataSource.getSeats(departure));

  @override
  Future<Either<Failure, UpcomingTrip?>> getNextTrip() =>
      attempt(_dataSource.getNextTrip);

  @override
  Future<Either<Failure, List<Ticket>>> getTickets() =>
      attempt(_dataSource.getTickets);
}
