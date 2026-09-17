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
        _dioError(const ForbiddenApiException('Ce départ ne vous est pas affecté.')),
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
}
