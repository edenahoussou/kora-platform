import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../../../core/usecases/usecase.dart';
import '../../../booking/domain/entities/city.dart';
import '../../../booking/domain/entities/search_criteria.dart';
import '../../../booking/domain/entities/upcoming_trip.dart';
import '../../../booking/domain/usecases/get_cities_usecase.dart';
import '../../../booking/domain/usecases/get_next_trip_usecase.dart';
import '../../../booking/domain/usecases/get_recent_cities_usecase.dart';

part 'home_event.dart';
part 'home_state.dart';

/// Couche PRESENTATION : le Bloc de l'écran d'accueil.
///
/// Il porte deux choses :
/// 1. les données chargées au démarrage (villes desservies, prochain
///    voyage réservé) ;
/// 2. la saisie en cours du formulaire de recherche (départ, arrivée,
///    date, nombre de voyageurs).
///
/// Il ne navigue pas lui-même vers les résultats : l'écran lit
/// [HomeState.criteria] et appelle `go_router`. Un Bloc ne connaît jamais
/// la navigation — c'est ce qui permet de le tester sans widget.
class HomeBloc extends Bloc<HomeEvent, HomeState> {
  HomeBloc(
    this._getCities,
    this._getRecentCities,
    this._getNextTrip, {
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now,
       super(const HomeState()) {
    on<HomeStarted>(_onStarted);
    on<HomeOriginSelected>(_onOriginSelected);
    on<HomeDestinationSelected>(_onDestinationSelected);
    on<HomeRouteSwapped>(_onRouteSwapped);
    on<HomeDateSelected>(_onDateSelected);
    on<HomePassengersSelected>(_onPassengersSelected);
    on<HomeTripTypeChanged>(_onTripTypeChanged);
    on<HomeReturnDateSelected>(_onReturnDateSelected);
  }

  final GetCitiesUseCase _getCities;
  final GetRecentCitiesUseCase _getRecentCities;
  final GetNextTripUseCase _getNextTrip;

  /// Horloge injectable — voir [BookingMockDataSource], même raison :
  /// garder la date du jour testable.
  final DateTime Function() _now;

  /// Trajet pré-rempli au premier chargement, comme dans la maquette.
  ///
  /// C'est une commodité de démo propre au Sprint 1 : au Sprint 2 ces deux
  /// valeurs viendront de la dernière recherche de l'utilisateur (ou de sa
  /// position), et ces constantes disparaîtront.
  static const String _defaultOriginId = 'ctn';
  static const String _defaultDestinationId = 'mlv';

  Future<void> _onStarted(HomeStarted event, Emitter<HomeState> emit) async {
    emit(state.copyWith(status: HomeStatus.loading));

    // Les trois appels partent en parallèle : rien ne justifie d'attendre
    // les villes pour demander le prochain voyage.
    final citiesFuture = _getCities(const NoParams());
    final recentFuture = _getRecentCities(const NoParams());
    final tripFuture = _getNextTrip(const NoParams());

    final citiesResult = await citiesFuture;
    final recentResult = await recentFuture;
    final tripResult = await tripFuture;

    // Un seul de ces appels suffit à faire échouer l'écran : sans la
    // liste des villes il n'y a pas de recherche possible, et une carte
    // « Prochain voyage » silencieusement absente serait un mensonge.
    final failure =
        citiesResult.fold<Failure?>((failure) => failure, (_) => null) ??
        recentResult.fold<Failure?>((failure) => failure, (_) => null) ??
        tripResult.fold<Failure?>((failure) => failure, (_) => null);
    if (failure != null) {
      emit(
        state.copyWith(
          status: HomeStatus.failure,
          errorMessage: failure.message,
        ),
      );
      return;
    }

    final cities = citiesResult.getOrElse(() => const []);

    emit(
      state.copyWith(
        status: HomeStatus.success,
        cities: cities,
        recentCities: recentResult.getOrElse(() => const []),
        origin: state.origin ?? _cityOrNull(cities, _defaultOriginId),
        destination:
            state.destination ?? _cityOrNull(cities, _defaultDestinationId),
        date: state.date ?? _today(),
        today: _today(),
        nextTrip: tripResult.getOrElse(() => null),
      ),
    );
  }

  void _onOriginSelected(HomeOriginSelected event, Emitter<HomeState> emit) {
    // Choisir comme départ la ville déjà en arrivée intervertit le trajet
    // plutôt que de produire un Cotonou → Cotonou impossible à chercher.
    if (event.city == state.destination) {
      add(const HomeRouteSwapped());
      return;
    }
    emit(state.copyWith(origin: event.city));
  }

  void _onDestinationSelected(
    HomeDestinationSelected event,
    Emitter<HomeState> emit,
  ) {
    if (event.city == state.origin) {
      add(const HomeRouteSwapped());
      return;
    }
    emit(state.copyWith(destination: event.city));
  }

  void _onRouteSwapped(HomeRouteSwapped event, Emitter<HomeState> emit) {
    if (state.origin == null && state.destination == null) return;

    // `copyWith` ne sait pas remettre un champ à `null` — or échanger un
    // trajet à moitié rempli déplace bien une valeur vers un champ vide.
    // On reconstruit donc l'état explicitement.
    emit(
      HomeState(
        status: state.status,
        cities: state.cities,
        recentCities: state.recentCities,
        origin: state.destination,
        destination: state.origin,
        date: state.date,
        passengers: state.passengers,
        nextTrip: state.nextTrip,
        today: state.today,
        errorMessage: state.errorMessage,
      ),
    );
  }

  void _onDateSelected(HomeDateSelected event, Emitter<HomeState> emit) {
    emit(state.copyWith(date: event.date));
  }

  void _onPassengersSelected(
    HomePassengersSelected event,
    Emitter<HomeState> emit,
  ) {
    emit(state.copyWith(passengers: event.passengers));
  }

  City? _cityOrNull(List<City> cities, String id) {
    for (final city in cities) {
      if (city.id == id) return city;
    }
    return null;
  }

  /// Aujourd'hui, à minuit : seule la date compte pour une recherche.
  DateTime _today() {
    final now = _now();
    return DateTime(now.year, now.month, now.day);
  }

  void _onTripTypeChanged(HomeTripTypeChanged event, Emitter<HomeState> emit) {
    emit(state.copyWith(tripType: event.tripType));
  }

  void _onReturnDateSelected(
    HomeReturnDateSelected event,
    Emitter<HomeState> emit,
  ) {
    emit(state.copyWith(returnDate: event.date));
  }
}
