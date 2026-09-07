import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../domain/entities/group_ticket.dart';
import '../../domain/repositories/reservation_repository.dart';

/// Les billets d'une réservation de groupe, un par voyageur.
///
/// L'émission suit le paiement et prend un instant côté serveur : arriver
/// sur l'écran avant qu'elle soit finie rend une liste **vide**, ce qui est
/// une attente et non une erreur. Les deux se distinguent ici, parce qu'ils
/// n'appellent pas le même message.
class GroupTicketsCubit extends Cubit<GroupTicketsState> {
  GroupTicketsCubit(this._repository) : super(const GroupTicketsState());

  final ReservationRepository _repository;

  Future<void> load(String reservationId) async {
    if (state.isLoading) return;

    emit(state.copyWith(isLoading: true, clearFailure: true));

    final result = await _repository.bookingTickets(reservationId);

    result.fold(
      (failure) =>
          emit(state.copyWith(isLoading: false, errorMessage: failure.message)),
      (tickets) => emit(GroupTicketsState(tickets: tickets, isLoaded: true)),
    );
  }
}

/// Couche PRESENTATION : les billets du groupe et leur chargement.
class GroupTicketsState extends Equatable {
  const GroupTicketsState({
    this.tickets = const [],
    this.isLoading = false,
    this.isLoaded = false,
    this.errorMessage,
  });

  final List<GroupTicket> tickets;
  final bool isLoading;

  /// Le serveur a répondu, même par une liste vide.
  final bool isLoaded;

  final String? errorMessage;

  bool get hasFailed => errorMessage != null;

  /// Le serveur a répondu, mais n'a encore émis aucun billet — le paiement
  /// vient d'aboutir et l'émission suit.
  bool get isPending => isLoaded && tickets.isEmpty;

  GroupTicketsState copyWith({
    List<GroupTicket>? tickets,
    bool? isLoading,
    bool? isLoaded,
    String? errorMessage,
    bool clearFailure = false,
  }) {
    return GroupTicketsState(
      tickets: tickets ?? this.tickets,
      isLoading: isLoading ?? this.isLoading,
      isLoaded: isLoaded ?? this.isLoaded,
      // `copyWith` ne sait pas remettre un champ à `null` : un rechargement
      // qui réussit doit pouvoir effacer l'erreur précédente.
      errorMessage: clearFailure ? null : errorMessage ?? this.errorMessage,
    );
  }

  @override
  List<Object?> get props => [tickets, isLoading, isLoaded, errorMessage];
}
