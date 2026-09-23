import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../domain/repositories/reservation_repository.dart';

/// Ce qui prévient les écrans qu'une réservation a changé.
///
/// L'onglet Voyages reste en mémoire derrière le détail d'un billet : sans
/// ce signal, il continuerait d'afficher comme confirmé un billet qu'on
/// vient d'annuler. Même procédé que les demandes en agence, qui se
/// rafraîchissent sur le flux de leur stockage local.
class BookingRefresh {
  BookingRefresh();

  final _changes = StreamController<void>.broadcast();

  Stream<void> get changes => _changes.stream;

  void notify() => _changes.add(null);

  Future<void> close() => _changes.close();
}

/// Couche PRESENTATION : annuler une réservation, depuis le détail d'un
/// billet.
///
/// L'annulation porte sur **toute la réservation** — c'est ce que fait le
/// serveur —, donc sur tous les billets qu'elle a émis. L'écran le dit
/// avant de demander confirmation.
class CancelTicketCubit extends Cubit<CancelTicketState> {
  CancelTicketCubit(this._repository, this._refresh)
    : super(const CancelTicketState());

  final ReservationRepository _repository;
  final BookingRefresh _refresh;

  Future<void> cancel(String reservationId) async {
    if (state.isCancelling || state.isCancelled) return;

    emit(const CancelTicketState(isCancelling: true));

    final result = await _repository.cancelBooking(reservationId);

    result.fold((failure) => emit(CancelTicketState(failure: failure)), (_) {
      // Les listes qui tiennent encore l'ancien statut se relisent.
      _refresh.notify();
      emit(const CancelTicketState(isCancelled: true));
    });
  }
}

class CancelTicketState extends Equatable {
  const CancelTicketState({
    this.isCancelling = false,
    this.isCancelled = false,
    this.failure,
  });

  final bool isCancelling;

  /// Le serveur a clos la réservation : l'écran rend la main à la liste.
  final bool isCancelled;

  final Failure? failure;

  @override
  List<Object?> get props => [isCancelling, isCancelled, failure];
}
