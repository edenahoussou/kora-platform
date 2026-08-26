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

/// Le serveur a refusé un siège au moment de le retenir (409).
///
/// Le plan avait été chargé quelques instants plus tôt : entre-temps, un
/// autre voyageur a gagné la course. On repeint ce siège en occupé et on
/// le retire de la sélection, plutôt que de laisser le voyageur retaper
/// « Continuer » sur une place qui ne reviendra pas.
class SeatRefused extends SeatsEvent {
  const SeatRefused({this.seatId, this.seatLabel});

  /// L'identifiant renvoyé par le serveur. Le libellé sert de repli : la
  /// réponse ne porte pas toujours les deux.
  final String? seatId;
  final String? seatLabel;

  @override
  List<Object?> get props => [seatId, seatLabel];
}
