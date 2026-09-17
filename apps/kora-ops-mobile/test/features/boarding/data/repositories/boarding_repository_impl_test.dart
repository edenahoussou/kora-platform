import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show NetworkApiException;
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/features/boarding/data/datasources/boarding_local_datasource.dart';
import 'package:kora_ops_mobile/features/boarding/data/datasources/boarding_remote_datasource.dart';
import 'package:kora_ops_mobile/features/boarding/data/repositories/boarding_repository_impl.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/boarding_outcome.dart';
import 'package:kora_ops_mobile/features/boarding/domain/entities/boarding_outcome_status.dart';
import 'package:mocktail/mocktail.dart';

class MockBoardingRemoteDataSource extends Mock
    implements BoardingRemoteDataSource {}

class MockBoardingLocalDataSource extends Mock
    implements BoardingLocalDataSource {}

DioException _networkError() {
  return DioException(
    requestOptions: RequestOptions(path: '/api/v1/ops/boarding'),
    error: const NetworkApiException(),
    type: DioExceptionType.connectionError,
  );
}

void main() {
  setUpAll(() {
    registerFallbackValue(
      const BoardingOutcome(
        status: BoardingOutcomeStatus.valid,
        message: 'fallback',
      ),
    );
  });

  late MockBoardingRemoteDataSource remote;
  late MockBoardingLocalDataSource local;
  late BoardingRepositoryImpl repository;

  const String departureUuid = 'dep-uuid-1';
  final DateTime localRecordedAt = DateTime(2026, 9, 15, 8);

  setUp(() {
    remote = MockBoardingRemoteDataSource();
    local = MockBoardingLocalDataSource();
    repository = BoardingRepositoryImpl(remote, local);

    // Par défaut : rien en cache local, l'appel réseau est donc toujours
    // tenté — chaque test qui a besoin d'un résultat déjà en cache le
    // stub explicitement.
    when(() => local.findConclusiveOutcome(
          departureUuid: any(named: 'departureUuid'),
          qrPayload: any(named: 'qrPayload'),
          shortCode: any(named: 'shortCode'),
        )).thenAnswer((_) async => null);
    when(() => local.recordOutcome(
          operationUuid: any(named: 'operationUuid'),
          departureUuid: any(named: 'departureUuid'),
          qrPayload: any(named: 'qrPayload'),
          shortCode: any(named: 'shortCode'),
          outcome: any(named: 'outcome'),
          localRecordedAt: any(named: 'localRecordedAt'),
          synced: any(named: 'synced'),
        )).thenAnswer((_) async {});
  });

  test('scan nominal : billet valide, enregistré comme synchronisé', () async {
    when(() => remote.scan(
          departureUuid: departureUuid,
          qrPayload: 'QR-123',
          shortCode: null,
          operationUuid: 'op-1',
          localRecordedAt: localRecordedAt,
        )).thenAnswer((_) async => const BoardingOutcome(
          status: BoardingOutcomeStatus.valid,
          message: 'Billet valide.',
          ticketUuid: 'ticket-1',
        ));

    final result = await repository.scan(
      departureUuid: departureUuid,
      qrPayload: 'QR-123',
      operationUuid: 'op-1',
      localRecordedAt: localRecordedAt,
    );

    result.when(
      onSuccess: (outcome) {
        expect(outcome.status, BoardingOutcomeStatus.valid);
        expect(outcome.ticketUuid, 'ticket-1');
      },
      onFailure: (_) => fail('devait réussir'),
    );
    verify(() => local.recordOutcome(
          operationUuid: 'op-1',
          departureUuid: departureUuid,
          qrPayload: 'QR-123',
          shortCode: null,
          outcome: any(named: 'outcome'),
          localRecordedAt: localRecordedAt,
          synced: true,
        )).called(1);
  });

  test(
      'double scan : un résultat déjà en cache local bloque immédiatement, '
      'sans appel réseau', () async {
    const BoardingOutcome cached = BoardingOutcome(
      status: BoardingOutcomeStatus.valid,
      message: 'Billet valide.',
      ticketUuid: 'ticket-1',
    );
    when(() => local.findConclusiveOutcome(
          departureUuid: departureUuid,
          qrPayload: 'QR-123',
          shortCode: null,
        )).thenAnswer((_) async => cached);

    final result = await repository.scan(
      departureUuid: departureUuid,
      qrPayload: 'QR-123',
      operationUuid: 'op-2',
      localRecordedAt: localRecordedAt,
    );

    result.when(
      onSuccess: (outcome) => expect(outcome, cached),
      onFailure: (_) => fail('devait réussir'),
    );
    verifyNever(() => remote.scan(
          departureUuid: any(named: 'departureUuid'),
          qrPayload: any(named: 'qrPayload'),
          shortCode: any(named: 'shortCode'),
          operationUuid: any(named: 'operationUuid'),
          localRecordedAt: any(named: 'localRecordedAt'),
        ));
  });

  test(
      'reprise après crash : une nouvelle instance du repository, adossée '
      'au même datasource local, retrouve le résultat déjà connu', () async {
    const BoardingOutcome cached = BoardingOutcome(
      status: BoardingOutcomeStatus.alreadyBoarded,
      message: 'Billet déjà embarqué.',
      ticketUuid: 'ticket-9',
    );
    when(() => local.findConclusiveOutcome(
          departureUuid: departureUuid,
          qrPayload: null,
          shortCode: 'AB12CD',
        )).thenAnswer((_) async => cached);

    // Nouvelle instance (simule un redémarrage à froid de l'app) — le
    // datasource local, lui, correspond à la même base sqlite persistée.
    final BoardingRepositoryImpl afterRestart =
        BoardingRepositoryImpl(remote, local);

    final result = await afterRestart.scan(
      departureUuid: departureUuid,
      shortCode: 'AB12CD',
      operationUuid: 'op-3',
      localRecordedAt: localRecordedAt,
    );

    result.when(
      onSuccess: (outcome) => expect(outcome, cached),
      onFailure: (_) => fail('devait réussir'),
    );
    verifyNever(() => remote.scan(
          departureUuid: any(named: 'departureUuid'),
          qrPayload: any(named: 'qrPayload'),
          shortCode: any(named: 'shortCode'),
          operationUuid: any(named: 'operationUuid'),
          localRecordedAt: any(named: 'localRecordedAt'),
        ));
  });

  test('mauvais départ : rejeté (422, code wrong_departure)', () async {
    when(() => remote.scan(
          departureUuid: departureUuid,
          qrPayload: 'QR-456',
          shortCode: null,
          operationUuid: 'op-4',
          localRecordedAt: localRecordedAt,
        )).thenAnswer((_) async => const BoardingOutcome(
          status: BoardingOutcomeStatus.wrongDeparture,
          message: 'Ce billet ne correspond pas à ce départ.',
          ticketUuid: 'ticket-2',
        ));

    final result = await repository.scan(
      departureUuid: departureUuid,
      qrPayload: 'QR-456',
      operationUuid: 'op-4',
      localRecordedAt: localRecordedAt,
    );

    result.when(
      onSuccess: (outcome) =>
          expect(outcome.status, BoardingOutcomeStatus.wrongDeparture),
      onFailure: (_) => fail('devait réussir (résultat métier, pas un échec)'),
    );
  });

  test('QR inconnu : 404 qr_unknown, enregistré pour bloquer un re-scan',
      () async {
    when(() => remote.scan(
          departureUuid: departureUuid,
          qrPayload: 'QR-GARBAGE',
          shortCode: null,
          operationUuid: 'op-5',
          localRecordedAt: localRecordedAt,
        )).thenThrow(const BoardingApiException(
      BoardingApiException.qrUnknown,
      'Billet inconnu ou signature invalide.',
    ));

    final result = await repository.scan(
      departureUuid: departureUuid,
      qrPayload: 'QR-GARBAGE',
      operationUuid: 'op-5',
      localRecordedAt: localRecordedAt,
    );

    result.when(
      onSuccess: (outcome) =>
          expect(outcome.status, BoardingOutcomeStatus.ticketUnknown),
      onFailure: (_) => fail('devait réussir (résultat métier, pas un échec)'),
    );
    verify(() => local.recordOutcome(
          operationUuid: 'op-5',
          departureUuid: departureUuid,
          qrPayload: 'QR-GARBAGE',
          shortCode: null,
          outcome: any(named: 'outcome'),
          localRecordedAt: localRecordedAt,
          synced: true,
        )).called(1);
  });

  test(
      'hors ligne : mis en file localement, retourne pendingSync sans '
      'échec', () async {
    when(() => remote.scan(
          departureUuid: departureUuid,
          qrPayload: 'QR-789',
          shortCode: null,
          operationUuid: 'op-6',
          localRecordedAt: localRecordedAt,
        )).thenThrow(_networkError());

    final result = await repository.scan(
      departureUuid: departureUuid,
      qrPayload: 'QR-789',
      operationUuid: 'op-6',
      localRecordedAt: localRecordedAt,
    );

    result.when(
      onSuccess: (outcome) =>
          expect(outcome.status, BoardingOutcomeStatus.pendingSync),
      onFailure: (_) => fail('l\'absence de réseau n\'est pas un échec'),
    );
    verify(() => local.recordOutcome(
          operationUuid: 'op-6',
          departureUuid: departureUuid,
          qrPayload: 'QR-789',
          shortCode: null,
          outcome: any(named: 'outcome'),
          localRecordedAt: localRecordedAt,
          synced: false,
        )).called(1);
  });

  test(
      'erreur technique (5xx) : Failure, jamais enregistrée localement '
      '(re-scannable)', () async {
    when(() => remote.scan(
          departureUuid: departureUuid,
          qrPayload: 'QR-999',
          shortCode: null,
          operationUuid: 'op-7',
          localRecordedAt: localRecordedAt,
        )).thenThrow(
      DioException(
        requestOptions: RequestOptions(path: '/api/v1/ops/boarding'),
        type: DioExceptionType.unknown,
      ),
    );

    final result = await repository.scan(
      departureUuid: departureUuid,
      qrPayload: 'QR-999',
      operationUuid: 'op-7',
      localRecordedAt: localRecordedAt,
    );

    result.when(
      onSuccess: (_) => fail('devait échouer'),
      onFailure: (failure) => expect(failure, isA<ServerFailure>()),
    );
    verifyNever(() => local.recordOutcome(
          operationUuid: any(named: 'operationUuid'),
          departureUuid: any(named: 'departureUuid'),
          qrPayload: any(named: 'qrPayload'),
          shortCode: any(named: 'shortCode'),
          outcome: any(named: 'outcome'),
          localRecordedAt: any(named: 'localRecordedAt'),
          synced: any(named: 'synced'),
        ));
  });

  group('reverse (annulation exceptionnelle d\'embarquement)', () {
    test('succès : Result.success', () async {
      when(() => remote.reverse(
            boardingEventUuid: 'event-1',
            reason: 'Erreur de scan',
          )).thenAnswer((_) async {});

      final result = await repository.reverse(
        boardingEventUuid: 'event-1',
        reason: 'Erreur de scan',
      );

      expect(result.isSuccess, isTrue);
    });

    test('422 (déjà non-accepté) : ValidationFailure', () async {
      when(() => remote.reverse(
            boardingEventUuid: 'event-2',
            reason: 'Erreur de scan',
          )).thenThrow(const BoardingApiException(
        'boarding_event_not_reversible',
        'Seul un embarquement accepté peut être annulé.',
      ));

      final result = await repository.reverse(
        boardingEventUuid: 'event-2',
        reason: 'Erreur de scan',
      );

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<ValidationFailure>()),
      );
    });

    test('hors ligne : NetworkFailure explicite, jamais mis en file',
        () async {
      when(() => remote.reverse(
            boardingEventUuid: 'event-3',
            reason: 'Erreur de scan',
          )).thenThrow(_networkError());

      final result = await repository.reverse(
        boardingEventUuid: 'event-3',
        reason: 'Erreur de scan',
      );

      result.when(
        onSuccess: (_) => fail('devait échouer (pas de file hors ligne ici)'),
        onFailure: (failure) => expect(failure, isA<NetworkFailure>()),
      );
    });
  });
}
