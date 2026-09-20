import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show ForbiddenApiException, NetworkApiException;
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/features/ops/data/datasources/departure_remote_datasource.dart';
import 'package:kora_ops_mobile/features/ops/data/repositories/departure_repository_impl.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/depart.dart';
import 'package:mocktail/mocktail.dart';

class MockDepartureRemoteDataSource extends Mock
    implements DepartureRemoteDataSource {}

DioException _dioError(Object apiException) => DioException(
      requestOptions: RequestOptions(path: '/api/v1/ops/departures'),
      error: apiException,
      type: DioExceptionType.badResponse,
    );

void main() {
  late MockDepartureRemoteDataSource remote;
  late DepartureRepositoryImpl repository;

  const Depart depart = Depart(
    id: 'dep-uuid-1',
    route: 'Cotonou → Porto-Novo',
    date: '16/08/2026',
    heure: '07:30',
    vehiculeImmat: 'AB-1234-RB',
    totalPlaces: 45,
    placesOccupees: 0,
  );

  setUp(() {
    remote = MockDepartureRemoteDataSource();
    repository = DepartureRepositoryImpl(remote);
  });

  group('listAssigned', () {
    test('succès : retourne les départs affectés', () async {
      when(() => remote.list(date: null, status: null))
          .thenAnswer((_) async => const <Depart>[depart]);

      final result = await repository.listAssigned();

      result.when(
        onSuccess: (departs) => expect(departs, <Depart>[depart]),
        onFailure: (_) => fail('devait réussir'),
      );
    });

    test('liste vide (aucun départ affecté) : succès avec liste vide',
        () async {
      when(() => remote.list(date: null, status: null))
          .thenAnswer((_) async => const <Depart>[]);

      final result = await repository.listAssigned();

      result.when(
        onSuccess: (departs) => expect(departs, isEmpty),
        onFailure: (_) => fail('devait réussir'),
      );
    });

    test('erreur réseau → NetworkFailure', () async {
      when(() => remote.list(date: null, status: null))
          .thenThrow(_dioError(const NetworkApiException()));

      final result = await repository.listAssigned();

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<NetworkFailure>()),
      );
    });

    test('filtres date/status transmis tels quels', () async {
      when(() => remote.list(date: '2026-10-01', status: 'scheduled'))
          .thenAnswer((_) async => const <Depart>[depart]);

      await repository.listAssigned(date: '2026-10-01', status: 'scheduled');

      verify(() => remote.list(date: '2026-10-01', status: 'scheduled'))
          .called(1);
    });
  });

  group('show', () {
    test('403 (départ non affecté) → AuthenticationFailure', () async {
      when(() => remote.show('dep-uuid-1')).thenThrow(
        _dioError(
            const ForbiddenApiException('Ce départ ne vous est pas affecté.')),
      );

      final result = await repository.show('dep-uuid-1');

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<AuthenticationFailure>()),
      );
    });

    test('succès : retourne le détail complet', () async {
      when(() => remote.show('dep-uuid-1')).thenAnswer((_) async => depart);

      final result = await repository.show('dep-uuid-1');

      result.when(
        onSuccess: (d) => expect(d, depart),
        onFailure: (_) => fail('devait réussir'),
      );
    });
  });

  group('updateStatus (MEZ-98)', () {
    test('succès : retourne le statut confirmé par le serveur', () async {
      when(() => remote.updateStatus('dep-uuid-1', 'boarding'))
          .thenAnswer((_) async => 'boarding');

      final result = await repository.updateStatus('dep-uuid-1', 'boarding');

      result.when(
        onSuccess: (status) => expect(status, 'boarding'),
        onFailure: (_) => fail('devait réussir'),
      );
    });

    test('erreur serveur générique (ex. transition impossible) → ServerFailure',
        () async {
      when(() => remote.updateStatus('dep-uuid-1', 'arrived'))
          .thenThrow(_dioError('Transition impossible : Programmé → Arrivé.'));

      final result = await repository.updateStatus('dep-uuid-1', 'arrived');

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<ServerFailure>()),
      );
    });

    test('erreur réseau → NetworkFailure', () async {
      when(() => remote.updateStatus('dep-uuid-1', 'boarding'))
          .thenThrow(_dioError(const NetworkApiException()));

      final result = await repository.updateStatus('dep-uuid-1', 'boarding');

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<NetworkFailure>()),
      );
    });

    test('occurred_at transmis tel quel au datasource', () async {
      final DateTime occurredAt = DateTime(2026, 9, 21, 8, 7);
      when(() => remote.updateStatus('dep-uuid-1', 'departed',
          occurredAt: occurredAt)).thenAnswer((_) async => 'departed');

      await repository.updateStatus('dep-uuid-1', 'departed',
          occurredAt: occurredAt);

      verify(() => remote.updateStatus('dep-uuid-1', 'departed',
          occurredAt: occurredAt)).called(1);
    });
  });

  group('recordDelay (MEZ-98)', () {
    test('succès : transmet minutes et motif tels quels', () async {
      when(() => remote.recordDelay('dep-uuid-1', 25, 'Attente correspondance'))
          .thenAnswer((_) async {});

      final result = await repository.recordDelay(
          'dep-uuid-1', 25, 'Attente correspondance');

      expect(result.isSuccess, isTrue);
      verify(() =>
              remote.recordDelay('dep-uuid-1', 25, 'Attente correspondance'))
          .called(1);
    });

    test('0 minute (annulation d\'un retard) accepté', () async {
      when(() => remote.recordDelay('dep-uuid-1', 0, 'Retard résorbé'))
          .thenAnswer((_) async {});

      final result =
          await repository.recordDelay('dep-uuid-1', 0, 'Retard résorbé');

      expect(result.isSuccess, isTrue);
    });

    test('422 (départ déjà parti) → ServerFailure', () async {
      when(() => remote.recordDelay('dep-uuid-1', 10, 'Motif')).thenThrow(
          _dioError('Le retard ne peut être enregistré que pour un départ '
              'programmé ou en embarquement.'));

      final result = await repository.recordDelay('dep-uuid-1', 10, 'Motif');

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<ServerFailure>()),
      );
    });

    test('erreur réseau → NetworkFailure', () async {
      when(() => remote.recordDelay('dep-uuid-1', 10, 'Motif'))
          .thenThrow(_dioError(const NetworkApiException()));

      final result = await repository.recordDelay('dep-uuid-1', 10, 'Motif');

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<NetworkFailure>()),
      );
    });
  });
}
