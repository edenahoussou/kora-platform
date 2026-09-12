// Le catalogue des départs, monté pour les tests d'écran.

import 'package:dartz/dartz.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/city.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/departure_search.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/search_criteria.dart';
import 'package:kora_app_passenger/features/booking/domain/repositories/departure_repository.dart';

/// Dépôt de recherche réglable.
///
/// Il ne rend rien par défaut : les tests d'écran du détail travaillent sur
/// le départ que la liste leur a transmis, seule source depuis que MEZ-63 a
/// retiré la route qui rechargeait un départ isolé.
class StubDepartureRepository implements DepartureRepository {
  final List<String> calls = [];

  @override
  Future<Either<Failure, List<City>>> getCities() async => const Right([]);

  @override
  Future<Either<Failure, DepartureSearch>> search(
    SearchCriteria criteria,
  ) async {
    calls.add('search:${criteria.origin.name}-${criteria.destination.name}');
    return const Right(DepartureSearch.oneWay([]));
  }
}
