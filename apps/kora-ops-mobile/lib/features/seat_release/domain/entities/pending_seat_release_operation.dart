import 'package:equatable/equatable.dart';

/// Type d'opération de libération — miroir des deux valeurs pertinentes
/// de `SyncOperationType` côté backend (`seat_release` = descente
/// anticipée, `boarding.no_show` = no-show individuel). La clôture
/// no-show *groupée* (`POST /ops/seat-releases/no-show-closure`) n'a pas
/// d'équivalent dans la file de synchro : hors ligne, chaque billet non
/// contrôlé devient sa propre opération `noShow` individuelle — voir
/// `SeatReleaseRepository.closeNoShows`.
enum SeatReleaseType {
  earlyDropOff,
  noShow;

  /// Valeur exacte attendue par `operation_type` dans le lot de synchro.
  String get syncOperationType => switch (this) {
        SeatReleaseType.earlyDropOff => 'seat_release',
        SeatReleaseType.noShow => 'boarding.no_show',
      };
}

/// Une ligne de `seat_release_operations` (sqlite) pas encore
/// synchronisée — voir `SeatReleaseRepository.listPending`.
class PendingSeatReleaseOperation extends Equatable {
  const PendingSeatReleaseOperation({
    required this.operationUuid,
    required this.type,
    required this.departureUuid,
    required this.ticketUuid,
    this.actualStopUuid,
    required this.localRecordedAt,
  });

  final String operationUuid;
  final SeatReleaseType type;
  final String departureUuid;
  final String ticketUuid;

  /// `null` pour un no-show (voir docstring `SeatReleaseType`).
  final String? actualStopUuid;

  final DateTime localRecordedAt;

  @override
  List<Object?> get props => <Object?>[
        operationUuid,
        type,
        departureUuid,
        ticketUuid,
        actualStopUuid,
        localRecordedAt,
      ];
}
