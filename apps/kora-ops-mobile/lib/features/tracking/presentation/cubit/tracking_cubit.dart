import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../terminal/data/datasources/terminal_local_datasource.dart';
import '../../domain/entities/tracking_availability.dart';
import '../../domain/entities/tracking_runtime_status.dart';
import '../../domain/entities/tracking_stop_reason.dart';
import '../../domain/repositories/tracking_repository.dart';

part 'tracking_state.dart';

/// Démarre/arrête le suivi de position du convoyeur en fonction de la
/// progression du départ sélectionné (MEZ-101) — écoute
/// [SelectedDepartCubit] plutôt que d'être piloté explicitement par
/// chaque écran : `departed` → démarre, `arrived`/`cancelled` ou
/// désélection → arrête. Même conception que `SyncCubit` (singleton,
/// écoute continue, voir `injection.dart`).
///
/// Ne mesure jamais de position elle-même — [_repository] délègue tout au
/// service premier plan (`TrackingTaskHandler`, isolate séparé, voir sa
/// docstring). Ce cubit ne fait que refléter l'état pour l'UI
/// (`TrackingStatusBanner`) et réagir aux transitions de départ.
class TrackingCubit extends Cubit<TrackingState> {
  TrackingCubit(
    this._repository,
    this._terminalLocalDataSource,
    SelectedDepartCubit selectedDepartCubit,
  ) : super(const TrackingState()) {
    _selectedDepartSubscription =
        selectedDepartCubit.stream.listen(_onDepartureChanged);
    _statusSubscription = _repository.statusStream.listen(_onRuntimeStatus);
  }

  final TrackingRepository _repository;
  final TerminalLocalDataSource _terminalLocalDataSource;

  late final StreamSubscription<Depart?> _selectedDepartSubscription;
  late final StreamSubscription<TrackingRuntimeStatus> _statusSubscription;

  /// À appeler une fois au démarrage (voir `SplashPage`) : relit la
  /// disponibilité (permission/GPS/précision, sans boîte de dialogue —
  /// voir `TrackingRepository.checkAvailability`) et reprend un suivi
  /// resté actif après un crash ou un redémarrage à froid.
  Future<void> bootstrap() async {
    await _repository.bootstrap();
    final TrackingAvailability availability =
        await _repository.checkAvailability();
    final int pendingCount = await _repository.totalPendingCount();
    emit(
        state.copyWith(availability: availability, pendingCount: pendingCount));
  }

  /// Demande activement la permission de localisation (boîte de dialogue
  /// système) — à appeler depuis une action explicite du convoyeur (ex.
  /// bouton "Autoriser" sur l'état bloquant affiché par
  /// `TrackingStatusBanner`), jamais automatiquement.
  Future<TrackingAvailability> requestPermission() async {
    final TrackingAvailability availability =
        await _repository.requestAvailability();
    emit(state.copyWith(availability: availability));
    return availability;
  }

  Future<void> _onDepartureChanged(Depart? depart) async {
    if (depart == null) {
      if (state.isActive) await _stop(TrackingStopReason.manualClose);
      return;
    }

    switch (depart.status) {
      case 'departed':
        await _start(depart);
      case 'arrived':
        await _stop(TrackingStopReason.arrived);
      case 'cancelled':
        await _stop(TrackingStopReason.cancelled);
      default:
        // 'scheduled'/'boarding'/null : rien à faire, le suivi ne
        // concerne que le bus effectivement en circulation.
        break;
    }
  }

  Future<void> _start(Depart depart) async {
    final String? terminalSerial =
        await _terminalLocalDataSource.readSerialNumber();
    if (terminalSerial == null || terminalSerial.isEmpty) return;

    final TrackingAvailability availability = await _repository.start(
      departureUuid: depart.id,
      terminalSerial: terminalSerial,
    );

    emit(state.copyWith(
      isActive: availability == TrackingAvailability.ready,
      availability: availability,
    ));
  }

  Future<void> _stop(TrackingStopReason reason) async {
    await _repository.stop(reason: reason);
    emit(state.copyWith(
      isActive: false,
      isOnline: true,
      isSyncing: false,
      pendingCount: 0,
    ));
  }

  void _onRuntimeStatus(TrackingRuntimeStatus status) {
    emit(state.copyWith(
      isActive: true,
      isOnline: status.isOnline,
      isSyncing: status.isSyncing,
      pendingCount: status.pendingCount,
      lastPositionAt: status.lastPositionAt,
    ));
  }

  @override
  Future<void> close() {
    unawaited(_selectedDepartSubscription.cancel());
    unawaited(_statusSubscription.cancel());
    return super.close();
  }
}
