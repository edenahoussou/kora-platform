import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/trip_log.dart';
import '../../domain/repositories/trip_log_repository.dart';

enum TripLogStatus { initial, loading, ready, error }

class TripLogState extends Equatable {
  const TripLogState({
    this.status = TripLogStatus.initial,
    this.log,
    this.message,
  });

  final TripLogStatus status;
  final TripLog? log;

  /// Message d'erreur du dernier chargement (le carnet en cache reste
  /// affiché s'il existe).
  final String? message;

  bool get isLoading => status == TripLogStatus.loading;

  TripLogState copyWith({
    TripLogStatus? status,
    TripLog? log,
    String? message,
  }) {
    return TripLogState(
      status: status ?? this.status,
      log: log ?? this.log,
      message: message,
    );
  }

  @override
  List<Object?> get props => <Object?>[status, log, message];
}

/// Carnet de bord du départ affiché (chauffeur et convoyeur) : affiche
/// d'abord le cache local (instantané, hors ligne compris) puis la
/// version serveur.
class TripLogCubit extends Cubit<TripLogState> {
  TripLogCubit(this._repository) : super(const TripLogState());

  final TripLogRepository _repository;
  String? _departureUuid;

  Future<void> load(String departureUuid) async {
    _departureUuid = departureUuid;
    final TripLog cached = await _repository.cached(departureUuid);
    if (isClosed) return;
    emit(state.copyWith(status: TripLogStatus.loading, log: cached));

    final Result<TripLog> result = await _repository.load(departureUuid);
    if (isClosed) return;
    result.when(
      onSuccess: (TripLog log) =>
          emit(state.copyWith(status: TripLogStatus.ready, log: log)),
      onFailure: (Failure failure) => emit(state.copyWith(
        status: TripLogStatus.error,
        message: failure.message,
      )),
    );
  }

  Future<void> refresh() async {
    final String? departureUuid = _departureUuid;
    if (departureUuid != null) await load(departureUuid);
  }

  Future<Result<TripLog>> recordOdometer(OdometerKind kind, int valueKm) async {
    final String? departureUuid = _departureUuid;
    if (departureUuid == null) {
      return const Result<TripLog>.failure(
          ValidationFailure('Aucun départ sélectionné.'));
    }
    final Result<TripLog> result = await _repository.recordOdometer(
      departureUuid: departureUuid,
      kind: kind,
      valueKm: valueKm,
    );
    if (!isClosed) {
      result.when(
        onSuccess: (TripLog log) =>
            emit(state.copyWith(status: TripLogStatus.ready, log: log)),
        onFailure: (_) {},
      );
    }
    return result;
  }
}
