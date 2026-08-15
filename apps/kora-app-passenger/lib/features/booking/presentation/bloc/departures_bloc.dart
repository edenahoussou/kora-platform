import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecases/usecase.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/search_criteria.dart';
import '../../domain/usecases/get_cities_usecase.dart';
import '../../domain/usecases/search_departures_usecase.dart';

part 'departures_event.dart';
part 'departures_state.dart';

/// Couche PRESENTATION : le Bloc de l'écran des départs.
///
/// Il fait deux choses :
/// 1. traduire les paramètres de l'URL (`?origin=ctn&date=2026-08-14…`) en
///    [SearchCriteria] — d'où le besoin de la liste des villes ;
/// 2. lancer la recherche et trier les résultats.
///
/// Le tri est appliqué à l'affichage (voir [DeparturesState.sortedDepartures])
/// et non en relançant la recherche : changer de tri ne doit pas rappeler
/// l'API.
class DeparturesBloc extends Bloc<DeparturesEvent, DeparturesState> {
  DeparturesBloc(this._getCities, this._searchDepartures)
    : super(const DeparturesState()) {
    on<DeparturesRequested>(_onRequested);
    on<DeparturesSortChanged>(_onSortChanged);
  }

  final GetCitiesUseCase _getCities;
  final SearchDeparturesUseCase _searchDepartures;

  Future<void> _onRequested(
    DeparturesRequested event,
    Emitter<DeparturesState> emit,
  ) async {
    emit(state.copyWith(status: DeparturesStatus.loading));

    final citiesResult = await _getCities(const NoParams());
    final citiesFailure = citiesResult.fold<Failure?>(
      (failure) => failure,
      (_) => null,
    );
    if (citiesFailure != null) {
      emit(_failed(citiesFailure.message));
      return;
    }

    final cities = citiesResult.getOrElse(() => const []);
    final criteria = _criteriaFrom(event, cities);
    if (criteria == null) {
      // Paramètres d'URL absents ou inconnus : l'écran ne peut rien
      // chercher, et le dire vaut mieux qu'une liste vide trompeuse.
      emit(_failed('Cette recherche est incomplète. Reprenez-la depuis '
          'l’accueil.'));
      return;
    }

    // Le trajet est connu avant même d'avoir les résultats : l'écran de
    // recherche l'affiche pendant le chargement.
    emit(state.copyWith(criteria: criteria));

    final result = await _searchDepartures(criteria);
    result.fold(
      (failure) => emit(_failed(failure.message)),
      (departures) => emit(
        state.copyWith(
          status: DeparturesStatus.success,
          criteria: criteria,
          departures: departures,
        ),
      ),
    );
  }

  void _onSortChanged(
    DeparturesSortChanged event,
    Emitter<DeparturesState> emit,
  ) {
    emit(state.copyWith(sort: event.sort));
  }

  DeparturesState _failed(String message) => DeparturesState(
    status: DeparturesStatus.failure,
    criteria: state.criteria,
    sort: state.sort,
    errorMessage: message,
  );

  /// Reconstruit les critères de recherche à partir des paramètres bruts.
  /// `null` dès qu'un paramètre manque, est inconnu ou est illisible.
  SearchCriteria? _criteriaFrom(DeparturesRequested event, List<City> cities) {
    final origin = _cityById(cities, event.originId);
    final destination = _cityById(cities, event.destinationId);
    final date = DateTime.tryParse(event.date ?? '');
    if (origin == null || destination == null || date == null) return null;

    return SearchCriteria(
      origin: origin,
      destination: destination,
      date: date,
      passengers: int.tryParse(event.passengers ?? '') ?? 1,
    );
  }

  City? _cityById(List<City> cities, String? id) {
    if (id == null) return null;
    for (final city in cities) {
      if (city.id == id) return city;
    }
    return null;
  }
}
