part of 'seats_bloc.dart';

enum SeatsStatus { loading, success, failure }

/// Couche PRESENTATION : le plan des sièges et la sélection en cours.
class SeatsState extends Equatable {
  const SeatsState({
    this.status = SeatsStatus.loading,
    this.departure,
    this.seats = const [],
    this.grid,
    this.selectedSeats = const [],
    this.passengers = 1,
    this.errorMessage,
  });

  final SeatsStatus status;
  final Departure? departure;
  final List<Seat> seats;

  /// La géométrie de la cabine, quand le bus a un gabarit.
  ///
  /// `null` sinon : l'écran dessine alors le plan en déduisant les
  /// positions des codes, comme il l'a toujours fait.
  final SeatGrid? grid;

  /// Vrai quand l'écran a de quoi dessiner la vraie cabine — voir
  /// [SeatGrid.describesCabin] pour ce que « vraie » veut dire ici.
  bool get hasGrid {
    final grid = this.grid;
    return grid != null && grid.isUsable && grid.describesCabin;
  }

  /// Dans l'ordre de sélection : c'est aussi l'ordre d'affichage sous le
  /// plan (`4A, 4B`).
  final List<Seat> selectedSeats;

  /// Nombre de sièges attendus, égal au nombre de voyageurs.
  final int passengers;

  final String? errorMessage;

  bool isSelected(Seat seat) =>
      selectedSeats.any((selected) => selected.id == seat.id);

  /// Le parcours ne continue que si chaque voyageur a son siège.
  bool get canContinue => selectedSeats.length == passengers;

  /// Total dû, à savoir le prix du départ par siège choisi.
  ///
  /// On compte au moins un siège pour que le montant affiché ne tombe pas
  /// à zéro avant toute sélection — la maquette fait de même
  /// (`money(12500 * Math.max(1, …))`).
  Price get total {
    final price = departure?.price ?? const Price(0);
    return price * (selectedSeats.isEmpty ? 1 : selectedSeats.length);
  }

  SeatsState copyWith({
    SeatsStatus? status,
    Departure? departure,
    List<Seat>? seats,
    SeatGrid? grid,
    List<Seat>? selectedSeats,
    int? passengers,
    String? errorMessage,
  }) {
    return SeatsState(
      status: status ?? this.status,
      departure: departure ?? this.departure,
      seats: seats ?? this.seats,
      grid: grid ?? this.grid,
      selectedSeats: selectedSeats ?? this.selectedSeats,
      passengers: passengers ?? this.passengers,
      errorMessage: errorMessage ?? this.errorMessage,
    );
  }

  @override
  List<Object?> get props => [
    status,
    departure,
    seats,
    grid,
    selectedSeats,
    passengers,
    errorMessage,
  ];
}
