import 'dart:async';

import 'package:dartz/dartz.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';

import '../../domain/entities/departure.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/seat_hold.dart';
import '../../domain/repositories/reservation_repository.dart';

part 'seat_hold_event.dart';
part 'seat_hold_state.dart';

/// Le blocage des sièges, du « Continuer » du plan jusqu'au paiement.
///
/// **Il vit au-dessus des écrans, pas dans l'un d'eux.** Le décompte
/// commence sur le plan des sièges, s'affiche sur le formulaire passagers
/// puis sur le récapitulatif, et doit survivre à un retour en arrière. Un
/// Bloc par écran l'aurait remis à zéro à chaque navigation ; celui-ci est
/// fourni par la route mère du tunnel (voir `AppRouter`).
class SeatHoldBloc extends Bloc<SeatHoldEvent, SeatHoldState> {
  SeatHoldBloc(this._repository, {DateTime Function()? clock})
    : _clock = clock ?? DateTime.now,
      super(const SeatHoldState()) {
    on<SeatHoldRequested>(_onRequested);
    on<SeatHoldReleased>(_onReleased);
    on<SeatHoldSettled>(_onSettled);
    on<SeatHoldTicked>(_onTicked);
  }

  final ReservationRepository _repository;

  /// Injectable pour les tests : un décompte se vérifie mal quand il lit
  /// l'horloge du monde.
  final DateTime Function() _clock;

  Timer? _ticker;

  @override
  Future<void> close() {
    _ticker?.cancel();
    return super.close();
  }

  Future<void> _onRequested(
    SeatHoldRequested event,
    Emitter<SeatHoldState> emit,
  ) async {
    emit(
      state.copyWith(
        status: SeatHoldStatus.holding,
        departure: event.departure,
        passengers: event.seats.length,
        clearError: true,
      ),
    );

    // Le devis d'abord : le blocage réclame son identifiant, et il fige le
    // tarif pour que le montant vu au moment du choix soit celui qu'on
    // paiera.
    final quote = await _repository.quote(
      departure: event.departure,
      passengers: event.seats.length,
    );

    final held = await quote.fold(
      (failure) async => Left<Failure, SeatHold>(failure),
      (quote) => _repository.hold(
        departure: event.departure,
        quote: quote,
        seats: event.seats,
      ),
    );

    held.fold(
      (failure) => emit(
        state.copyWith(status: SeatHoldStatus.failure, errorMessage: failure),
      ),
      (hold) {
        emit(
          SeatHoldState(
            status: SeatHoldStatus.held,
            hold: hold,
            remaining: hold.remaining(_clock()),
            departure: event.departure,
            passengers: event.seats.length,
          ),
        );
        _startTicking();
      },
    );
  }

  Future<void> _onReleased(
    SeatHoldReleased event,
    Emitter<SeatHoldState> emit,
  ) async {
    final hold = state.hold;
    _ticker?.cancel();
    // Le départ survit à la libération : le voyageur reste sur le plan.
    emit(
      SeatHoldState(departure: state.departure, passengers: state.passengers),
    );

    // L'écran n'attend pas la réponse : les sièges sont rendus côté app
    // quoi qu'il arrive, et le serveur les libérera de toute façon à
    // l'échéance. Faire patienter le voyageur devant un appel qui ne peut
    // que réussir plus tard serait gratuit.
    if (hold != null) await _repository.release(hold.id);
  }

  /// Coupe le décompte sans rien rendre : la réservation est payée.
  void _onSettled(SeatHoldSettled event, Emitter<SeatHoldState> emit) {
    _ticker?.cancel();
    emit(
      SeatHoldState(departure: state.departure, passengers: state.passengers),
    );
  }

  void _onTicked(SeatHoldTicked event, Emitter<SeatHoldState> emit) {
    final hold = state.hold;
    if (hold == null) return;

    final remaining = hold.remaining(_clock());
    if (remaining > Duration.zero) {
      emit(state.copyWith(remaining: remaining));
      return;
    }

    // À zéro, le serveur a déjà rendu les sièges : on ne rappelle pas
    // `DELETE`, on annonce l'expiration et l'écran prend le relais.
    _ticker?.cancel();
    emit(
      SeatHoldState(
        status: SeatHoldStatus.expired,
        departure: state.departure,
        passengers: state.passengers,
      ),
    );
  }

  /// Une seconde : c'est la granularité qu'affiche le décompte (`MM:SS`).
  void _startTicking() {
    _ticker?.cancel();
    _ticker = Timer.periodic(
      const Duration(seconds: 1),
      (_) => add(const SeatHoldTicked()),
    );
  }
}
