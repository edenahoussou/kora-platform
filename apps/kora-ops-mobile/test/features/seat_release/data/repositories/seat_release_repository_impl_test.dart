import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show NetworkApiException;
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/features/seat_release/data/datasources/seat_release_local_datasource.dart';
import 'package:kora_ops_mobile/features/seat_release/data/datasources/seat_release_remote_datasource.dart';
import 'package:kora_ops_mobile/features/seat_release/data/repositories/seat_release_repository_impl.dart';
import 'package:kora_ops_mobile/features/seat_release/domain/entities/pending_seat_release_operation.dart';
import 'package:kora_ops_mobile/features/seat_release/domain/entities/seat_release_outcome.dart';
import 'package:kora_ops_mobile/features/seat_release/domain/entities/seat_release_outcome_status.dart';
import 'package:mocktail/mocktail.dart';
import 'package:uuid/uuid.dart';

class MockSeatReleaseRemoteDataSource extends Mock
    implements SeatReleaseRemoteDataSource {}

class MockSeatReleaseLocalDataSource extends Mock
    implements SeatReleaseLocalDataSource {}

DioException _networkError() => DioException(
      requestOptions: RequestOptions(path: '/api/v1/ops/seat-releases'),
      error: const NetworkApiException(),
      type: DioExceptionType.connectionError,
    );

