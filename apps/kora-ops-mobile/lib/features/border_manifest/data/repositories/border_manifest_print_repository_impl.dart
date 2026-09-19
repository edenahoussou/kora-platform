import 'package:uuid/uuid.dart';

import '../../domain/entities/border_manifest_print_job.dart';
import '../../domain/repositories/border_manifest_print_repository.dart';
import '../datasources/border_manifest_print_local_datasource.dart';

/// Machine à états des impressions du manifeste frontière (MEZ-94).
///
/// C'est ici — et pas dans l'écran — que l'invariant « jamais d'exemplaire
/// en trop » est protégé : chaque transition vérifie l'état persisté et lève
/// une [StateError] si elle n'est pas permise.
///
/// ```
///            createJob
///               │
///     ┌─────────▼─────────┐  markCopyStarted(n)   ┌───────────────┐
///     │ inProgress        ├──────────────────────▶│ inProgress    │
///     │ in_flight = null  │                       │ in_flight = n │
///     └─────────▲─────────┘                       └───┬───────┬───┘
///               │ markCopyConfirmed(n) (reste des     │       │ markCopyFailed(n)
///               │ exemplaires)                        │       ▼
///               └─────────────────────────────────────┘  ┌─────────────┐
///                       markCopyConfirmed(dernier)         │ interrupted │
///                              │                            └──────┬──────┘
///                              ▼                                   │ markCopyStarted(n) (reprise)
///                        ┌───────────┐                             ▼
///                        │ completed │                        (reprend en n)
///                        └───────────┘
/// ```
///
/// Un crash pendant l'impression laisse `in_flight = n` : au redémarrage,
/// `resolveInFlightCopy` (constat du convoyeur) tranche — jamais
/// l'application seule.
class BorderManifestPrintRepositoryImpl
    implements BorderManifestPrintRepository {
  BorderManifestPrintRepositoryImpl(
    this._local, {
    DateTime Function()? now,
    String Function()? newUuid,
  })  : _now = now ?? DateTime.now,
        _newUuid = newUuid ?? (() => const Uuid().v4());

  final BorderManifestPrintLocalDataSource _local;
  final DateTime Function() _now;
  final String Function() _newUuid;

  @override
  Future<BorderManifestPrintJob?> findUnfinishedJob(String departureUuid) =>
      _local.findUnfinished(departureUuid);

  @override
  Future<BorderManifestPrintJob?> findLastJob(String departureUuid) =>
      _local.findLast(departureUuid);

  @override
  Future<BorderManifestPrintJob> createJob({
    required String departureUuid,
    required int requestedCopies,
    String? manifestVersion,
    String? userUuid,
    String? terminalSerial,
  }) async {
    if (requestedCopies < 1) {
      throw ArgumentError.value(
        requestedCopies,
        'requestedCopies',
        'Au moins un exemplaire est requis.',
      );
    }

    // Reprise, pas doublon : une demande non terminée existe déjà pour ce
    // départ — on la renvoie au lieu d'en ouvrir une seconde qui
    // repartirait de zéro et réimprimerait des exemplaires déjà sortis.
    final BorderManifestPrintJob? existing =
        await _local.findUnfinished(departureUuid);
    if (existing != null) return existing;

    final DateTime now = _now();
    final BorderManifestPrintJob job = BorderManifestPrintJob(
      jobUuid: _newUuid(),
      departureUuid: departureUuid,
      requestedCopies: requestedCopies,
      confirmedCopies: 0,
      status: BorderManifestPrintJobStatus.inProgress,
      manifestVersion: manifestVersion,
      userUuid: userUuid,
      terminalSerial: terminalSerial,
      createdAt: now,
      updatedAt: now,
    );
    await _local.insert(job);
    return job;
  }

  @override
  Future<BorderManifestPrintJob> markCopyStarted(
    String jobUuid,
    int copyNumber,
  ) async {
    final BorderManifestPrintJob job = await _require(jobUuid);

    if (job.isCompleted) {
      throw StateError('Tous les exemplaires de ce job sont déjà imprimés.');
    }
    if (job.inFlightCopy != null) {
      throw StateError(
        'L\'exemplaire ${job.inFlightCopy} est déjà en cours : son résultat '
        'doit être connu avant d\'en démarrer un autre.',
      );
    }
    if (copyNumber > job.requestedCopies) {
      throw StateError(
        'Exemplaire $copyNumber au-delà des ${job.requestedCopies} demandés.',
      );
    }
    if (copyNumber != job.confirmedCopies + 1) {
      throw StateError(
        'Seul l\'exemplaire ${job.confirmedCopies + 1} peut être démarré '
        '(demandé : $copyNumber).',
      );
    }

    return _save(job.copyWith(
      inFlightCopy: copyNumber,
      status: BorderManifestPrintJobStatus.inProgress,
      lastError: null,
      updatedAt: _now(),
    ));
  }

  @override
  Future<BorderManifestPrintJob> markCopyConfirmed(
    String jobUuid,
    int copyNumber,
  ) async {
    final BorderManifestPrintJob job = await _require(jobUuid);
    _requireInFlight(job, copyNumber);
    return _save(_confirmed(job, copyNumber));
  }

  @override
  Future<BorderManifestPrintJob> markCopyFailed(
    String jobUuid,
    int copyNumber,
    String message,
  ) async {
    final BorderManifestPrintJob job = await _require(jobUuid);
    _requireInFlight(job, copyNumber);

    return _save(job.copyWith(
      inFlightCopy: null,
      status: BorderManifestPrintJobStatus.interrupted,
      lastError: message,
      updatedAt: _now(),
    ));
  }

  @override
  Future<BorderManifestPrintJob> resolveInFlightCopy(
    String jobUuid, {
    required bool printed,
  }) async {
    final BorderManifestPrintJob job = await _require(jobUuid);
    final int? inFlight = job.inFlightCopy;
    if (inFlight == null) {
      throw StateError('Aucun exemplaire en attente de confirmation.');
    }

    if (printed) return _save(_confirmed(job, inFlight));

    return _save(job.copyWith(
      inFlightCopy: null,
      status: BorderManifestPrintJobStatus.interrupted,
      lastError: null,
      updatedAt: _now(),
    ));
  }

  // ---------------------------------------------------------------------

  BorderManifestPrintJob _confirmed(BorderManifestPrintJob job, int copy) {
    final bool allDone = copy >= job.requestedCopies;
    return job.copyWith(
      confirmedCopies: copy,
      inFlightCopy: null,
      lastError: null,
      status: allDone
          ? BorderManifestPrintJobStatus.completed
          : BorderManifestPrintJobStatus.inProgress,
      updatedAt: _now(),
    );
  }

  void _requireInFlight(BorderManifestPrintJob job, int copyNumber) {
    if (job.inFlightCopy != copyNumber) {
      throw StateError(
        'L\'exemplaire $copyNumber n\'est pas en cours '
        '(en cours : ${job.inFlightCopy}).',
      );
    }
  }

  Future<BorderManifestPrintJob> _require(String jobUuid) async {
    final BorderManifestPrintJob? job = await _local.find(jobUuid);
    if (job == null) {
      throw StateError('Job d\'impression introuvable : $jobUuid.');
    }
    return job;
  }

  Future<BorderManifestPrintJob> _save(BorderManifestPrintJob job) async {
    await _local.update(job);
    return job;
  }
}
