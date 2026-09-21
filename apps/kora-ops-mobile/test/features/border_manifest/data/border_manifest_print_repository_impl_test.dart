import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/border_manifest/data/repositories/border_manifest_print_repository_impl.dart';
import 'package:kora_ops_mobile/features/border_manifest/domain/entities/border_manifest_print_job.dart';

import '../support/border_manifest_fixtures.dart';

void main() {
  late InMemoryBorderManifestPrintDataSource store;
  late BorderManifestPrintRepositoryImpl repository;
  int uuidCounter = 0;
  DateTime clock = DateTime(2026, 8, 14, 7);

  setUp(() {
    store = InMemoryBorderManifestPrintDataSource();
    uuidCounter = 0;
    clock = DateTime(2026, 8, 14, 7);
    repository = BorderManifestPrintRepositoryImpl(
      store,
      now: () => clock = clock.add(const Duration(seconds: 1)),
      newUuid: () => 'job-${++uuidCounter}',
    );
  });

  Future<BorderManifestPrintJob> newJob({int copies = 3}) =>
      repository.createJob(
        departureUuid: kDepartureUuid,
        requestedCopies: copies,
        manifestVersion: 'v1',
        userUuid: 'user-1',
        terminalSerial: 'P501-000123',
      );

  group('createJob', () {
    test('crée un job en cours, sans exemplaire confirmé', () async {
      final BorderManifestPrintJob job = await newJob();

      expect(job.jobUuid, 'job-1');
      expect(job.requestedCopies, 3);
      expect(job.confirmedCopies, 0);
      expect(job.inFlightCopy, isNull);
      expect(job.status, BorderManifestPrintJobStatus.inProgress);
      expect(job.userUuid, 'user-1');
      expect(job.terminalSerial, 'P501-000123');
      expect(job.nextCopy, 1);
      expect(store.jobs, hasLength(1));
    });

    test('ne crée jamais un second job non terminé pour le même départ',
        () async {
      final BorderManifestPrintJob first = await newJob();
      await repository.markCopyStarted(first.jobUuid, 1);
      await repository.markCopyConfirmed(first.jobUuid, 1);

      final BorderManifestPrintJob second = await newJob();

      expect(second.jobUuid, first.jobUuid);
      expect(second.confirmedCopies, 1);
      expect(store.jobs, hasLength(1));
    });

    test('un nouveau job est possible une fois le précédent terminé', () async {
      final BorderManifestPrintJob first = await newJob(copies: 1);
      await repository.markCopyStarted(first.jobUuid, 1);
      await repository.markCopyConfirmed(first.jobUuid, 1);

      final BorderManifestPrintJob second = await newJob(copies: 1);

      expect(second.jobUuid, isNot(first.jobUuid));
      expect(store.jobs, hasLength(2));
    });

    test('refuse un nombre d\'exemplaires nul', () async {
      await expectLater(newJob(copies: 0), throwsArgumentError);
    });
  });

  group('cycle nominal', () {
    test('3 exemplaires confirmés → job terminé, jamais un de plus', () async {
      final BorderManifestPrintJob job = await newJob();

      for (int copy = 1; copy <= 3; copy++) {
        final BorderManifestPrintJob started =
            await repository.markCopyStarted(job.jobUuid, copy);
        expect(started.inFlightCopy, copy);
        // Pas encore compté tant que l'imprimante n'a pas confirmé.
        expect(started.confirmedCopies, copy - 1);

        final BorderManifestPrintJob confirmed =
            await repository.markCopyConfirmed(job.jobUuid, copy);
        expect(confirmed.confirmedCopies, copy);
        expect(confirmed.inFlightCopy, isNull);
      }

      final BorderManifestPrintJob done =
          (await repository.findLastJob(kDepartureUuid))!;
      expect(done.isCompleted, isTrue);
      expect(done.nextCopy, isNull);
      expect(await repository.findUnfinishedJob(kDepartureUuid), isNull);

      await expectLater(
          repository.markCopyStarted(job.jobUuid, 4), throwsStateError);
      await expectLater(
          repository.markCopyStarted(job.jobUuid, 3), throwsStateError);
    });
  });

  group('erreur matérielle', () {
    test('markCopyFailed : job interrompu, compte confirmé inchangé', () async {
      final BorderManifestPrintJob job = await newJob();
      await repository.markCopyStarted(job.jobUuid, 1);
      await repository.markCopyConfirmed(job.jobUuid, 1);
      await repository.markCopyStarted(job.jobUuid, 2);

      final BorderManifestPrintJob failed =
          await repository.markCopyFailed(job.jobUuid, 2, 'Plus de papier');

      expect(failed.status, BorderManifestPrintJobStatus.interrupted);
      expect(failed.confirmedCopies, 1);
      expect(failed.inFlightCopy, isNull);
      expect(failed.lastError, 'Plus de papier');
      expect(failed.nextCopy, 2);
      expect(await repository.findUnfinishedJob(kDepartureUuid), isNotNull);
    });

    test('la reprise repart de l\'exemplaire en échec, pas de zéro', () async {
      final BorderManifestPrintJob job = await newJob();
      await repository.markCopyStarted(job.jobUuid, 1);
      await repository.markCopyConfirmed(job.jobUuid, 1);
      await repository.markCopyStarted(job.jobUuid, 2);
      await repository.markCopyFailed(job.jobUuid, 2, 'Plus de papier');

      // Reprendre l'exemplaire 1 (déjà imprimé) est interdit.
      await expectLater(
          repository.markCopyStarted(job.jobUuid, 1), throwsStateError);

      final BorderManifestPrintJob resumed =
          await repository.markCopyStarted(job.jobUuid, 2);
      expect(resumed.inFlightCopy, 2);
      expect(resumed.status, BorderManifestPrintJobStatus.inProgress);
      // L'ancien message d'erreur ne doit pas survivre à la reprise.
      expect(resumed.lastError, isNull);
    });
  });

  group('invariants (StateError)', () {
    test('impossible de démarrer un exemplaire hors séquence', () async {
      final BorderManifestPrintJob job = await newJob();

      await expectLater(
          repository.markCopyStarted(job.jobUuid, 2), throwsStateError);
    });

    test(
        'impossible de démarrer un exemplaire pendant qu\'un autre est en cours',
        () async {
      final BorderManifestPrintJob job = await newJob();
      await repository.markCopyStarted(job.jobUuid, 1);

      await expectLater(
          repository.markCopyStarted(job.jobUuid, 1), throwsStateError);
      await expectLater(
          repository.markCopyStarted(job.jobUuid, 2), throwsStateError);
    });

    test('impossible de confirmer ou d\'échouer un exemplaire pas en cours',
        () async {
      final BorderManifestPrintJob job = await newJob();

      await expectLater(
          repository.markCopyConfirmed(job.jobUuid, 1), throwsStateError);
      await expectLater(
          repository.markCopyFailed(job.jobUuid, 1, 'x'), throwsStateError);

      await repository.markCopyStarted(job.jobUuid, 1);
      await expectLater(
          repository.markCopyConfirmed(job.jobUuid, 2), throwsStateError);
    });

    test('un job inconnu lève une StateError', () async {
      await expectLater(
          repository.markCopyStarted('inconnu', 1), throwsStateError);
    });
  });

  group('crash pendant l\'impression (exemplaire en cours)', () {
    Future<BorderManifestPrintJob> crashedDuringCopy2() async {
      final BorderManifestPrintJob job = await newJob();
      await repository.markCopyStarted(job.jobUuid, 1);
      await repository.markCopyConfirmed(job.jobUuid, 1);
      // L'app est tuée ici : ni confirmation ni échec pour l'exemplaire 2.
      return repository.markCopyStarted(job.jobUuid, 2);
    }

    test('l\'exemplaire reste « en cours », non compté', () async {
      final BorderManifestPrintJob crashed = await crashedDuringCopy2();

      expect(crashed.needsConfirmation, isTrue);
      expect(crashed.inFlightCopy, 2);
      expect(crashed.confirmedCopies, 1);

      // Après « redémarrage » : un nouveau repository sur le même stockage.
      final BorderManifestPrintRepositoryImpl restarted =
          BorderManifestPrintRepositoryImpl(store);
      final BorderManifestPrintJob? found =
          await restarted.findUnfinishedJob(kDepartureUuid);
      expect(found?.needsConfirmation, isTrue);
      expect(found?.inFlightCopy, 2);
    });

    test('impossible de démarrer un autre exemplaire avant le constat',
        () async {
      final BorderManifestPrintJob crashed = await crashedDuringCopy2();

      await expectLater(
          repository.markCopyStarted(crashed.jobUuid, 2), throwsStateError);
      await expectLater(
          repository.markCopyStarted(crashed.jobUuid, 3), throwsStateError);
    });

    test('constat « il est sorti » : compté, jamais réimprimé', () async {
      final BorderManifestPrintJob crashed = await crashedDuringCopy2();

      final BorderManifestPrintJob resolved =
          await repository.resolveInFlightCopy(crashed.jobUuid, printed: true);

      expect(resolved.confirmedCopies, 2);
      expect(resolved.inFlightCopy, isNull);
      expect(resolved.nextCopy, 3);
      expect(resolved.status, BorderManifestPrintJobStatus.inProgress);
    });

    test('constat « il n\'est pas sorti » : sera réimprimé', () async {
      final BorderManifestPrintJob crashed = await crashedDuringCopy2();

      final BorderManifestPrintJob resolved =
          await repository.resolveInFlightCopy(crashed.jobUuid, printed: false);

      expect(resolved.confirmedCopies, 1);
      expect(resolved.inFlightCopy, isNull);
      expect(resolved.nextCopy, 2);
      expect(resolved.status, BorderManifestPrintJobStatus.interrupted);
    });

    test('constat « sorti » sur le dernier exemplaire : job terminé', () async {
      final BorderManifestPrintJob job = await newJob(copies: 1);
      await repository.markCopyStarted(job.jobUuid, 1);

      final BorderManifestPrintJob resolved =
          await repository.resolveInFlightCopy(job.jobUuid, printed: true);

      expect(resolved.isCompleted, isTrue);
      expect(await repository.findUnfinishedJob(kDepartureUuid), isNull);
    });

    test('rien à constater : StateError', () async {
      final BorderManifestPrintJob job = await newJob();

      await expectLater(
        repository.resolveInFlightCopy(job.jobUuid, printed: true),
        throwsStateError,
      );
    });
  });
}
