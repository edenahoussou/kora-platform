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
/// Le tri **et les filtres** sont appliqués à l'affichage (voir
/// [DeparturesState.visibleDepartures]) et non en relançant la recherche :
/// cocher « Matin » ou passer au tri par prix ne doit pas rappeler l'API,
/// tous les départs du jour sont déjà là.
class DeparturesBloc extends Bloc<DeparturesEvent, DeparturesState> {
  DeparturesBloc(this._getCities, this._searchDepartures)
    : super(const DeparturesState()) {
    on<DeparturesRequested>(_onRequested);
    on<DeparturesRetried>(_onRetried);
    on<DeparturesSortChanged>(_onSortChanged);
    on<DeparturesTimeSlotToggled>(_onTimeSlotToggled);
    on<DeparturesFiltersCleared>(_onFiltersCleared);
    on<DeparturesRefreshed>(_onRefreshed);
  }

  final GetCitiesUseCase _getCities;
  final SearchDeparturesUseCase _searchDepartures;

  /// La dernière recherche demandée, rejouée telle quelle par
  /// [DeparturesRetried]. L'écran d'erreur n'a ainsi rien à retenir des
  /// paramètres d'URL pour proposer « Réessayer ».
  DeparturesRequested? _lastRequest;

  Future<void> _onRequested(
    DeparturesRequested event,
    Emitter<DeparturesState> emit,
  ) async {
    _lastRequest = event;
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
      emit(
        _failed(
          'Cette recherche est incomplète. Reprenez-la depuis '
          'l’accueil.',
        ),
      );
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

  /// Relance la recherche après un échec réseau.
  ///
  /// Sans recherche précédente — impossible en pratique, l'écran en lance
  /// toujours une en s'ouvrant — il n'y a rien à rejouer, et un état
  /// d'erreur inchangé vaut mieux qu'un chargement sans fin.
  Future<void> _onRetried(
    DeparturesRetried event,
    Emitter<DeparturesState> emit,
  ) async {
    final request = _lastRequest;
    if (request == null) return;

    await _onRequested(request, emit);
  }

  /// Retaper la puce déjà active inverse le sens ; changer de puce repart
  /// en croissant. Une puce fraîchement choisie qui hériterait du sens
  /// précédent afficherait « le plus cher d'abord » alors que
  /// l'utilisateur vient seulement de demander un tri par prix.
  void _onSortChanged(
    DeparturesSortChanged event,
    Emitter<DeparturesState> emit,
  ) {
    final isSameField = event.field == state.sortField;

    emit(
      state.copyWith(
        sortField: event.field,
        sortDescending: isSameField && !state.sortDescending,
      ),
    );
  }

  void _onTimeSlotToggled(
    DeparturesTimeSlotToggled event,
    Emitter<DeparturesState> emit,
  ) {
    final slots = {...state.timeSlots};
    if (!slots.remove(event.slot)) slots.add(event.slot);

    emit(state.copyWith(timeSlots: slots));
  }

  void _onFiltersCleared(
    DeparturesFiltersCleared event,
    Emitter<DeparturesState> emit,
  ) {
    emit(state.copyWith(timeSlots: const {}));
  }

  /// Rejoue la recherche **sans vider l'écran**.
  ///
  /// Les critères sont déjà résolus : on rappelle directement la
  /// recherche, sans repasser par la liste des villes. Un échec ne casse
  /// pas la liste affichée — des places légèrement périmées valent mieux
  /// qu'un écran d'erreur à la place de résultats corrects. Le message
  /// part dans [DeparturesState.refreshError], que l'écran signale au
  /// passage.
  Future<void> _onRefreshed(
    DeparturesRefreshed event,
    Emitter<DeparturesState> emit,
  ) async {
    final criteria = state.criteria;
    if (criteria == null) return;

    emit(state.copyWith(isRefreshing: true));

    final result = await _searchDepartures(criteria);
    result.fold(
      (failure) => emit(
        state.copyWith(isRefreshing: false, refreshError: failure.message),
      ),
      (departures) => emit(
        state.copyWith(
          status: DeparturesStatus.success,
          departures: departures,
          isRefreshing: false,
        ),
      ),
    );
  }

  /// L'écran d'erreur, en conservant ce que l'utilisateur a réglé : ses
  /// filtres et son tri doivent survivre à une coupure réseau.
  DeparturesState _failed(String message) => DeparturesState(
    status: DeparturesStatus.failure,
    criteria: state.criteria,
    sortField: state.sortField,
    sortDescending: state.sortDescending,
    timeSlots: state.timeSlots,
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
