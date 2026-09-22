import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/tracking/data/datasources/tracking_local_datasource.dart';
import 'package:kora_ops_mobile/features/tracking/data/datasources/tracking_remote_datasource.dart';
import 'package:kora_ops_mobile/features/tracking/data/tracking_sync_engine.dart';
import 'package:kora_ops_mobile/features/tracking/domain/entities/tracking_position.dart';
import 'package:kora_ops_mobile/features/tracking/domain/entities/tracking_position_source.dart';
import 'package:kora_ops_mobile/features/tracking/domain/entities/tracking_position_status.dart';
import 'package:kora_ops_mobile/features/tracking/domain/entities/tracking_session_snapshot.dart';
import 'package:kora_ops_mobile/features/tracking/domain/services/location_fix.dart';
import 'package:mocktail/mocktail.dart';

/// Faux TrackingLocalDataSource en mémoire — un vrai sqlite n'est pas
/// nécessaire ici : c'est le COMPORTEMENT de [TrackingSyncEngine] qui est
/// sous test (idempotence, ordre, tolérance réseau), pas la persistance
/// elle-même. Même principe que les mocks des autres datasources locales
/// dans ce projet (voir `incident_repository_impl_test.dart`), en version
/// « fake » plutôt que « mock » car le test a besoin d'un état qui évolue
/// réellement entre les appels (séquence, statut de sync).
class InMemoryTrackingLocalDataSource implements TrackingLocalDataSource {
  final List<TrackingPosition> _queue = <TrackingPosition>[];
  int _lastSequence = 0;

  @override
  Future<void> enqueue(TrackingPosition position, {required bool synced}) async {
    _queue.removeWhere((TrackingPosition p) => p.positionUuid == position.positionUuid);
    _queue.add(position);
    _syncStatus[position.positionUuid] = synced;
  }

  final Map<String, bool> _syncStatus = <String, bool>{};

  @override
  Future<List<TrackingPosition>> listPending(String departureUuid) async {
    final List<TrackingPosition> pending = _queue
        .where((TrackingPosition p) =>
            p.departureUuid == departureUuid && _syncStatus[p.positionUuid] == false)
        .toList()
      ..sort((TrackingPosition a, TrackingPosition b) => a.sequence.compareTo(b.sequence));
    return pending;
  }

  @override
  Future<int> totalPendingCount() async =>
      _syncStatus.values.where((bool synced) => !synced).length;

  @override
  Future<void> markSynced(String positionUuid) async {
    _syncStatus[positionUuid] = true;
  }

  @override
  Future<void> pruneSynced({required Duration olderThan}) async {}

  @override
  Future<TrackingSessionSnapshot?> readSession() async => _session;

  @override
  Future<void> saveSession(TrackingSessionSnapshot session) async {
    _session = session;
  }

  TrackingSessionSnapshot? _session;

  @override
  Future<int> nextSequence() async {
    _lastSequence++;
    return _lastSequence;
  }

  @override
  Future<void> clearSession() async {
    _session = null;
  }

  int countFor(String departureUuid) => _queue
      .where((TrackingPosition p) => p.departureUuid == departureUuid)
      .length;
}

class MockTrackingRemoteDataSource extends Mock
    implements TrackingRemoteDataSource {}

