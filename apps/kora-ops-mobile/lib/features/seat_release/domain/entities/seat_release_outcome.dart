import 'package:equatable/equatable.dart';
import 'seat_release_outcome_status.dart';

/// Résultat d'une tentative de libération de siège (descente anticipée
/// ou no-show individuel) — que ce résultat vienne du serveur ou d'une
/// décision 100 % locale (anti-doublon, file hors ligne).
class SeatReleaseOutcome extends Equatable {
  const SeatReleaseOutcome({
    required this.status,
    required this.message,
    this.recommendedAction,
    this.ticketUuid,
    this.operationUuid,
  });

  final SeatReleaseOutcomeStatus status;
  final String message;

  /// Action suggérée à l'agent (voir `SeatReleaseFunctionalCode.
  /// recommendedAction` côté backend) — affichée sous le message quand
  /// disponible.
  final String? recommendedAction;

  final String? ticketUuid;
  final String? operationUuid;

  @override
  List<Object?> get props =>
      <Object?>[status, message, recommendedAction, ticketUuid, operationUuid];
}
