import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/usecases/usecase.dart';
import '../../domain/entities/ticket.dart';
import '../../domain/usecases/get_tickets_usecase.dart';

part 'tickets_event.dart';
part 'tickets_state.dart';

/// Couche PRESENTATION : l'état de l'écran « Mes voyages ».
///
/// Les billets sont chargés **une fois**, tous statuts confondus, et le
/// changement d'onglet ne relance rien : c'est un filtre d'affichage, pas
/// une nouvelle requête. Les compteurs des trois onglets restent ainsi
/// cohérents entre eux.
class TicketsBloc extends Bloc<TicketsEvent, TicketsState> {
  TicketsBloc(this._getTickets, {DateTime Function()? now})
    : _now = now ?? DateTime.now,
      super(TicketsState(today: (now ?? DateTime.now)())) {
    on<TicketsStarted>(_onStarted);
    on<TicketsFilterChanged>(_onFilterChanged);
  }

  final GetTicketsUseCase _getTickets;

  /// Horloge injectable : les tests figent le temps pour vérifier des
  /// libellés du type « Départ dans 7 jours ».
  final DateTime Function() _now;

  Future<void> _onStarted(
    TicketsStarted event,
    Emitter<TicketsState> emit,
  ) async {
    emit(state.copyWith(status: TicketsStatus.loading, today: _now()));

    final result = await _getTickets(const NoParams());

    emit(
      result.fold(
        (failure) => state.copyWith(
          status: TicketsStatus.failure,
          errorMessage: failure.message,
        ),
        (tickets) => state.copyWith(
          status: TicketsStatus.success,
          tickets: tickets,
          errorMessage: '',
        ),
      ),
    );
  }

  void _onFilterChanged(
    TicketsFilterChanged event,
    Emitter<TicketsState> emit,
  ) {
    emit(state.copyWith(filter: event.filter));
  }
}
