import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show NetworkApiException;
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/features/manifeste/data/datasources/manifeste_local_datasource.dart';
import 'package:kora_ops_mobile/features/manifeste/data/datasources/manifeste_remote_datasource.dart';
import 'package:kora_ops_mobile/features/manifeste/data/repositories/manifeste_repository_impl.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';
import 'package:mocktail/mocktail.dart';

class MockManifesteRemoteDataSource extends Mock
    implements ManifesteRemoteDataSource {}

class MockManifesteLocalDataSource extends Mock
    implements ManifesteLocalDataSource {}

DioException _networkError() => DioException(
      requestOptions:
          RequestOptions(path: '/api/v1/ops/sync/manifest-versions'),
      error: const NetworkApiException(),
      type: DioExceptionType.connectionError,
    );

void main() {
  setUpAll(() {
    registerFallbackValue(<Passenger>[]);
  });

  late MockManifesteRemoteDataSource remote;
  late MockManifesteLocalDataSource local;

  const String departureUuid = 'dep-uuid-1';
  const Passenger passenger = Passenger(
    id: 'ticket-1',
    nomComplet: 'Aïcha Hounkpé',
    siege: '12A',
    statut: BoardingStatus.enAttente,
    segmentOrigine: 'Cotonou',
    segmentDestination: 'Porto-Novo',
    ticketId: 'AB12CD',
  );
  final DateTime fetchedAt = DateTime(2026, 8, 16, 7);

  ManifesteRepositoryImpl repositoryOnline({bool online = true}) =>
      ManifesteRepositoryImpl(remote, local, isOnline: () async => online);

  setUp(() {
    remote = MockManifesteRemoteDataSource();
    local = MockManifesteLocalDataSource();
  });

  test(
      'cache vide (jamais chargé) : télécharge tout, sans vérifier de '
      'version', () async {
    when(() => local.readCache(departureUuid)).thenAnswer((_) async => null);
    when(() => remote.fetch(departureUuid)).thenAnswer(
      (_) async => const ManifesteFetchResult(<Passenger>[passenger], 'v1'),
    );
    when(() => local.replaceCache(departureUuid, any(),
            manifestVersion: any(named: 'manifestVersion')))
        .thenAnswer((_) async {});

    final result = await repositoryOnline().load(departureUuid);

    result.when(
      onSuccess: (snapshot) {
        expect(snapshot.passengers.single, passenger);
        expect(snapshot.isFromCache, isFalse);
        expect(snapshot.isStale, isFalse);
      },
      onFailure: (_) => fail('devait réussir'),
    );
    verifyNever(() => remote.checkVersion(any(), any()));
  });

  test(
      'cache valide (version vérifiée à jour) : sert le cache sans '
      'retélécharger', () async {
    when(() => local.readCache(departureUuid)).thenAnswer((_) async =>
        CachedManifest(
            passengers: <Passenger>[passenger],
            version: 'v1',
            fetchedAt: fetchedAt));
    when(() => remote.checkVersion(departureUuid, 'v1')).thenAnswer(
      (_) async => const ManifestVersionCheck(
          currentVersion: 'v1', stale: false, accessible: true),
    );

    final result = await repositoryOnline().load(departureUuid);

    result.when(
      onSuccess: (snapshot) {
        expect(snapshot.passengers.single, passenger);
        expect(snapshot.isFromCache, isTrue);
        expect(snapshot.isStale, isFalse);
      },
      onFailure: (_) => fail('devait réussir'),
    );
    verifyNever(() => remote.fetch(any()));
  });

  test('cache périmé (version différente) : retélécharge', () async {
    when(() => local.readCache(departureUuid)).thenAnswer((_) async =>
        CachedManifest(
            passengers: <Passenger>[passenger],
            version: 'v1',
            fetchedAt: fetchedAt));
    when(() => remote.checkVersion(departureUuid, 'v1')).thenAnswer(
      (_) async => const ManifestVersionCheck(
          currentVersion: 'v2', stale: true, accessible: true),
    );
    when(() => remote.fetch(departureUuid)).thenAnswer(
      (_) async => const ManifesteFetchResult(<Passenger>[passenger], 'v2'),
    );
    when(() => local.replaceCache(departureUuid, any(),
            manifestVersion: any(named: 'manifestVersion')))
        .thenAnswer((_) async {});

    final result = await repositoryOnline().load(departureUuid);

    result.when(
      onSuccess: (snapshot) {
        expect(snapshot.version, 'v2');
        expect(snapshot.isFromCache, isFalse);
        expect(snapshot.isStale, isFalse);
      },
      onFailure: (_) => fail('devait réussir'),
    );
    verify(() => remote.fetch(departureUuid)).called(1);
  });

  test('départ non accessible (plus affecté) : échec', () async {
    when(() => local.readCache(departureUuid)).thenAnswer((_) async =>
        CachedManifest(
            passengers: <Passenger>[passenger],
            version: 'v1',
            fetchedAt: fetchedAt));
    when(() => remote.checkVersion(departureUuid, 'v1')).thenAnswer(
      (_) async => const ManifestVersionCheck(
          currentVersion: null, stale: false, accessible: false),
    );

    final result = await repositoryOnline().load(departureUuid);

    result.when(
      onSuccess: (_) => fail('devait échouer'),
      onFailure: (failure) => expect(failure, isA<AuthenticationFailure>()),
    );
  });

  test('absence réseau avec cache : sert le cache, marqué non vérifié',
      () async {
    when(() => local.readCache(departureUuid)).thenAnswer((_) async =>
        CachedManifest(
            passengers: <Passenger>[passenger],
            version: 'v1',
            fetchedAt: fetchedAt));

    final result = await repositoryOnline(online: false).load(departureUuid);

    result.when(
      onSuccess: (snapshot) {
        expect(snapshot.passengers.single, passenger);
        expect(snapshot.isStale, isTrue);
        expect(snapshot.isFromCache, isTrue);
      },
      onFailure: (_) => fail('devait réussir'),
    );
    verifyNever(() => remote.checkVersion(any(), any()));
    verifyNever(() => remote.fetch(any()));
  });

  test('absence réseau sans cache : échec', () async {
    when(() => local.readCache(departureUuid)).thenAnswer((_) async => null);

    final result = await repositoryOnline(online: false).load(departureUuid);

    result.when(
      onSuccess: (_) => fail('devait échouer'),
      onFailure: (failure) => expect(failure, isA<NetworkFailure>()),
    );
  });

  test(
      'la vérification de version échoue en réseau (avec cache) : repli '
      'sur le cache, marqué non vérifié', () async {
    when(() => local.readCache(departureUuid)).thenAnswer((_) async =>
        CachedManifest(
            passengers: <Passenger>[passenger],
            version: 'v1',
            fetchedAt: fetchedAt));
    when(() => remote.checkVersion(departureUuid, 'v1'))
        .thenThrow(_networkError());

    final result = await repositoryOnline().load(departureUuid);

    result.when(
      onSuccess: (snapshot) {
        expect(snapshot.isStale, isTrue);
        expect(snapshot.isFromCache, isTrue);
      },
      onFailure: (_) => fail('devait réussir (repli sur le cache)'),
    );
  });
}
