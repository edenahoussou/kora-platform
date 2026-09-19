import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/exceptions/app_exceptions.dart';
import '../../../printing/domain/entities/border_manifest_payload.dart';
import '../../../printing/domain/services/thermal_printer.dart';
import '../../../terminal/domain/entities/terminal.dart';
import '../../../terminal/domain/entities/terminal_activity_type.dart';
import '../../../terminal/domain/repositories/terminal_repository.dart';
import '../../domain/entities/border_manifest_print_job.dart';
import '../../domain/repositories/border_manifest_print_repository.dart';
import '../../domain/services/border_manifest_builder.dart';

part 'border_manifest_print_state.dart';

/// Impression du manifeste frontière sur la P501 (MEZ-94) : 3 exemplaires
/// sur commande explicite du convoyeur, état de chaque exemplaire, reprise
/// sans doublon.
///
/// ### Garanties (« pas d'exemplaire de trop en silence »)
///
/// 1. **Jamais deux impressions en même temps** ([_running]) : un double
///    appui sur le bouton ne lance rien de plus.
/// 2. **Persisté avant l'envoi** : chaque exemplaire est marqué « en cours »
///    en base *avant* l'appel à l'imprimante, et « confirmé » seulement
///    après sa confirmation — voir `BorderManifestPrintRepository`.
/// 3. **Reprise = suite, pas recommencement** : une demande non terminée
///    est toujours reprise là où elle s'est arrêtée ; [printCopies] ne crée
///    jamais un second job pour le même départ.
/// 4. **Jamais de décision à la place du convoyeur en cas de doute** : si
///    l'app a été interrompue pendant un exemplaire, on ne le réimprime pas
///    tout seul — [confirmInFlightCopy] enregistre le constat du convoyeur
///    (« il est sorti » / « il n'est pas sorti »).
/// 5. **Réimpression après un job terminé = geste explicite**
///    (`confirmReprint`), jamais automatique.
///
/// ### Journalisation
///
/// Chaque événement (exemplaire imprimé, exemplaire en échec, impression
/// terminée, constat après crash, refus) est journalisé via
/// [TerminalRepository.logActivity] avec le terminal, l'utilisateur,
/// l'heure, le nombre d'exemplaires et le résultat. Jamais de donnée
/// passager (nom, nationalité, n° de document) dans le journal.
class BorderManifestPrintCubit extends Cubit<BorderManifestPrintState> {
  BorderManifestPrintCubit({
    required ThermalPrinter printer,
    required BorderManifestPrintRepository repository,
    required TerminalRepository terminalRepository,
    required Future<String?> Function() currentUserUuid,
    DateTime Function()? now,
    int copies = kBorderManifestCopies,
  })  : _printer = printer,
        _repository = repository,
        _terminalRepository = terminalRepository,
        _currentUserUuid = currentUserUuid,
        _now = now ?? DateTime.now,
        _copies = copies,
        super(BorderManifestPrintState(requestedCopies: copies));

  final ThermalPrinter _printer;
  final BorderManifestPrintRepository _repository;
  final TerminalRepository _terminalRepository;
  final Future<String?> Function() _currentUserUuid;
  final DateTime Function() _now;
  final int _copies;

  BorderManifestInput? _input;
  bool _running = false;

  /// Charge l'écran : valide le manifeste et relit l'état persisté (une
  /// impression interrompue, un exemplaire à confirmer…). À rappeler pour
  /// resynchroniser l'écran.
  Future<void> load(BorderManifestInput input) async {
    _input = input;
    _emit(BorderManifestPrintState(requestedCopies: _copies));

    final BorderManifestBuild build =
        BorderManifestBuilder.build(input, now: _now());
    final BorderManifestPrintJob? unfinished =
        await _repository.findUnfinishedJob(input.depart.id);

    if (unfinished != null) {
      _emit(_settled(unfinished, build));
      return;
    }

    if (!build.isReady) {
      _emit(BorderManifestPrintState(
        phase: BorderManifestPrintPhase.refused,
        issues: build.issues,
        job: await _repository.findLastJob(input.depart.id),
        requestedCopies: _copies,
      ));
      return;
    }

    final BorderManifestPrintJob? last =
        await _repository.findLastJob(input.depart.id);
    if (last != null && last.isCompleted) {
      _emit(BorderManifestPrintState(
        phase: BorderManifestPrintPhase.completed,
        payload: build.payload,
        job: last,
        requestedCopies: _copies,
      ));
      return;
    }

    _emit(BorderManifestPrintState(
      phase: BorderManifestPrintPhase.ready,
      payload: build.payload,
      requestedCopies: _copies,
    ));
  }

