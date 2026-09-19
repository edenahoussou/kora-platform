part of 'border_manifest_print_cubit.dart';

enum BorderManifestPrintPhase {
  /// Lecture de l'état persisté (job en cours ?) et validation du manifeste.
  loading,

  /// Manifeste complet, aucune impression en attente : prêt à imprimer.
  ready,

  /// Manifeste refusé (incomplet, départ non international…) — voir
  /// [BorderManifestPrintState.issues].
  refused,

  /// Un exemplaire est en cours d'impression.
  printing,

  /// L'app a été interrompue (crash, redémarrage) pendant l'impression d'un
  /// exemplaire : on ne sait pas s'il est sorti. Le convoyeur tranche.
  awaitingConfirmation,

  /// Arrêtée sur une erreur ou une interruption : reprise possible.
  interrupted,

  /// Tous les exemplaires demandés sont confirmés imprimés.
  completed,
}

/// État d'un exemplaire, tel qu'affiché à l'écran (« afficher l'état de
/// chaque impression »).
enum BorderCopyStatus {
  /// Pas encore envoyé à l'imprimante.
  pending,

  /// En cours d'impression.
  printing,

  /// Confirmé imprimé.
  printed,

  /// Erreur matérielle : sera réimprimé à la reprise.
  failed,

  /// Résultat inconnu (interruption) : à confirmer par le convoyeur.
  unconfirmed,
}

class BorderManifestPrintState extends Equatable {
  const BorderManifestPrintState({
    this.phase = BorderManifestPrintPhase.loading,
    this.payload,
    this.issues = const <String>[],
    this.job,
    this.printingCopy,
    this.message,
    this.requestedCopies = kBorderManifestCopies,
  });

  final BorderManifestPrintPhase phase;

  /// Document prêt à imprimer — `null` tant que le manifeste est refusé.
  final BorderManifestPayload? payload;

  /// Raisons du refus (phase [BorderManifestPrintPhase.refused]).
  final List<String> issues;

  final BorderManifestPrintJob? job;

  /// Exemplaire en cours d'impression (phase `printing`).
  final int? printingCopy;

  /// Message à afficher (erreur matérielle, constat à faire…).
  final String? message;

  final int requestedCopies;

  int get copiesTotal => job?.requestedCopies ?? requestedCopies;

  int get copiesConfirmed => job?.confirmedCopies ?? 0;

  bool get isBusy =>
      phase == BorderManifestPrintPhase.loading ||
      phase == BorderManifestPrintPhase.printing;

  /// État de chaque exemplaire, dans l'ordre (1 … N).
  List<BorderCopyStatus> get copyStatuses {
    final BorderManifestPrintJob? current = job;

    return List<BorderCopyStatus>.generate(copiesTotal, (int index) {
      final int copy = index + 1;
      if (current == null) return BorderCopyStatus.pending;
      if (copy <= current.confirmedCopies) return BorderCopyStatus.printed;
      if (phase == BorderManifestPrintPhase.printing && copy == printingCopy) {
        return BorderCopyStatus.printing;
      }
      if (current.inFlightCopy == copy &&
          phase == BorderManifestPrintPhase.awaitingConfirmation) {
        return BorderCopyStatus.unconfirmed;
      }
      if (phase == BorderManifestPrintPhase.interrupted &&
          copy == current.confirmedCopies + 1 &&
          message != null) {
        return BorderCopyStatus.failed;
      }
      return BorderCopyStatus.pending;
    });
  }

  @override
  List<Object?> get props => <Object?>[
        phase,
        payload,
        issues,
        job,
        printingCopy,
        message,
        requestedCopies,
      ];
}
