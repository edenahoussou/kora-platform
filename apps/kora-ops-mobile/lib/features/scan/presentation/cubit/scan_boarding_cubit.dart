import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../manifeste/presentation/cubit/manifeste_cubit.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../printing/domain/usecases/scan_barcode.dart';

part 'scan_boarding_state.dart';

/// Orchestration du scan d'embarquement. Deux sources alimentent le même
/// traitement ([_processCode]) :
///
/// - **Caméra en direct** (`ScanBoardingPage`, `mobile_scanner`) : chaque
///   QR détecté par la caméra du téléphone est transmis directement via
///   [handleDetectedCode] — c'est le chemin principal sur un téléphone de
///   test/développement.
/// - **Adaptateur matériel** [ScanBarcode] (MEZ-10 — Mock ou scanner dédié
///   P501 via MethodChannel) via [scan] — utilisé sur le terminal P501 en
///   production, ou pour un test manuel sans caméra.
///
/// Le SDK matériel/la caméra ne connaissant pas nos identifiants de billets
/// mockés, la correspondance "valide/refusé" se fait contre le **prochain
/// passager en attente du manifeste courant**, pas contre le contenu
/// littéral du code — voir document.md § Scan boarding.
class ScanBoardingCubit extends Cubit<ScanBoardingState> {
  ScanBoardingCubit(
      {required ScanBarcode scanBarcode,
      required ManifesteCubit manifesteCubit})
      : _scanBarcode = scanBarcode,
        _manifesteCubit = manifesteCubit,
        super(const ScanBoardingState());

  final ScanBarcode _scanBarcode;
  final ManifesteCubit _manifesteCubit;

  /// Déclenché par la caméra en direct (`mobile_scanner`) dès qu'un QR est
  /// détecté. Ignoré si un scan est déjà en cours de traitement, pour
  /// éviter les détections multiples du même code pendant l'animation de
  /// résultat.
  void handleDetectedCode(String code) {
    if (state.status == ScanBoardingStatus.scanning) return;
    emit(state.copyWith(status: ScanBoardingStatus.scanning, message: null));
    _processCode(code);
  }

  /// Déclenché par le bouton manuel (adaptateur P501/Mock, MEZ-10).
  Future<void> scan() async {
    emit(state.copyWith(status: ScanBoardingStatus.scanning, message: null));
    final result = await _scanBarcode();

    result.when(
      onSuccess: _processCode,
      onFailure: (failure) {
        emit(state.copyWith(
            status: ScanBoardingStatus.erreur, message: failure.message));
      },
    );
  }

  void _processCode(String code) {
    final List<Passenger> enAttente = _manifesteCubit.state.passengers
        .where((Passenger p) => p.statut == BoardingStatus.enAttente)
        .toList();

    if (enAttente.isEmpty) {
      emit(
        state.copyWith(
          status: ScanBoardingStatus.refuse,
          lastScannedCode: code,
          message: 'Billet non reconnu ou déjà traité pour ce départ.',
        ),
      );
      return;
    }

    final Passenger passenger = enAttente.first;
    _manifesteCubit.updateStatut(passenger.id, BoardingStatus.embarque);
    emit(
      state.copyWith(
        status: ScanBoardingStatus.valide,
        lastScannedCode: code,
        matchedPassenger: passenger.copyWith(statut: BoardingStatus.embarque),
      ),
    );
  }

  void reset() => emit(const ScanBoardingState());
}