  /// Commande explicite « imprimer [kBorderManifestCopies] exemplaires ».
  ///
  /// - Reprend l'impression non terminée s'il y en a une (jamais un second
  ///   job) ; refuse de réimprimer par-dessus un exemplaire dont le
  ///   résultat est inconnu (il faut d'abord le constat du convoyeur).
  /// - Si les exemplaires ont déjà tous été imprimés, ne fait rien sauf si
  ///   [confirmReprint] est `true` (réimpression voulue).
  Future<void> printCopies({bool confirmReprint = false}) async {
    if (_running) return;
    final BorderManifestInput? input = _input;
    if (input == null) return;
    if (state.phase == BorderManifestPrintPhase.completed && !confirmReprint) {
      return;
    }

    final BorderManifestBuild build =
        BorderManifestBuilder.build(input, now: _now());
    if (!build.isReady) {
      await _log(
        departureUuid: input.depart.id,
        result: 'refused',
        copiesRequested: _copies,
        copiesConfirmed: 0,
        error: build.issues.join(' | '),
      );
      _emit(BorderManifestPrintState(
        phase: BorderManifestPrintPhase.refused,
        issues: build.issues,
        job: state.job,
        requestedCopies: _copies,
      ));
      return;
    }

    _running = true;
    try {
      final BorderManifestPrintJob? unfinished =
          await _repository.findUnfinishedJob(input.depart.id);

      if (unfinished != null && unfinished.needsConfirmation) {
        _emit(_settled(unfinished, build));
        return;
      }

      final BorderManifestPrintJob job = unfinished ??
          await _repository.createJob(
            departureUuid: input.depart.id,
            requestedCopies: _copies,
            manifestVersion: input.manifestVersion,
            userUuid: await _currentUserUuid(),
            terminalSerial: (await _terminalRepository.cachedTerminal())
                ?.serialNumber,
          );

      await _run(job, build.payload!);
    } on Object {
      // Incohérence d'état persisté (StateError du repository) ou panne
      // sqlite : on ne devine rien, on relit l'état réel et on le montre.
      await load(input);
      _emit(BorderManifestPrintState(
        phase: state.phase,
        payload: state.payload,
        issues: state.issues,
        job: state.job,
        printingCopy: state.printingCopy,
        message: 'Impossible de poursuivre l\'impression. Vérifiez les '
            'exemplaires sortis, puis réessayez.',
        requestedCopies: _copies,
      ));
    } finally {
      _running = false;
    }
  }

  /// Reprise d'une impression interrompue : imprime uniquement les
  /// exemplaires restants (jamais plus que demandé).
  Future<void> resume() => printCopies();

  /// Constat du convoyeur après une interruption pendant un exemplaire :
  /// [printed] `true` = « l'exemplaire est sorti » (compté, pas réimprimé),
  /// `false` = « il n'est pas sorti » (sera imprimé à la reprise).
  Future<void> confirmInFlightCopy({required bool printed}) async {
    if (_running) return;
    final BorderManifestPrintJob? job = state.job;
    final BorderManifestInput? input = _input;
    if (job == null || input == null || !job.needsConfirmation) return;

    _running = true;
    try {
      final int copy = job.inFlightCopy!;
      final BorderManifestPrintJob updated =
          await _repository.resolveInFlightCopy(job.jobUuid, printed: printed);

      await _log(
        departureUuid: updated.departureUuid,
        jobUuid: updated.jobUuid,
        copyNumber: copy,
        result: printed ? 'interrupted_copy_printed' : 'interrupted_copy_lost',
        copiesRequested: updated.requestedCopies,
        copiesConfirmed: updated.confirmedCopies,
        manifestVersion: updated.manifestVersion,
      );

      final BorderManifestBuild build =
          BorderManifestBuilder.build(input, now: _now());
      final BorderManifestPrintState settled = _settled(updated, build);
      _emit(BorderManifestPrintState(
        phase: settled.phase,
        payload: settled.payload,
        issues: settled.issues,
        job: settled.job,
        message: updated.isCompleted
            ? null
            : printed
                ? 'Exemplaire $copy confirmé. Il reste '
                    '${updated.requestedCopies - updated.confirmedCopies} '
                    'exemplaire(s) à imprimer.'
                : 'L\'exemplaire $copy n\'est pas sorti : il sera imprimé à '
                    'la reprise.',
        requestedCopies: _copies,
      ));
    } finally {
      _running = false;
    }
  }

  // ---------------------------------------------------------------------

