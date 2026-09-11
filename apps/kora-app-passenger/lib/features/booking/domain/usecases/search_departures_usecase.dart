import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecases/usecase.dart';
import '../entities/departure.dart';
import '../entities/search_criteria.dart';
import '../repositories/departure_repository.dart';

/// Couche DOMAIN : cherche les départs correspondant aux critères saisis
/// sur l'accueil.
///
/// Les critères sont eux-mêmes le paramètre du use case : [SearchCriteria]
/// est déjà un objet métier complet et validé (voir sa documentation), pas
/// besoin d'une classe `Params` supplémentaire.
///
/// Il s'adresse à [DepartureRepository], dont c'est le seul objet — et
/// non au dépôt du tunnel de réservation, qui couvre sièges et billets.
class SearchDeparturesUseCase
    implements UseCase<List<Departure>, SearchCriteria> {
  SearchDeparturesUseCase(this._repository);

  final DepartureRepository _repository;

  @override
  Future<Either<Failure, List<Departure>>> call(SearchCriteria params) =>
      _repository.search(params);
}
