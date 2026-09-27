part of 'departure_list_cubit.dart';

enum DepartureListStatus { loading, success, empty, error }

class DepartureListState extends Equatable {
  const DepartureListState({
    this.status = DepartureListStatus.loading,
    this.departs = const <Depart>[],
    this.errorMessage,
    this.selectedDate,
  });

  final DepartureListStatus status;
  final List<Depart> departs;
  final String? errorMessage;

  /// Date choisie via le filtre de la Tournée (FAB) — `null` = pas de
  /// filtre, la liste montre tous les départs affectés. Sert uniquement à
  /// réafficher le filtre actif à l'écran ; le filtrage lui-même est fait
  /// côté serveur (`GET /ops/departures?date=`).
  final DateTime? selectedDate;

  DepartureListState copyWith({
    DepartureListStatus? status,
    List<Depart>? departs,
    String? errorMessage,
    DateTime? selectedDate,
    bool clearSelectedDate = false,
  }) {
    return DepartureListState(
      status: status ?? this.status,
      departs: departs ?? this.departs,
      errorMessage: errorMessage,
      selectedDate:
          clearSelectedDate ? null : (selectedDate ?? this.selectedDate),
    );
  }

  @override
  List<Object?> get props =>
      <Object?>[status, departs, errorMessage, selectedDate];
}
