import 'package:equatable/equatable.dart';

/// Une ligne de `boarding_operations` (sqlite) pas encore synchronisée —
/// voir `BoardingRepository.listPending`, utilisée par `SyncRepository`
/// pour construire un lot `POST /ops/sync/batches` (MEZ-88).
class PendingBoardingOperation extends Equatable {
  const PendingBoardingOperation({
    required this.operationUuid,
    required this.departureUuid,
    this.qrPayload,
    this.shortCode,
    required this.localRecordedAt,
  });

  final String operationUuid;
  final String departureUuid;
  final String? qrPayload;
  final String? shortCode;
  final DateTime localRecordedAt;

  @override
  List<Object?> get props => <Object?>[
        operationUuid,
        departureUuid,
        qrPayload,
        shortCode,
        localRecordedAt,
      ];
}
