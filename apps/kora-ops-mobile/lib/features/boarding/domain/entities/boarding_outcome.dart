import 'package:equatable/equatable.dart';
import 'boarding_outcome_status.dart';

/// Résultat d'une tentative de scan/validation d'embarquement — que ce
/// résultat vienne du serveur (`/ops/boarding`) ou d'une décision 100 %
/// locale (anti-double-scan, file hors ligne).
class BoardingOutcome extends Equatable {
  const BoardingOutcome({
    required this.status,
    required this.message,
    this.ticketUuid,
    this.operationUuid,
  });

  final BoardingOutcomeStatus status;
  final String message;

  /// `null` pour `ticketUnknown` (le serveur n'a pas pu résoudre de billet
  /// — voir `BoardingTicketUnknownException`) et pour un échec technique
  /// avant toute résolution.
  final String? ticketUuid;

  final String? operationUuid;

  @override
  List<Object?> get props =>
      <Object?>[status, message, ticketUuid, operationUuid];
}
