import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show NetworkApiException, ForbiddenApiException;
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/features/incidents/data/datasources/incident_local_datasource.dart';
import 'package:kora_ops_mobile/features/incidents/data/datasources/incident_remote_datasource.dart';
import 'package:kora_ops_mobile/features/incidents/data/repositories/incident_repository_impl.dart';
import 'package:kora_ops_mobile/features/incidents/domain/entities/incident.dart';
import 'package:mocktail/mocktail.dart';

class MockIncidentRemoteDataSource extends Mock
    implements IncidentRemoteDataSource {}

class MockIncidentLocalDataSource extends Mock
    implements IncidentLocalDataSource {}

DioException _networkError() => DioException(
      requestOptions: RequestOptions(path: '/api/v1/ops/incidents'),
      error: const NetworkApiException(),
      type: DioExceptionType.connectionError,
    );

DioException _serverError(Object apiException) => DioException(
      requestOptions: RequestOptions(path: '/api/v1/ops/incidents'),
      error: apiException,
      type: DioExceptionType.badResponse,
    );

void main() {
  setUpAll(() {
    registerFallbackValue(
      Incident(
        operationUuid: 'fallback',
        departureUuid: 'fallback',
        type: IncidentType.other,
        description: 'fallback',
        occurredAt: DateTime(2026, 1, 1),
        pendingSync: false,
      ),
    );
    // Nécessaire séparément de l'`Incident` ci-dessus : utilisé seul via
    // `any(named: 'type')` dans les stubs de `remote.report`.
    registerFallbackValue(IncidentType.other);
  });

  late MockIncidentRemoteDataSource remote;
  late MockIncidentLocalDataSource local;
  late IncidentRepositoryImpl repository;

  const String departureUuid = 'dep-uuid-1';
  final DateTime localRecordedAt = DateTime(2026, 9, 22, 9, 14);

  setUp(() {
    remote = MockIncidentRemoteDataSource();
    local = MockIncidentLocalDataSource();
    repository = IncidentRepositoryImpl(remote, local);

    when(() => local.record(any(), synced: any(named: 'synced')))
        .thenAnswer((_) async {});
  });

  group('report()', () {
    test('en ligne : transmis puis enregistré comme synchronisé', () async {
      when(() => remote.report(
            operationUuid: 'op-1',
            departureUuid: departureUuid,
            type: IncidentType.breakdown,
            description: 'Crevaison au niveau de Ouidah.',
            occurredAt: localRecordedAt,
            latitude: null,
            longitude: null,
          )).thenAnswer((_) async {});

      final result = await repository.report(
        operationUuid: 'op-1',
        departureUuid: departureUuid,
        type: IncidentType.breakdown,
        description: 'Crevaison au niveau de Ouidah.',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (incident) {
          expect(incident.pendingSync, isFalse);
          expect(incident.operationUuid, 'op-1');
        },
        onFailure: (_) => fail('devait réussir'),
      );
      verify(() => local.record(any(), synced: true)).called(1);
    });

    test(
      'hors ligne : mis en file localement, jamais un échec — '
      'pendingSync: true',
      () async {
        when(() => remote.report(
              operationUuid: any(named: 'operationUuid'),
              departureUuid: any(named: 'departureUuid'),
              type: any(named: 'type'),
              description: any(named: 'description'),
              occurredAt: any(named: 'occurredAt'),
              latitude: any(named: 'latitude'),
              longitude: any(named: 'longitude'),
            )).thenThrow(_networkError());

        final result = await repository.report(
          operationUuid: 'op-2',
          departureUuid: departureUuid,
          type: IncidentType.accident,
          description: 'Accrochage mineur au dépôt.',
          localRecordedAt: localRecordedAt,
        );

        result.when(
          onSuccess: (incident) => expect(incident.pendingSync, isTrue),
          onFailure: (_) => fail('une coupure réseau n\'est pas un échec'),
        );
        verify(() => local.record(any(), synced: false)).called(1);
      },
    );

    test('erreur serveur (ex. permission refusée) → Result.failure', () async {
      when(() => remote.report(
            operationUuid: any(named: 'operationUuid'),
            departureUuid: any(named: 'departureUuid'),
            type: any(named: 'type'),
            description: any(named: 'description'),
            occurredAt: any(named: 'occurredAt'),
            latitude: any(named: 'latitude'),
            longitude: any(named: 'longitude'),
          )).thenThrow(_serverError(
        const ForbiddenApiException(
            'Votre compte n\'a pas la permission d\'utiliser KORA OPS Mobile.'),
      ));

      final result = await repository.report(
        operationUuid: 'op-3',
        departureUuid: departureUuid,
        type: IncidentType.security,
        description: 'Comportement suspect signalé par un passager.',
        localRecordedAt: localRecordedAt,
      );

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<AuthenticationFailure>()),
      );
      verifyNever(() => local.record(any(), synced: any(named: 'synced')));
    });
  });

  group('retryPending()', () {
    test('réémet chaque incident en attente et confirme les succès', () async {
      final Incident pendingA = Incident(
        operationUuid: 'a',
        departureUuid: departureUuid,
        type: IncidentType.delay,
        description: 'Retard au départ.',
        occurredAt: localRecordedAt,
        pendingSync: true,
      );
      final Incident pendingB = Incident(
        operationUuid: 'b',
        departureUuid: departureUuid,
        type: IncidentType.other,
        description: 'Autre incident.',
        occurredAt: localRecordedAt,
        pendingSync: true,
      );
      when(() => local.listPending())
          .thenAnswer((_) async => <Incident>[pendingA, pendingB]);
      when(() => remote.report(
            operationUuid: 'a',
            departureUuid: departureUuid,
            type: IncidentType.delay,
            description: 'Retard au départ.',
            occurredAt: localRecordedAt,
            latitude: null,
            longitude: null,
          )).thenAnswer((_) async {});
      when(() => remote.report(
            operationUuid: 'b',
            departureUuid: departureUuid,
            type: IncidentType.other,
            description: 'Autre incident.',
            occurredAt: localRecordedAt,
            latitude: null,
            longitude: null,
          )).thenThrow(_networkError());
      when(() => local.markSynced(any())).thenAnswer((_) async {});

      await repository.retryPending();

      verify(() => local.markSynced('a')).called(1);
      verifyNever(() => local.markSynced('b'));
    });
  });
}
