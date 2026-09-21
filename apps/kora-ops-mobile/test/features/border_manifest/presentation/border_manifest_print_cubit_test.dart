import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/exceptions/app_exceptions.dart';
import 'package:kora_ops_mobile/features/border_manifest/data/repositories/border_manifest_print_repository_impl.dart';
import 'package:kora_ops_mobile/features/border_manifest/domain/entities/border_manifest_print_job.dart';
import 'package:kora_ops_mobile/features/border_manifest/presentation/cubit/border_manifest_print_cubit.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';
import 'package:kora_ops_mobile/features/printing/data/services/mock_thermal_printer.dart';
import 'package:kora_ops_mobile/features/printing/domain/entities/border_manifest_payload.dart';
import 'package:kora_ops_mobile/features/printing/domain/services/thermal_printer.dart';
import 'package:kora_ops_mobile/features/terminal/domain/entities/terminal.dart';
import 'package:kora_ops_mobile/features/terminal/domain/entities/terminal_activity_type.dart';
import 'package:kora_ops_mobile/features/terminal/domain/entities/terminal_status.dart';
import 'package:kora_ops_mobile/features/terminal/domain/repositories/terminal_repository.dart';
import 'package:mocktail/mocktail.dart';

import '../support/border_manifest_fixtures.dart';

class MockTerminalRepository extends Mock implements TerminalRepository {}

/// Imprimante dont la sortie est scriptée : `false` (le SDK refuse) ou une
/// exception quelconque (bug inattendu).
class ScriptedPrinter implements ThermalPrinter {
  ScriptedPrinter({this.result = false, this.error});

  bool result;
  Object? error;
  int calls = 0;

  @override
  Future<bool> printBorderManifest(
    BorderManifestPayload payload, {
    required int copyNumber,
    required int totalCopies,
  }) async {
    calls++;
    final Object? toThrow = error;
    if (toThrow != null) throw toThrow;
    return result;
  }

  @override
  Future<bool> printTicket(payload) async => true;
}

