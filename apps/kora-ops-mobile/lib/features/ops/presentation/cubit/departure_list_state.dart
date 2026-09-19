part of 'departure_list_cubit.dart';

enum DepartureListStatus { loading, success, empty, error }

class DepartureListState extends Equatable {
  const DepartureListState({
    this.status = DepartureListStatus.loading,
    this.departs = const <Depart>[],
    this.errorMessage,
  });

  final DepartureListStatus status;
  final List<Depart> departs;
  final String? errorMessage;

  DepartureListState copyWith({
    DepartureListStatus? status,
    List<Depart>? departs,
    String? errorMessage,
  }) {
    return DepartureListState(
      status: status ?? this.status,
      departs: departs ?? this.departs,
      errorMessage: errorMessage,
    );
  }

  @override
  List<Object?> get props => <Object?>[status, departs, errorMessage];
}