void main() {
  setUpAll(() {
    registerFallbackValue(<TrackingPosition>[]);
  });

  late InMemoryTrackingLocalDataSource local;
  late MockTrackingRemoteDataSource remote;
  late TrackingSyncEngine engine;

  const String departureUuid = 'dep-uuid-1';

  final LocationFix fix = LocationFix(
    latitude: 6.3654,
    longitude: 2.4183,
    accuracyMeters: 8,
    timestamp: DateTime(2026, 9, 22, 9, 14),
    source: TrackingPositionSource.gps,
    speedKmh: 45,
    headingDegrees: 90,
  );

  setUp(() {
    local = InMemoryTrackingLocalDataSource();
    remote = MockTrackingRemoteDataSource();
    engine = TrackingSyncEngine(local, remote);
  });

  TrackingBatchResult acceptedResult(List<TrackingPosition> positions) =>
      TrackingBatchResult(
        sessionUuid: 'session-1',
        accepted: positions.length,
        duplicate: 0,
        rejectedUuids: const <String>[],
      );

  group('enqueueFix / enqueueGpsUnavailable', () {
    test('attribue des séquences strictement croissantes', () async {
      final TrackingPosition first = await engine.enqueueFix(departureUuid, fix);
      final TrackingPosition second = await engine.enqueueFix(departureUuid, fix);
      final TrackingPosition heartbeat =
          await engine.enqueueGpsUnavailable(departureUuid);

      expect(first.sequence, 1);
      expect(second.sequence, 2);
      expect(heartbeat.sequence, 3);
      expect(heartbeat.status, TrackingPositionStatus.gpsUnavailable);
      expect(heartbeat.latitude, isNull);
    });
  });

  group('flush()', () {
    test('envoie la file et marque tout comme synchronisé (accepté)', () async {
      await engine.enqueueFix(departureUuid, fix);
      await engine.enqueueFix(departureUuid, fix);

      when(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).thenAnswer((Invocation invocation) async => acceptedResult(
            invocation.namedArguments[#positions] as List<TrackingPosition>,
          ));

      await engine.flush(departureUuid);

      expect(await local.listPending(departureUuid), isEmpty);
      verify(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).called(1);
    });

    test('idempotent : un doublon renvoyé par le serveur est aussi marqué synchronisé',
        () async {
      await engine.enqueueFix(departureUuid, fix);

      when(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).thenAnswer((_) async => const TrackingBatchResult(
            sessionUuid: 'session-1',
            accepted: 0,
            duplicate: 1,
            rejectedUuids: <String>[],
          ));

      await engine.flush(departureUuid);

      expect(await local.listPending(departureUuid), isEmpty);
    });

    test('une coupure réseau ne perd aucun point : tout reste en file', () async {
      await engine.enqueueFix(departureUuid, fix);
      await engine.enqueueFix(departureUuid, fix);

      when(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).thenThrow(DioException(
        requestOptions: RequestOptions(path: '/api/v1/ops/tracking/positions/batch'),
        type: DioExceptionType.connectionError,
      ));

      await engine.flush(departureUuid);

      expect(await local.listPending(departureUuid), hasLength(2));
      expect(engine.isOnline, isFalse);
    });

    test('un rejeu après reconnexion transmet les points restés en file, sans doublon',
        () async {
      await engine.enqueueFix(departureUuid, fix);

      when(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).thenThrow(DioException(
        requestOptions: RequestOptions(path: '/api/v1/ops/tracking/positions/batch'),
        type: DioExceptionType.connectionError,
      ));
      await engine.flush(departureUuid);
      expect(engine.isOnline, isFalse);

      // Un flush() alors qu'on est marqué hors ligne ne fait rien (voir
      // TrackingTaskHandler._onConnectivityChanged, qui appelle
      // markOnline() avant de relancer flush()).
      await engine.flush(departureUuid);
      verify(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).called(1);

      engine.markOnline();
      when(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).thenAnswer((Invocation invocation) async => acceptedResult(
            invocation.namedArguments[#positions] as List<TrackingPosition>,
          ));

      await engine.flush(departureUuid);

      expect(await local.listPending(departureUuid), isEmpty);
      // 2 appels au total (échec réseau + succès après reconnexion), mais
      // toujours pour LE MÊME point unique — jamais recréé ni dupliqué
      // localement malgré la tentative précédente échouée.
      verify(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).called(2);
      expect(local.countFor(departureUuid), 1);
    });

    test('découpe la file en lots de taille bornée', () async {
      for (int i = 0; i < TrackingSyncEngine.batchChunkSize + 5; i++) {
        await engine.enqueueFix(departureUuid, fix);
      }

      when(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).thenAnswer((Invocation invocation) async => acceptedResult(
            invocation.namedArguments[#positions] as List<TrackingPosition>,
          ));

      await engine.flush(departureUuid);

      verify(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).called(2);
      expect(await local.listPending(departureUuid), isEmpty);
    });

    test('une position rejetée définitivement (horodatage) n\'est plus réessayée',
        () async {
      final TrackingPosition position = await engine.enqueueFix(departureUuid, fix);

      when(() => remote.sendBatch(
            departureUuid: departureUuid,
            positions: any(named: 'positions'),
          )).thenAnswer((_) async => TrackingBatchResult(
            sessionUuid: 'session-1',
            accepted: 0,
            duplicate: 0,
            rejectedUuids: <String>[position.positionUuid],
          ));

      await engine.flush(departureUuid);

      expect(await local.listPending(departureUuid), isEmpty);
    });
  });
}
