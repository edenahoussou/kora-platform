import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:uuid/uuid.dart';
import '../../../boarding/domain/entities/boarding_outcome_status.dart';
import '../../../boarding/domain/repositories/boarding_repository.dart';
import '../../../manifeste/presentation/cubit/manifeste_cubit.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../printing/domain/usecases/scan_barcode.dart';
import '../../../terminal/domain/entities/terminal_activity_type.dart';
import '../../../terminal/domain/repositories/terminal_repository.dart';

part 'scan_boarding_state.dart';

/// Orchestration du scan d'embarquement (MEZ-91) : déclenché par
/// l'adaptateur matériel P501 (ou son Mock, MEZ-10) — jamais par la caméra
/// standard du téléphone, voir critère d'acceptation MEZ-91 — ou par la
/// recherche manuelle d'un code court (QR illisible). Dans les deux cas,
/// le contenu scanné/saisi est envoyé tel quel au contrat de boarding
/// (`BoardingRepository.scan`), qui gère lui-même l'anti-double-scan
/// local, l'appel réseau et la file hors ligne — ce cubit ne fait
/// qu'orchestrer l'UI et refléter le résultat sur le manifeste local.
class ScanBoardingCubit extends Cubit<ScanBoardingState> {
  ScanBoardingCubit({
    required ScanBarcode scanBarcode,
    required BoardingRepository boardingRepository,
    required ManifesteCubit manifesteCubit,
    required SelectedDepartCubit selectedDepartCubit,
    required TerminalRepository terminalRepository,
    Uuid? uuid,
    DateTime Function()? now,
  })  : _scanBarcode = scanBarcode,
        _boardingRepository = boardingRepository,
        _manifesteCubit = manifesteCubit,
        _selectedDepartCubit = selectedDepartCubit,
        _terminalRepository = terminalRepository,
        _uuid = uuid ?? const Uuid(),
        _now = now ?? DateTime.now,
        super(const ScanBoardingState());

  final ScanBarcode _scanBarcode;
  final BoardingRepository _boardingRepository;
  final ManifesteCubit _manifesteCubit;
  final SelectedDepartCubit _selectedDepartCubit;
  final TerminalRepository _terminalRepository;
  final Uuid _uuid;
  final DateTime Function() _now;

  /// Déclenché par le bouton matériel/l'API scanner du P501 (Mock ou SDK
  /// réel — MEZ-10). Ignoré si un scan est déjà en cours de traitement.
  Future<void> scan() async {
    if (state.status == ScanBoardingStatus.scanning) return;

    emit(state.copyWith(status: ScanBoardingStatus.scanning, message: null));
    final scanResult = await _scanBarcode();

    await scanResult.when(
      onSuccess: (code) => _submit(qrPayload: code),
      onFailure: (failure) async {
        emit(state.copyWith(
          status: ScanBoardingStatus.erreur,
          message: failure.message,
        ));
      },
    );
  }

  /// Filtre le manifeste déjà chargé par code court (QR illisible) — voir
  /// `ManifesteCubit.searchByShortCode`, fonctionne hors ligne.
  void searchManual(String query) {
    emit(state.copyWith(
      manualQuery: query,
      manualResults: _manifesteCubit.searchByShortCode(query),
    ));
  }

  /// Soumet le billet choisi dans les résultats de la recherche manuelle.
  Future<void> submitManual(Passenger passenger) async {
    emit(state.copyWith(
      status: ScanBoardingStatus.scanning,
      message: null,
      manualResults: const <Passenger>[],
      manualQuery: '',
    ));
    await _submit(shortCode: passenger.ticketId);
  }

  Future<void> _submit({String? qrPayload, String? shortCode}) async {
    final String? departureUuid = _selectedDepartCubit.state?.id;
    if (departureUuid == null) {
      emit(state.copyWith(
        status: ScanBoardingStatus.erreur,
        message: 'Sélectionnez un départ à gérer avant de scanner.',
      ));
      return;
    }

    final String code = qrPayload ?? shortCode ?? '';
    final String operationUuid = _uuid.v4();
    final DateTime localRecordedAt = _now();

    final result = await _boardingRepository.scan(
      departureUuid: departureUuid,
      qrPayload: qrPayload,
      shortCode: shortCode,
      operationUuid: operationUuid,
      localRecordedAt: localRecordedAt,
    );

    result.when(
      onSuccess: (outcome) {
        if (outcome.status == BoardingOutcomeStatus.valid &&
            outcome.ticketUuid != null) {
          _manifesteCubit.markBoarded(outcome.ticketUuid!);
        }
        emit(state.copyWith(
          status: ScanBoardingStatus.fromOutcome(outcome.status),
          lastCode: code,
          message: outcome.message,
          matchedPassenger: outcome.ticketUuid == null
              ? null
              : _manifesteCubit.byId(outcome.ticketUuid!),
        ));
      },
      onFailure: (failure) {
        emit(state.copyWith(
          status: ScanBoardingStatus.erreur,
          lastCode: code,
          message: failure.message,
        ));
      },
    );

    // Journalisation terminal (voir MEZ-91 "journaliser le terminal et
    // l'heure locale de chaque opération") — best-effort, jamais bloquant
    // ni visible en cas d'échec : l'audit détaillé par opération vit déjà
    // dans `boarding_operations` (sqlite), cet appel est un journal
    // serveur complémentaire.
    unawaited(_terminalRepository.logActivity(
      TerminalActivityType.scan,
      context: <String, dynamic>{
        'departure_uuid': departureUuid,
        'operation_uuid': operationUuid,
        'result_status': state.status.name,
      },
    ));
  }

  void reset() => emit(const ScanBoardingState());
}
