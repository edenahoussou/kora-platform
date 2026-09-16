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
class DepartureListCubit extends Cubit<DepartureListState> {
  DepartureListCubit(this._repository) : super(const DepartureListState());

  final DepartureRepository _repository;

  Future<void> load({String? date, String? status}) async {
    emit(state.copyWith(status: DepartureListStatus.loading));

    final result = await _repository.listAssigned(date: date, status: status);
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
}