void main() {
  setUpAll(() {
    registerFallbackValue(TerminalActivityType.print);
  });

  late InMemoryBorderManifestPrintDataSource store;
  late BorderManifestPrintRepositoryImpl repository;
  late MockTerminalRepository terminals;
  late MockThermalPrinter printer;
  late List<Map<String, dynamic>> logs;
  final DateTime fixedNow = DateTime(2026, 8, 14, 7, 5);

  int uuidCounter = 0;
  DateTime clock = DateTime(2026, 8, 14, 7);

  BorderManifestPrintRepositoryImpl newRepository() =>
      BorderManifestPrintRepositoryImpl(
        store,
        now: () => clock = clock.add(const Duration(seconds: 1)),
        newUuid: () => 'job-${++uuidCounter}',
      );

  BorderManifestPrintCubit buildCubit([ThermalPrinter? thermalPrinter]) =>
      BorderManifestPrintCubit(
        printer: thermalPrinter ?? printer,
        repository: repository,
        terminalRepository: terminals,
        currentUserUuid: () async => 'user-42',
        now: () => fixedNow,
      );

  setUp(() {
    store = InMemoryBorderManifestPrintDataSource();
    uuidCounter = 0;
    clock = DateTime(2026, 8, 14, 7);
    repository = newRepository();
    printer = MockThermalPrinter(simulatedDelay: Duration.zero);
    terminals = MockTerminalRepository();
    logs = <Map<String, dynamic>>[];

    when(() => terminals.cachedTerminal()).thenAnswer(
      (_) async => const Terminal(
        uuid: 'terminal-uuid',
        serialNumber: 'P501-000123',
        status: TerminalStatus.active,
      ),
    );
    when(() => terminals.logActivity(any(), context: any(named: 'context')))
        .thenAnswer((Invocation call) async {
      final Map<String, dynamic>? context =
          call.namedArguments[#context] as Map<String, dynamic>?;
      if (context != null) logs.add(context);
    });
  });

  /// Numéros d'exemplaires effectivement sortis de l'imprimante, dans
  /// l'ordre (lus dans la ligne « Exemplaire: n/3 » du document).
  List<int> printedCopyNumbers() => printer.printedBorderManifests
      .map((List<String> lines) => int.parse(RegExp(r'Exemplaire: (\d+)/3')
          .firstMatch(lines.join('\n'))!
          .group(1)!))
      .toList();

  group('load', () {
    test('manifeste complet, aucun job : prêt à imprimer', () async {
      final BorderManifestPrintCubit cubit = buildCubit();

      await cubit.load(borderInput());

      expect(cubit.state.phase, BorderManifestPrintPhase.ready);
      expect(cubit.state.payload?.passengerCount, 2);
      expect(cubit.state.copyStatuses, everyElement(BorderCopyStatus.pending));
      expect(cubit.state.copyStatuses, hasLength(3));
      await cubit.close();
    });

    test('départ national : refusé', () async {
      final BorderManifestPrintCubit cubit = buildCubit();

      await cubit.load(borderInput(isInternational: false));

      expect(cubit.state.phase, BorderManifestPrintPhase.refused);
      expect(cubit.state.issues.single, contains('pas international'));
      expect(cubit.state.payload, isNull);
      await cubit.close();
    });

    test('type de départ inconnu : refusé, rafraîchissement demandé', () async {
      final BorderManifestPrintCubit cubit = buildCubit();

      await cubit.load(borderInput(isInternational: null));

      expect(cubit.state.phase, BorderManifestPrintPhase.refused);
      expect(cubit.state.issues.single, contains('Rafraîchissez'));
      await cubit.close();
    });

    test('manifeste incomplet : refusé, le passager est nommé', () async {
      final BorderManifestPrintCubit cubit = buildCubit();

      await cubit.load(borderInput(passengers: <Passenger>[
        borderPassenger(),
        borderPassenger(id: 't2', name: 'Koffi Mensah', nationality: null),
      ]));

      expect(cubit.state.phase, BorderManifestPrintPhase.refused);
      expect(cubit.state.issues.single, contains('Koffi Mensah'));
      expect(cubit.state.issues.single, contains('nationalité'));
      await cubit.close();
    });
  });

  group('impression nominale', () {
    test('3 exemplaires, dans l\'ordre, puis terminé', () async {
      final BorderManifestPrintCubit cubit = buildCubit();
      final List<BorderManifestPrintPhase> phases =
          <BorderManifestPrintPhase>[];
      cubit.stream.listen((BorderManifestPrintState s) => phases.add(s.phase));

      await cubit.load(borderInput());
      await cubit.printCopies();
      await Future<void>.delayed(Duration.zero);

      expect(printedCopyNumbers(), <int>[1, 2, 3]);
      expect(cubit.state.phase, BorderManifestPrintPhase.completed);
      expect(cubit.state.job?.confirmedCopies, 3);
      expect(cubit.state.copyStatuses, everyElement(BorderCopyStatus.printed));
      expect(phases, contains(BorderManifestPrintPhase.printing));
      expect(phases.last, BorderManifestPrintPhase.completed);
      await cubit.close();
    });

    test('pendant l\'impression, l\'exemplaire en cours est signalé', () async {
      final BorderManifestPrintCubit cubit = buildCubit();
      final List<List<BorderCopyStatus>> snapshots = <List<BorderCopyStatus>>[];
      cubit.stream.listen((BorderManifestPrintState s) {
        if (s.phase == BorderManifestPrintPhase.printing) {
          snapshots.add(s.copyStatuses);
        }
      });

      await cubit.load(borderInput());
      await cubit.printCopies();
      await Future<void>.delayed(Duration.zero);

      expect(snapshots, <List<BorderCopyStatus>>[
        <BorderCopyStatus>[
          BorderCopyStatus.printing,
          BorderCopyStatus.pending,
          BorderCopyStatus.pending,
        ],
        <BorderCopyStatus>[
          BorderCopyStatus.printed,
          BorderCopyStatus.printing,
          BorderCopyStatus.pending,
        ],
        <BorderCopyStatus>[
          BorderCopyStatus.printed,
          BorderCopyStatus.printed,
          BorderCopyStatus.printing,
        ],
      ]);
      await cubit.close();
    });

    test('un double appui n\'imprime pas 6 exemplaires', () async {
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await Future.wait(<Future<void>>[
        cubit.printCopies(),
        cubit.printCopies(),
        cubit.printCopies(),
      ]);

      expect(printedCopyNumbers(), <int>[1, 2, 3]);
      expect(store.jobs, hasLength(1));
      await cubit.close();
    });

    test('déjà terminé : rien ne part sans réimpression explicite', () async {
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());
      await cubit.printCopies();

      await cubit.printCopies();
      await cubit.resume();

      expect(printedCopyNumbers(), <int>[1, 2, 3]);
      expect(store.jobs, hasLength(1));
      await cubit.close();
    });

    test('réimpression explicite : un nouveau job de 3 exemplaires', () async {
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());
      await cubit.printCopies();

      await cubit.printCopies(confirmReprint: true);

      expect(printedCopyNumbers(), <int>[1, 2, 3, 1, 2, 3]);
      expect(store.jobs, hasLength(2));
      expect(cubit.state.phase, BorderManifestPrintPhase.completed);
      await cubit.close();
    });

    test('ouvrir l\'écran après une impression terminée : état « terminé »',
        () async {
      final BorderManifestPrintCubit first = buildCubit();
      await first.load(borderInput());
      await first.printCopies();
      await first.close();

      final BorderManifestPrintCubit reopened = buildCubit();
      await reopened.load(borderInput());

      expect(reopened.state.phase, BorderManifestPrintPhase.completed);
      expect(reopened.state.copiesConfirmed, 3);
      expect(printedCopyNumbers(), <int>[1, 2, 3]);
      await reopened.close();
    });
  });

  group('manifeste refusé', () {
    test('rien n\'est envoyé à l\'imprimante, le refus est journalisé',
        () async {
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput(
        passengers: <Passenger>[borderPassenger(document: null)],
      ));

      await cubit.printCopies();

      expect(printer.printedBorderManifests, isEmpty);
      expect(store.jobs, isEmpty);
      expect(cubit.state.phase, BorderManifestPrintPhase.refused);
      expect(logs.single['result'], 'refused');
      expect(logs.single['document'], 'border_manifest');
      await cubit.close();
    });

    test('sans load préalable, printCopies ne fait rien', () async {
      final BorderManifestPrintCubit cubit = buildCubit();

      await cubit.printCopies();

      expect(printer.printedBorderManifests, isEmpty);
      await cubit.close();
    });
  });

  group('absence de papier', () {
    test('l\'exemplaire 2 échoue : interrompu, message clair, 1 confirmé',
        () async {
      printer
        ..failOnCopies.add(2)
        ..failureKind = PrinterFailureKind.outOfPaper;
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await cubit.printCopies();

      expect(cubit.state.phase, BorderManifestPrintPhase.interrupted);
      expect(cubit.state.message, PrinterFailureKind.outOfPaper.userMessage());
      expect(cubit.state.job?.confirmedCopies, 1);
      expect(cubit.state.copyStatuses, <BorderCopyStatus>[
        BorderCopyStatus.printed,
        BorderCopyStatus.failed,
        BorderCopyStatus.pending,
      ]);
      expect(printedCopyNumbers(), <int>[1]);
      await cubit.close();
    });

    test('la reprise imprime les exemplaires restants, sans réimprimer le 1',
        () async {
      printer
        ..failOnCopies.add(2)
        ..failureKind = PrinterFailureKind.outOfPaper;
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());
      await cubit.printCopies();

      // Le convoyeur recharge le papier.
      printer.failOnCopies.clear();
      await cubit.resume();

      expect(printedCopyNumbers(), <int>[1, 2, 3]);
      expect(cubit.state.phase, BorderManifestPrintPhase.completed);
      expect(store.jobs, hasLength(1));
      await cubit.close();
    });

    test('toujours sans papier à la reprise : reste interrompu, sans doublon',
        () async {
      printer
        ..failOnCopies.add(2)
        ..failureKind = PrinterFailureKind.outOfPaper;
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());
      await cubit.printCopies();

      await cubit.resume();
      await cubit.resume();

      expect(cubit.state.phase, BorderManifestPrintPhase.interrupted);
      expect(printedCopyNumbers(), <int>[1]);
      expect(cubit.state.job?.confirmedCopies, 1);
      await cubit.close();
    });

    test('après fermeture de l\'écran, l\'impression interrompue est reprise',
        () async {
      printer
        ..failOnCopies.add(3)
        ..failureKind = PrinterFailureKind.outOfPaper;
      final BorderManifestPrintCubit first = buildCubit();
      await first.load(borderInput());
      await first.printCopies();
      await first.close();

      printer.failOnCopies.clear();
      final BorderManifestPrintCubit reopened = buildCubit();
      await reopened.load(borderInput());

      expect(reopened.state.phase, BorderManifestPrintPhase.interrupted);
      expect(reopened.state.copiesConfirmed, 2);
      expect(
          reopened.state.message, PrinterFailureKind.outOfPaper.userMessage());

      await reopened.resume();

      expect(printedCopyNumbers(), <int>[1, 2, 3]);
      expect(reopened.state.phase, BorderManifestPrintPhase.completed);
      await reopened.close();
    });

    test('imprimante absente : message dédié', () async {
      printer
        ..failOnCopies.add(1)
        ..failureKind = PrinterFailureKind.notConnected;
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await cubit.printCopies();

      expect(cubit.state.phase, BorderManifestPrintPhase.interrupted);
      expect(
          cubit.state.message, PrinterFailureKind.notConnected.userMessage());
      expect(cubit.state.job?.confirmedCopies, 0);
      await cubit.close();
    });
  });

  group('erreurs inattendues du matériel', () {
    test('le SDK renvoie false : exemplaire non compté', () async {
      final ScriptedPrinter scripted = ScriptedPrinter();
      final BorderManifestPrintCubit cubit = buildCubit(scripted);
      await cubit.load(borderInput());

      await cubit.printCopies();

      expect(cubit.state.phase, BorderManifestPrintPhase.interrupted);
      expect(cubit.state.message, 'Impression refusée par le matériel.');
      expect(cubit.state.job?.confirmedCopies, 0);
      expect(scripted.calls, 1);
      await cubit.close();
    });

    test('exception quelconque : interrompu avec un message générique',
        () async {
      final ScriptedPrinter scripted =
          ScriptedPrinter(error: StateError('boom interne'));
      final BorderManifestPrintCubit cubit = buildCubit(scripted);
      await cubit.load(borderInput());

      await cubit.printCopies();

      expect(cubit.state.phase, BorderManifestPrintPhase.interrupted);
      expect(cubit.state.message, 'Erreur inattendue pendant l\'impression.');
      // Le détail interne n'est jamais montré au convoyeur.
      expect(cubit.state.message, isNot(contains('boom')));
      await cubit.close();
    });
  });

  group('crash pendant l\'impression puis reprise', () {
    /// Reproduit l'état persisté d'un terminal tué pendant l'exemplaire 2 :
    /// exemplaire 1 confirmé, exemplaire 2 envoyé sans résultat.
    Future<void> simulateCrashDuringCopy2() async {
      final BorderManifestPrintJob job = await repository.createJob(
        departureUuid: kDepartureUuid,
        requestedCopies: 3,
        manifestVersion: 'v1',
      );
      await repository.markCopyStarted(job.jobUuid, 1);
      await repository.markCopyConfirmed(job.jobUuid, 1);
      await repository.markCopyStarted(job.jobUuid, 2);
    }

    test('au redémarrage, on demande au convoyeur de constater', () async {
      await simulateCrashDuringCopy2();

      // Nouveau cubit, nouveau repository : seul le stockage a survécu.
      repository = newRepository();
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      expect(cubit.state.phase, BorderManifestPrintPhase.awaitingConfirmation);
      expect(cubit.state.message, contains('exemplaire 2'));
      expect(cubit.state.copyStatuses, <BorderCopyStatus>[
        BorderCopyStatus.printed,
        BorderCopyStatus.unconfirmed,
        BorderCopyStatus.pending,
      ]);
      expect(printer.printedBorderManifests, isEmpty);
      await cubit.close();
    });

    test('tant que le doute n\'est pas levé, rien n\'est réimprimé', () async {
      await simulateCrashDuringCopy2();
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await cubit.printCopies();
      await cubit.resume();

      expect(printer.printedBorderManifests, isEmpty);
      expect(cubit.state.phase, BorderManifestPrintPhase.awaitingConfirmation);
      expect(cubit.state.job?.inFlightCopy, 2);
      await cubit.close();
    });

    test('« il est sorti » : compté, la reprise n\'imprime que le 3', () async {
      await simulateCrashDuringCopy2();
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await cubit.confirmInFlightCopy(printed: true);

      expect(cubit.state.phase, BorderManifestPrintPhase.interrupted);
      expect(cubit.state.job?.confirmedCopies, 2);
      expect(cubit.state.message, contains('Exemplaire 2 confirmé'));

      await cubit.resume();

      expect(printedCopyNumbers(), <int>[3]);
      expect(cubit.state.phase, BorderManifestPrintPhase.completed);
      expect(cubit.state.job?.confirmedCopies, 3);
      await cubit.close();
    });

    test('« il n\'est pas sorti » : la reprise réimprime le 2 puis le 3',
        () async {
      await simulateCrashDuringCopy2();
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await cubit.confirmInFlightCopy(printed: false);

      expect(cubit.state.phase, BorderManifestPrintPhase.interrupted);
      expect(cubit.state.job?.confirmedCopies, 1);
      expect(cubit.state.message, contains('n\'est pas sorti'));

      await cubit.resume();

      expect(printedCopyNumbers(), <int>[2, 3]);
      expect(cubit.state.phase, BorderManifestPrintPhase.completed);
      await cubit.close();
    });

    test('un constat sans exemplaire en doute est ignoré', () async {
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await cubit.confirmInFlightCopy(printed: true);

      expect(cubit.state.phase, BorderManifestPrintPhase.ready);
      expect(store.jobs, isEmpty);
      await cubit.close();
    });

    test('crash pendant le dernier exemplaire, « sorti » : terminé', () async {
      final BorderManifestPrintJob job = await repository.createJob(
        departureUuid: kDepartureUuid,
        requestedCopies: 3,
      );
      for (int copy = 1; copy <= 2; copy++) {
        await repository.markCopyStarted(job.jobUuid, copy);
        await repository.markCopyConfirmed(job.jobUuid, copy);
      }
      await repository.markCopyStarted(job.jobUuid, 3);

      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());
      await cubit.confirmInFlightCopy(printed: true);

      expect(cubit.state.phase, BorderManifestPrintPhase.completed);
      expect(printer.printedBorderManifests, isEmpty);
      await cubit.close();
    });

    test('crash entre deux exemplaires (rien en vol) : reprise directe',
        () async {
      final BorderManifestPrintJob job = await repository.createJob(
        departureUuid: kDepartureUuid,
        requestedCopies: 3,
      );
      await repository.markCopyStarted(job.jobUuid, 1);
      await repository.markCopyConfirmed(job.jobUuid, 1);

      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      expect(cubit.state.phase, BorderManifestPrintPhase.interrupted);

      await cubit.resume();

      expect(printedCopyNumbers(), <int>[2, 3]);
      await cubit.close();
    });
  });

  group('journalisation', () {
    test('chaque exemplaire et la fin de l\'impression sont journalisés',
        () async {
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await cubit.printCopies();

      expect(
        logs.map((Map<String, dynamic> l) => l['result']).toList(),
        <String>['copy_printed', 'copy_printed', 'copy_printed', 'completed'],
      );
      final Map<String, dynamic> last = logs.last;
      expect(last['document'], 'border_manifest');
      expect(last['mode'], 'thermal');
      expect(last['departure_uuid'], kDepartureUuid);
      expect(last['copies_requested'], 3);
      expect(last['copies_confirmed'], 3);
      expect(last['user_uuid'], 'user-42');
      expect(last['terminal_serial'], 'P501-000123');
      expect(last['recorded_at'], fixedNow.toIso8601String());
      expect(last['passenger_count'], 2);
      verify(() => terminals.logActivity(TerminalActivityType.print,
          context: any(named: 'context'))).called(4);
      await cubit.close();
    });

    test('un échec matériel est journalisé avec sa nature', () async {
      printer
        ..failOnCopies.add(2)
        ..failureKind = PrinterFailureKind.outOfPaper;
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await cubit.printCopies();

      final Map<String, dynamic> failure = logs.last;
      expect(failure['result'], 'copy_failed');
      expect(failure['copy_number'], 2);
      expect(failure['copies_confirmed'], 1);
      expect(failure['error_kind'], 'outOfPaper');
      await cubit.close();
    });

    test('le constat après crash est journalisé', () async {
      final BorderManifestPrintJob job = await repository.createJob(
        departureUuid: kDepartureUuid,
        requestedCopies: 3,
      );
      await repository.markCopyStarted(job.jobUuid, 1);
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await cubit.confirmInFlightCopy(printed: false);

      expect(logs.single['result'], 'interrupted_copy_lost');
      expect(logs.single['copy_number'], 1);
      await cubit.close();
    });

    test('jamais de donnée passager dans le journal', () async {
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());
      await cubit.printCopies();

      final String everything =
          logs.map((Map<String, dynamic> l) => '$l').join(' ').toLowerCase();

      expect(everything, isNot(contains('traor')));
      expect(everything, isNot(contains('mensah')));
      expect(everything, isNot(contains('pp123456')));
      await cubit.close();
    });

    test('une panne du journal n\'empêche pas l\'impression', () async {
      when(() => terminals.logActivity(any(), context: any(named: 'context')))
          .thenThrow(Exception('réseau'));
      final BorderManifestPrintCubit cubit = buildCubit();
      await cubit.load(borderInput());

      await cubit.printCopies();

      expect(cubit.state.phase, BorderManifestPrintPhase.completed);
      expect(printedCopyNumbers(), <int>[1, 2, 3]);
      await cubit.close();
    });
  });
}
