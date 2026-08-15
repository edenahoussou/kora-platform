import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/departure.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/seat.dart';
import '../../domain/usecases/get_seats_usecase.dart';

part 'seats_event.dart';
part 'seats_state.dart';

/// Couche PRESENTATION : le Bloc du plan des sièges.
///
/// Il porte le plan chargé et la sélection en cours. La sélection n'existe
/// que dans cet état : côté domaine, un siège est libre, vendu, retenu ou
/// bloqué — « choisi par moi » est une notion d'écran, pas de réseau.
class SeatsBloc extends Bloc<SeatsEvent, SeatsState> {
  SeatsBloc(this._getSeats) : super(const SeatsState()) {
    on<SeatsRequested>(_onRequested);
    on<SeatToggled>(_onToggled);
  }

  final GetSeatsUseCase _getSeats;

  Future<void> _onRequested(
    SeatsRequested event,
    Emitter<SeatsState> emit,
  ) async {
    emit(
      SeatsState(
        departure: event.departure,
        passengers: event.passengers,
      ),
    );

    final result = await _getSeats(event.departure);
    result.fold(
      (failure) => emit(
        state.copyWith(
          status: SeatsStatus.failure,
          errorMessage: failure.message,
        ),
      ),
      (seats) => emit(
        state.copyWith(status: SeatsStatus.success, seats: seats),
      ),
    );
  }

  void _onToggled(SeatToggled event, Emitter<SeatsState> emit) {
    if (!event.seat.isSelectable) return;

    final selected = [...state.selectedSeats];
    final index = selected.indexWhere((seat) => seat.id == event.seat.id);

    if (index >= 0) {
      selected.removeAt(index);
    } else {
      // Au-delà du quota, on remplace le siège choisi en premier plutôt
      // que d'ignorer le geste : l'utilisateur qui tape un autre siège
      // veut visiblement changer d'avis, pas ne rien faire.
      if (selected.length >= state.passengers) selected.removeAt(0);
      selected.add(event.seat);
    }

    emit(state.copyWith(selectedSeats: selected));
  }
}
