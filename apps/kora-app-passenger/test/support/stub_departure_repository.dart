// Le catalogue des départs, monté pour les tests d'écran.

import 'package:dartz/dartz.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/city.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure.dart';
import 'package:kora_app_passenger/features/booking/domain/repositories/departure_repository.dart';

/// Dépôt de recherche réglable.
///
/// Par défaut [byId] échoue : le détail garde alors le départ que la liste
/// lui a transmis, ce qui est exactement ce que vérifient la plupart des
/// tests d'écran. Passer [refreshed] rejoue un rafraîchissement réussi.
class StubDepartureRepository implements DepartureRepository {
  StubDepartureRepository({this.refreshed});

  /// Ce que rend [byId] quand le rafraîchissement aboutit.
  final Departure? refreshed;

  final List<String> calls = [];

  @override
  Future<Either<Failure, Departure>> byId(String departureId) async {
    calls.add('byId:$departureId');

    final refreshed = this.refreshed;
    if (refreshed == null) {
      return const Left(NetworkFailure('Pas de réseau'));
    }
    return Right(refreshed);
  }

  @override
  Future<Either<Failure, List<City>>> getCities() async => const Right([]);

  @override
  Future<Either<Failure, List<Departure>>> search({
    required String originCity,
    required String destinationCity,
    required DateTime date,
  }) async => const Right([]);
}