void main() {
  setUpAll(() {
    registerFallbackValue(
      const SeatReleaseOutcome(
          status: SeatReleaseOutcomeStatus.accepted, message: 'fallback'),
    );
    registerFallbackValue(SeatReleaseType.earlyDropOff);
  });

  late MockSeatReleaseRemoteDataSource remote;
  late MockSeatReleaseLocalDataSource local;
  late SeatReleaseRepositoryImpl repository;

  const String departureUuid = 'dep-uuid-1';
  const String ticketUuid = 'ticket-uuid-1';
  const String actualStopUuid = 'stop-uuid-1';
  final DateTime localRecordedAt = DateTime(2026, 9, 15, 8);

  setUp(() {
    remote = MockSeatReleaseRemoteDataSource();
    local = MockSeatReleaseLocalDataSource();
    repository = SeatReleaseRepositoryImpl(remote, local, uuid: const Uuid());

    // Par défaut : rien en cache, chaque test qui a besoin d'un doublon
    // déjà connu le stub explicitement.
    when(() => local.findConclusiveOutcome(
          departureUuid: any(named: 'departureUuid'),
          ticketUuid: any(named: 'ticketUuid'),
          type: any(named: 'type'),
        )).thenAnswer((_) async => null);
    when(() => local.recordOutcome(
          operationUuid: any(named: 'operationUuid'),
          type: any(named: 'type'),
          departureUuid: any(named: 'departureUuid'),
          ticketUuid: any(named: 'ticketUuid'),
          actualStopUuid: any(named: 'actualStopUuid'),
          outcome: any(named: 'outcome'),
          localRecordedAt: any(named: 'localRecordedAt'),
          synced: any(named: 'synced'),
        )).thenAnswer((_) async {});
  });

  group('release (descente anticipée)', () {
    test('en ligne, nominal : accepté, enregistré comme synchronisé', () async {
      when(() => remote.release(
            departureUuid: departureUuid,
            ticketUuid: ticketUuid,
            actualStopUuid: actualStopUuid,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: localRecordedAt,
          )).thenAnswer((_) async => const SeatReleaseOutcome(
            status: SeatReleaseOutcomeStatus.accepted,
            message: 'Libération enregistrée.',
            ticketUuid: ticketUuid,
          ));

      final result = await repository.release(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        actualStopUuid: actualStopUuid,
        operationUuid: 'op-1',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (outcome) =>
            expect(outcome.status, SeatReleaseOutcomeStatus.accepted),
        onFailure: (_) => fail('devait réussir'),
      );
      verify(() => local.recordOutcome(
            operationUuid: 'op-1',
            type: SeatReleaseType.earlyDropOff,
            departureUuid: departureUuid,
            ticketUuid: ticketUuid,
            actualStopUuid: actualStopUuid,
            outcome: any(named: 'outcome'),
            localRecordedAt: localRecordedAt,
            synced: true,
          )).called(1);
    });

    test(
        'retry idempotent : un résultat déjà connu localement est rendu '
        'immédiatement, sans second appel réseau', () async {
      const SeatReleaseOutcome cached = SeatReleaseOutcome(
        status: SeatReleaseOutcomeStatus.accepted,
        message: 'Libération enregistrée.',
        ticketUuid: ticketUuid,
      );
      when(() => local.findConclusiveOutcome(
            departureUuid: departureUuid,
            ticketUuid: ticketUuid,
            type: SeatReleaseType.earlyDropOff,
          )).thenAnswer((_) async => cached);

      final result = await repository.release(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        actualStopUuid: actualStopUuid,
        operationUuid: 'op-retry',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (outcome) => expect(outcome, cached),
        onFailure: (_) => fail('devait réussir'),
      );
      verifyNever(() => remote.release(
            departureUuid: any(named: 'departureUuid'),
            ticketUuid: any(named: 'ticketUuid'),
            actualStopUuid: any(named: 'actualStopUuid'),
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: any(named: 'localRecordedAt'),
          ));
    });

    test('conflit (deux terminaux) : renvoyé tel quel, enregistré localement',
        () async {
      when(() => remote.release(
            departureUuid: departureUuid,
            ticketUuid: ticketUuid,
            actualStopUuid: actualStopUuid,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: localRecordedAt,
          )).thenAnswer((_) async => const SeatReleaseOutcome(
            status: SeatReleaseOutcomeStatus.conflict,
            message: 'Ce trajet a déjà été libéré depuis un autre terminal.',
            recommendedAction: 'Vérifiez auprès de l’autre terminal.',
          ));

      final result = await repository.release(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        actualStopUuid: actualStopUuid,
        operationUuid: 'op-2',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (outcome) {
          expect(outcome.status, SeatReleaseOutcomeStatus.conflict);
          expect(outcome.recommendedAction, isNotNull);
        },
        onFailure: (_) =>
            fail('devait réussir (résultat métier, pas un échec)'),
      );
      verify(() => local.recordOutcome(
            operationUuid: 'op-2',
            type: SeatReleaseType.earlyDropOff,
            departureUuid: departureUuid,
            ticketUuid: ticketUuid,
            actualStopUuid: actualStopUuid,
            outcome: any(named: 'outcome'),
            localRecordedAt: localRecordedAt,
            synced: true,
          )).called(1);
    });

    test('arrêt incompatible (422) : rejeté, avec action recommandée',
        () async {
      when(() => remote.release(
            departureUuid: departureUuid,
            ticketUuid: ticketUuid,
            actualStopUuid: actualStopUuid,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: localRecordedAt,
          )).thenAnswer((_) async => const SeatReleaseOutcome(
            status: SeatReleaseOutcomeStatus.rejected,
            message:
                'Cet arrêt n’est pas compatible avec la jambe et la destination initiale du billet.',
            recommendedAction:
                'Sélectionnez un arrêt situé entre l’embarquement et la destination du billet.',
          ));

      final result = await repository.release(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        actualStopUuid: actualStopUuid,
        operationUuid: 'op-3',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (outcome) =>
            expect(outcome.status, SeatReleaseOutcomeStatus.rejected),
        onFailure: (_) =>
            fail('devait réussir (résultat métier, pas un échec)'),
      );
    });

    test('billet inconnu (404) : rejeté, enregistré pour bloquer un retry',
        () async {
      when(() => remote.release(
            departureUuid: departureUuid,
            ticketUuid: ticketUuid,
            actualStopUuid: actualStopUuid,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: localRecordedAt,
          )).thenThrow(const SeatReleaseApiException(
        SeatReleaseApiException.ticketUnknown,
        'Billet inconnu.',
        'Vérifiez le billet et réessayez.',
      ));

      final result = await repository.release(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        actualStopUuid: actualStopUuid,
        operationUuid: 'op-4',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (outcome) =>
            expect(outcome.status, SeatReleaseOutcomeStatus.rejected),
        onFailure: (_) =>
            fail('devait réussir (résultat métier, pas un échec)'),
      );
    });

    test('hors ligne : mis en file, jamais un succès fabriqué en "accepted"',
        () async {
      when(() => remote.release(
            departureUuid: departureUuid,
            ticketUuid: ticketUuid,
            actualStopUuid: actualStopUuid,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: localRecordedAt,
          )).thenThrow(_networkError());

      final result = await repository.release(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        actualStopUuid: actualStopUuid,
        operationUuid: 'op-5',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (outcome) =>
            expect(outcome.status, SeatReleaseOutcomeStatus.pendingSync),
        onFailure: (_) => fail('l\'absence de réseau n\'est pas un échec'),
      );
      verify(() => local.recordOutcome(
            operationUuid: 'op-5',
            type: SeatReleaseType.earlyDropOff,
            departureUuid: departureUuid,
            ticketUuid: ticketUuid,
            actualStopUuid: actualStopUuid,
            outcome: any(named: 'outcome'),
            localRecordedAt: localRecordedAt,
            synced: false,
          )).called(1);
    });

    test('erreur technique (5xx) : Failure, jamais enregistrée (re-tentable)',
        () async {
      when(() => remote.release(
            departureUuid: departureUuid,
            ticketUuid: ticketUuid,
            actualStopUuid: actualStopUuid,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: localRecordedAt,
          )).thenThrow(DioException(
        requestOptions: RequestOptions(path: '/api/v1/ops/seat-releases'),
        type: DioExceptionType.unknown,
      ));

      final result = await repository.release(
        departureUuid: departureUuid,
        ticketUuid: ticketUuid,
        actualStopUuid: actualStopUuid,
        operationUuid: 'op-6',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<ServerFailure>()),
      );
      verifyNever(() => local.recordOutcome(
            operationUuid: any(named: 'operationUuid'),
            type: any(named: 'type'),
            departureUuid: any(named: 'departureUuid'),
            ticketUuid: any(named: 'ticketUuid'),
            actualStopUuid: any(named: 'actualStopUuid'),
            outcome: any(named: 'outcome'),
            localRecordedAt: any(named: 'localRecordedAt'),
            synced: any(named: 'synced'),
          ));
    });
  });

  group('closeNoShows', () {
    test(
        'en ligne : un seul appel groupé, chaque résultat enregistré '
        'comme synchronisé', () async {
      when(() => remote.closeNoShows(
            departureUuid: departureUuid,
            operationUuid: 'op-closure',
            localRecordedAt: localRecordedAt,
          )).thenAnswer((_) async => const <SeatReleaseOutcome>[
            SeatReleaseOutcome(
              status: SeatReleaseOutcomeStatus.accepted,
              message: 'Libération enregistrée.',
              ticketUuid: 'ticket-a',
            ),
            SeatReleaseOutcome(
              status: SeatReleaseOutcomeStatus.rejected,
              message: 'Ce billet a été annulé.',
              ticketUuid: 'ticket-b',
            ),
          ]);

      final result = await repository.closeNoShows(
        departureUuid: departureUuid,
        uncontrolledTicketUuids: <String>['ticket-a', 'ticket-b'],
        operationUuid: 'op-closure',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (outcomes) => expect(outcomes.length, 2),
        onFailure: (_) => fail('devait réussir'),
      );
      verify(() => remote.closeNoShows(
            departureUuid: departureUuid,
            operationUuid: 'op-closure',
            localRecordedAt: localRecordedAt,
          )).called(1);
      verify(() => local.recordOutcome(
            operationUuid: any(named: 'operationUuid'),
            type: SeatReleaseType.noShow,
            departureUuid: departureUuid,
            ticketUuid: any(named: 'ticketUuid'),
            outcome: any(named: 'outcome'),
            localRecordedAt: localRecordedAt,
            synced: true,
          )).called(2);
    });

    test('liste de billets non contrôlés vide : aucun appel réseau', () async {
      final result = await repository.closeNoShows(
        departureUuid: departureUuid,
        uncontrolledTicketUuids: const <String>[],
        operationUuid: 'op-empty',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (outcomes) => expect(outcomes, isEmpty),
        onFailure: (_) => fail('devait réussir'),
      );
      verifyNever(() => remote.closeNoShows(
            departureUuid: any(named: 'departureUuid'),
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: any(named: 'localRecordedAt'),
          ));
    });

    test(
        'hors ligne : chaque billet devient sa propre opération '
        '`boarding.no_show` en file, jamais de clôture groupée locale',
        () async {
      when(() => remote.closeNoShows(
            departureUuid: departureUuid,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: localRecordedAt,
          )).thenThrow(_networkError());

      final result = await repository.closeNoShows(
        departureUuid: departureUuid,
        uncontrolledTicketUuids: <String>['ticket-a', 'ticket-b'],
        operationUuid: 'op-closure',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (outcomes) {
          expect(outcomes.length, 2);
          expect(
              outcomes.every(
                  (o) => o.status == SeatReleaseOutcomeStatus.pendingSync),
              isTrue);
        },
        onFailure: (_) => fail('l\'absence de réseau n\'est pas un échec'),
      );
      verify(() => local.recordOutcome(
            operationUuid: any(named: 'operationUuid'),
            type: SeatReleaseType.noShow,
            departureUuid: departureUuid,
            ticketUuid: any(named: 'ticketUuid'),
            actualStopUuid: null,
            outcome: any(named: 'outcome'),
            localRecordedAt: localRecordedAt,
            synced: false,
          )).called(2);
    });

    test(
        'hors ligne, retry idempotent : un billet déjà en file n\'est pas '
        'redoublé', () async {
      when(() => remote.closeNoShows(
            departureUuid: departureUuid,
            operationUuid: any(named: 'operationUuid'),
            localRecordedAt: localRecordedAt,
          )).thenThrow(_networkError());
      const SeatReleaseOutcome alreadyQueued = SeatReleaseOutcome(
        status: SeatReleaseOutcomeStatus.pendingSync,
        message: 'Enregistré hors ligne — en attente de synchronisation.',
        ticketUuid: 'ticket-a',
      );
      when(() => local.findConclusiveOutcome(
            departureUuid: departureUuid,
            ticketUuid: 'ticket-a',
            type: SeatReleaseType.noShow,
          )).thenAnswer((_) async => alreadyQueued);

      final result = await repository.closeNoShows(
        departureUuid: departureUuid,
        uncontrolledTicketUuids: <String>['ticket-a'],
        operationUuid: 'op-closure-2',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (outcomes) => expect(outcomes.single, alreadyQueued),
        onFailure: (_) => fail('devait réussir'),
      );
      verifyNever(() => local.recordOutcome(
            operationUuid: any(named: 'operationUuid'),
            type: SeatReleaseType.noShow,
            departureUuid: departureUuid,
            ticketUuid: 'ticket-a',
            actualStopUuid: any(named: 'actualStopUuid'),
            outcome: any(named: 'outcome'),
            localRecordedAt: any(named: 'localRecordedAt'),
            synced: false,
          ));
    });
  });

  group('pending / applySyncResult', () {
    test('totalPendingCount et listPending délèguent au datasource local',
        () async {
      when(() => local.totalPendingCount()).thenAnswer((_) async => 4);
      when(() => local.listPending())
          .thenAnswer((_) async => <PendingSeatReleaseOperation>[
                PendingSeatReleaseOperation(
                  operationUuid: 'op-1',
                  type: SeatReleaseType.earlyDropOff,
                  departureUuid: departureUuid,
                  ticketUuid: ticketUuid,
                  actualStopUuid: actualStopUuid,
                  localRecordedAt: localRecordedAt,
                ),
              ]);

      expect(await repository.totalPendingCount(), 4);
      expect((await repository.listPending()).single.operationUuid, 'op-1');
    });

    test('applySyncResult délègue à markSynced', () async {
      when(() => local.markSynced(
            operationUuid: any(named: 'operationUuid'),
            outcome: any(named: 'outcome'),
            serverRecordedAt: any(named: 'serverRecordedAt'),
          )).thenAnswer((_) async {});

      await repository.applySyncResult(
        operationUuid: 'op-1',
        outcome: const SeatReleaseOutcome(
            status: SeatReleaseOutcomeStatus.accepted, message: 'ok'),
        serverRecordedAt: localRecordedAt,
      );

      verify(() => local.markSynced(
            operationUuid: 'op-1',
            outcome: any(named: 'outcome'),
            serverRecordedAt: localRecordedAt,
          )).called(1);
    });
  });
}