  Future<void> _run(
    BorderManifestPrintJob job,
    BorderManifestPayload payload,
  ) async {
    BorderManifestPrintJob current = job;

    while (current.nextCopy != null) {
      final int copy = current.nextCopy!;

      // Persisté AVANT l'appel matériel : si l'app est tuée pendant
      // l'impression, ce marqueur permet de ne pas perdre la trace de
      // l'exemplaire (voir `BorderManifestPrintJob.needsConfirmation`).
      current = await _repository.markCopyStarted(current.jobUuid, copy);
      _emit(BorderManifestPrintState(
        phase: BorderManifestPrintPhase.printing,
        payload: payload,
        job: current,
        printingCopy: copy,
        requestedCopies: _copies,
      ));

      String? failureMessage;
      PrinterFailureKind? failureKind;
      try {
        final bool printed = await _printer.printBorderManifest(
          payload,
          copyNumber: copy,
          totalCopies: current.requestedCopies,
        );
        if (!printed) {
          failureMessage = 'Impression refusée par le matériel.';
        }
      } on PrinterException catch (error) {
        failureMessage = error.userMessage;
        failureKind = error.kind;
      } on Object {
        failureMessage = 'Erreur inattendue pendant l\'impression.';
      }

      if (failureMessage == null) {
        current = await _repository.markCopyConfirmed(current.jobUuid, copy);
        await _log(
          departureUuid: current.departureUuid,
          jobUuid: current.jobUuid,
          copyNumber: copy,
          result: 'copy_printed',
          copiesRequested: current.requestedCopies,
          copiesConfirmed: current.confirmedCopies,
          manifestVersion: current.manifestVersion,
          passengerCount: payload.passengerCount,
        );
        continue;
      }

      current = await _repository.markCopyFailed(
        current.jobUuid,
        copy,
        failureMessage,
      );
      await _log(
        departureUuid: current.departureUuid,
        jobUuid: current.jobUuid,
        copyNumber: copy,
        result: 'copy_failed',
        copiesRequested: current.requestedCopies,
        copiesConfirmed: current.confirmedCopies,
        manifestVersion: current.manifestVersion,
        passengerCount: payload.passengerCount,
        error: failureMessage,
        errorKind: (failureKind ?? PrinterFailureKind.unknown).name,
      );
      _emit(BorderManifestPrintState(
        phase: BorderManifestPrintPhase.interrupted,
        payload: payload,
        job: current,
        message: failureMessage,
        requestedCopies: _copies,
      ));
      return;
    }

    await _log(
      departureUuid: current.departureUuid,
      jobUuid: current.jobUuid,
      copyNumber: current.confirmedCopies,
      result: 'completed',
      copiesRequested: current.requestedCopies,
      copiesConfirmed: current.confirmedCopies,
      manifestVersion: current.manifestVersion,
      passengerCount: payload.passengerCount,
    );
    _emit(BorderManifestPrintState(
      phase: BorderManifestPrintPhase.completed,
      payload: payload,
      job: current,
      requestedCopies: _copies,
    ));
  }

  /// État à afficher pour un job non terminé (ou qui vient de se
  /// terminer), d'après le manifeste courant.
  BorderManifestPrintState _settled(
    BorderManifestPrintJob job,
    BorderManifestBuild build,
  ) {
    if (job.isCompleted) {
      return BorderManifestPrintState(
        phase: BorderManifestPrintPhase.completed,
        payload: build.payload,
        job: job,
        requestedCopies: _copies,
      );
    }

    if (job.needsConfirmation) {
      return BorderManifestPrintState(
        phase: BorderManifestPrintPhase.awaitingConfirmation,
        payload: build.payload,
        issues: build.issues,
        job: job,
        message: 'L\'application s\'est arrêtée pendant l\'impression de '
            'l\'exemplaire ${job.inFlightCopy}. Est-il sorti de '
            'l\'imprimante ?',
        requestedCopies: _copies,
      );
    }

    if (!build.isReady) {
      return BorderManifestPrintState(
        phase: BorderManifestPrintPhase.refused,
        issues: build.issues,
        job: job,
        requestedCopies: _copies,
      );
    }

    return BorderManifestPrintState(
      phase: BorderManifestPrintPhase.interrupted,
      payload: build.payload,
      job: job,
      message: job.lastError ??
          'Impression interrompue : ${job.confirmedCopies}/'
              '${job.requestedCopies} exemplaire(s) confirmé(s).',
      requestedCopies: _copies,
    );
  }

  Future<void> _log({
    required String departureUuid,
    required String result,
    required int copiesRequested,
    required int copiesConfirmed,
    String? jobUuid,
    int? copyNumber,
    String? manifestVersion,
    int? passengerCount,
    String? error,
    String? errorKind,
  }) async {
    try {
      final Terminal? terminal = await _terminalRepository.cachedTerminal();
      await _terminalRepository.logActivity(
        TerminalActivityType.print,
        context: <String, dynamic>{
          'document': 'border_manifest',
          'mode': 'thermal',
          'result': result,
          'departure_uuid': departureUuid,
          if (jobUuid != null) 'job_uuid': jobUuid,
          if (copyNumber != null) 'copy_number': copyNumber,
          'copies_requested': copiesRequested,
          'copies_confirmed': copiesConfirmed,
          if (manifestVersion != null) 'manifest_version': manifestVersion,
          if (passengerCount != null) 'passenger_count': passengerCount,
          if (error != null) 'error': error,
          if (errorKind != null) 'error_kind': errorKind,
          'user_uuid': await _currentUserUuid(),
          'terminal_serial': terminal?.serialNumber,
          'recorded_at': _now().toIso8601String(),
        },
      );
    } on Object {
      // Journal best-effort (voir `TerminalRepository.logActivity`) : un
      // souci de journalisation ne doit jamais faire échouer l'impression.
    }
  }

  void _emit(BorderManifestPrintState next) {
    if (!isClosed) emit(next);
  }
}
