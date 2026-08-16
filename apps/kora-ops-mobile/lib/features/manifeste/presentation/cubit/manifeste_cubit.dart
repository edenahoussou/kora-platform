import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../ops/data/mock_ops_data_source.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/domain/entities/pending_operation.dart';
import '../../../synchro/presentation/cubit/sync_cubit.dart';

part 'manifeste_state.dart';

/// Charge et met à jour la liste des passagers du départ courant. Écoute
/// [SelectedDepartCubit] pour se recharger automatiquement quand l'agent
/// change de départ (que ce soit depuis `AccueilPage` ou le sélecteur de
/// l'en-tête du Manifeste — voir document.md § Sélection du départ).
class ManifesteCubit extends Cubit<ManifesteState> {
  ManifesteCubit(
      {required SelectedDepartCubit selectedDepartCubit,
      required SyncCubit syncCubit})
      : _syncCubit = syncCubit,
        super(const ManifesteState()) {
    _subscription = selectedDepartCubit.stream.listen(_onDepartChanged);
    if (selectedDepartCubit.state != null) {
      _onDepartChanged(selectedDepartCubit.state);
    }
  }

  final SyncCubit _syncCubit;
  late final dynamic _subscription;

  Depart? _depart;

  Future<void> _onDepartChanged(Depart? depart) async {
    _depart = depart;
    if (depart == null) {
      emit(const ManifesteState());
      return;
    }
    await load();
  }

  Future<void> load() async {
    final Depart? depart = _depart;
    if (depart == null) {
      emit(const ManifesteState());
      return;
    }
    emit(state.copyWith(status: ManifesteStatus.loading));
    await Future<void>.delayed(const Duration(milliseconds: 700));
    try {
      final List<Passenger> passengers =
          MockOpsDataSource.passengersFor(depart.id);
      emit(state.copyWith(
          status: ManifesteStatus.success, passengers: passengers));
    } catch (_) {
      emit(state.copyWith(
          status: ManifesteStatus.error,
          errorMessage: 'Impossible de charger le manifeste.'));
    }
  }

  /// Met à jour le statut d'un passager (validation/refus de scan,
  /// descente anticipée…) et journalise l'opération dans la file de
  /// synchronisation — voir document.md § Synchro.
  void updateStatut(String passengerId, BoardingStatus statut) {
    final List<Passenger> updated = state.passengers
        .map((Passenger p) =>
            p.id == passengerId ? p.copyWith(statut: statut) : p)
        .toList();
    emit(state.copyWith(passengers: updated));

    final Passenger passenger =
        updated.firstWhere((Passenger p) => p.id == passengerId);
    _syncCubit.enqueue(
      PendingOperation(
        id: '${passengerId}_${DateTime.now().microsecondsSinceEpoch}',
        label:
            '${statut.label} — ${passenger.nomComplet} (siège ${passenger.siege})',
        createdAtLabel: 'À l\'instant',
      ),
    );
  }

  Passenger? byId(String id) {
    for (final Passenger passenger in state.passengers) {
      if (passenger.id == id) return passenger;
    }
    return null;
  }

  @override
  Future<void> close() {
    // ignore: avoid_dynamic_calls
    _subscription.cancel();
    return super.close();
  }
}
