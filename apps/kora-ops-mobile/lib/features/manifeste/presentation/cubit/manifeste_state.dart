part of 'manifeste_cubit.dart';

enum ManifesteStatus { noDepart, loading, success, error }

class ManifesteState extends Equatable {
  const ManifesteState({
    this.status = ManifesteStatus.noDepart,
    this.passengers = const <Passenger>[],
    this.errorMessage,
  });

  final ManifesteStatus status;
  final List<Passenger> passengers;
  final String? errorMessage;

  int get embarques => passengers
      .where((Passenger p) => p.statut == BoardingStatus.embarque)
      .length;

  ManifesteState copyWith({
    ManifesteStatus? status,
    List<Passenger>? passengers,
    String? errorMessage,
  }) {
    return ManifesteState(
      status: status ?? this.status,
      passengers: passengers ?? this.passengers,
      errorMessage: errorMessage,
    );
  }

  @override
  List<Object?> get props => <Object?>[status, passengers, errorMessage];
}
