import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/errors/failures.dart';
import '../../domain/entities/depart.dart';
import '../../domain/repositories/departure_repository.dart';

part 'departure_list_state.dart';

/// Charge les départs affectés au convoyeur connecté (onglet Tournée —
/// voir MEZ-92). Ne met rien en cache localement : une liste de départs
/// hors ligne pourrait faire gérer à l'agent une course qui ne lui est
/// plus affectée — voir docstring `DepartureRepository`.
///
/// Vue par défaut (échange du 26/09/2026) : les départs DU JOUR, sans
/// restriction d'heure — pas les autres dates. Voir [loadUpcomingToday].
/// Le filtre du FAB (`TourneePage`) permet de consulter un autre jour, en
/// lecture seule (l'écran ne rend actionnable que le départ du jour même
/// — logique côté page, pas ici).
class DepartureListCubit extends Cubit<DepartureListState> {
  DepartureListCubit(this._repository) : super(const DepartureListState());

  final DepartureRepository _repository;

  /// Vue par défaut de la Tournée (appelée à l'ouverture de l'écran) :
  /// tous les départs du jour. `state.selectedDate` reste `null` — ce
  /// n'est pas un filtre choisi par le convoyeur, juste la vue de base.
  Future<void> loadUpcomingToday() async {
    emit(state.copyWith(
      status: DepartureListStatus.loading,
      clearSelectedDate: true,
    ));

    final result =
        await _repository.listAssigned(date: _isoDate(DateTime.now()));
    result.when(
      onSuccess: (departs) => emit(state.copyWith(
        status: departs.isEmpty
            ? DepartureListStatus.empty
            : DepartureListStatus.success,
        departs: departs,
      )),
      onFailure: (Failure failure) => emit(state.copyWith(
        status: DepartureListStatus.error,
        errorMessage: failure.message,
      )),
    );
  }

  /// Filtre explicite (FAB → "Choisir une date") : tous les départs de
  /// [date] — une consultation volontaire d'un autre jour, pas la vue de
  /// travail par défaut.
  Future<void> load(DateTime date) async {
    emit(state.copyWith(
      status: DepartureListStatus.loading,
      selectedDate: date,
    ));

    final result = await _repository.listAssigned(date: _isoDate(date));
    result.when(
      onSuccess: (departs) => emit(state.copyWith(
        status: departs.isEmpty
            ? DepartureListStatus.empty
            : DepartureListStatus.success,
        departs: departs,
      )),
      onFailure: (Failure failure) => emit(state.copyWith(
        status: DepartureListStatus.error,
        errorMessage: failure.message,
      )),
    );
  }

  /// Reprend le dernier chargement (bouton "Réessayer" après une erreur) —
  /// respecte le filtre actif s'il y en a un, revient à la vue par défaut
  /// sinon.
  Future<void> retry() {
    final DateTime? date = state.selectedDate;
    return date == null ? loadUpcomingToday() : load(date);
  }

  /// Retire le filtre par date (FAB → "Aujourd'hui") — revient à la vue
  /// par défaut.
  Future<void> clearDateFilter() => loadUpcomingToday();

  static String _isoDate(DateTime date) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${date.year}-${two(date.month)}-${two(date.day)}';
  }
}
