part of 'seats_bloc.dart';

sealed class SeatsEvent extends Equatable {
  const SeatsEvent();

  @override
  List<Object?> get props => [];
}

/// Chargement du plan pour un départ donné.
class SeatsRequested extends SeatsEvent {
  const SeatsRequested({required this.departure, this.passengers = 1});

  final Departure departure;

  /// Nombre de sièges à choisir, repris de la recherche.
  final int passengers;

  @override
  List<Object?> get props => [departure, passengers];
}

/// Appui sur un siège : le choisit, ou le libère s'il l'était déjà.
class SeatToggled extends SeatsEvent {
  const SeatToggled(this.seat);

  final Seat seat;

  @override
  List<Object?> get props => [seat];
}
