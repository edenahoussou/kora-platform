import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show ForbiddenApiException, NetworkApiException, ValidationApiException;
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/features/terminal/data/datasources/terminal_local_datasource.dart';
import 'package:kora_ops_mobile/features/terminal/data/datasources/terminal_remote_datasource.dart';
import 'package:kora_ops_mobile/features/terminal/data/repositories/terminal_repository_impl.dart';
import 'package:kora_ops_mobile/features/terminal/domain/entities/terminal.dart';
import 'package:kora_ops_mobile/features/terminal/domain/entities/terminal_activity_type.dart';
import 'package:kora_ops_mobile/features/terminal/domain/entities/terminal_status.dart';
import 'package:mocktail/mocktail.dart';

class MockTerminalRemoteDataSource extends Mock
    implements TerminalRemoteDataSource {}

class MockTerminalLocalDataSource extends Mock
    implements TerminalLocalDataSource {}

DioException _dioError(Object apiException) {
  return DioException(
    requestOptions:
        RequestOptions(path: '/api/v1/ops-mobile/terminal/handshake'),
    error: apiException,
    type: DioExceptionType.badResponse,
  );
}

void main() {
  // Fallback requis par mocktail : `any()`/`captureAny()` est utilisé sur
  // `TerminalActivityType` (type non primitif) dans certains appels
  // ci-dessous (`verifyNever(() => remote.logActivity(any(), any()))`)
  // — voir même remarque dans auth_repository_impl_test.dart.
  setUpAll(() {
    registerFallbackValue(TerminalActivityType.scan);
  });

  late MockTerminalRemoteDataSource remote;
  late MockTerminalLocalDataSource local;
  late TerminalRepositoryImpl repository;

  const Terminal terminal = Terminal(
    uuid: 't-1',
    serialNumber: 'P501-000123',
    status: TerminalStatus.active,
  );

  setUp(() {
    remote = MockTerminalRemoteDataSource();
    local = MockTerminalLocalDataSource();
    repository = TerminalRepositoryImpl(remote, local);
  });

  group('handshake', () {
    test('succès : met en cache et retourne le terminal', () async {
      when(() => remote.handshake('P501-000123'))
          .thenAnswer((_) async => terminal);
      when(() => local.saveTerminal(terminal)).thenAnswer((_) async {});

      final result = await repository.handshake('P501-000123');

      expect(result.isSuccess, isTrue);
      verify(() => local.saveTerminal(terminal)).called(1);
    });

    test('terminal_unknown → TerminalUnknownFailure', () async {
      when(() => remote.handshake('BAD')).thenThrow(
        const TerminalApiException(
          TerminalApiException.unknown,
          'Ce terminal n’est pas enregistré.',
        ),
      );

      final result = await repository.handshake('BAD');

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<TerminalUnknownFailure>()),
      );
    });

    test('terminal_lost → TerminalNotUsableFailure(code: terminal_lost)',
        () async {
      when(() => remote.handshake('P501-LOST')).thenThrow(
        const TerminalApiException(TerminalApiException.lost, 'Perdu.'),
      );

      final result = await repository.handshake('P501-LOST');

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) {
          expect(failure, isA<TerminalNotUsableFailure>());
          expect(
            (failure as TerminalNotUsableFailure).code,
            TerminalApiException.lost,
          );
        },
      );
    });

    test('403 sans code métier → TerminalNotUsableFailure(terminal_inactive)',
        () async {
      when(() => remote.handshake('P501-000123'))
          .thenThrow(_dioError(const ForbiddenApiException('Accès refusé.')));

      final result = await repository.handshake('P501-000123');

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) {
          expect(failure, isA<TerminalNotUsableFailure>());
          expect((failure as TerminalNotUsableFailure).code,
              TerminalApiException.inactive);
        },
      );
    });

    test('erreur réseau → NetworkFailure', () async {
      when(() => remote.handshake('P501-000123'))
          .thenThrow(_dioError(const NetworkApiException()));

      final result = await repository.handshake('P501-000123');

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<NetworkFailure>()),
      );
    });

    test('422 → ValidationFailure', () async {
      when(() => remote.handshake('')).thenThrow(
        _dioError(const ValidationApiException('Numéro de série requis.')),
      );

      final result = await repository.handshake('');

      result.when(
        onSuccess: (_) => fail('devait échouer'),
        onFailure: (failure) => expect(failure, isA<ValidationFailure>()),
      );
    });
  });

  group('logActivity', () {
    test('enregistre localement puis envoie et marque synchronisé', () async {
      when(() => local.recordActivity(
            TerminalActivityType.scan,
            context: any(named: 'context'),
            localRecordedAt: any(named: 'localRecordedAt'),
          )).thenAnswer((_) async => 42);
      when(() => local.readSerialNumber())
          .thenAnswer((_) async => 'P501-000123');
      when(() => remote.logActivity(
            'P501-000123',
            TerminalActivityType.scan,
            context: any(named: 'context'),
          )).thenAnswer((_) async {});
      when(() => local.markActivitySynced(42)).thenAnswer((_) async {});

      await repository.logActivity(TerminalActivityType.scan);

      verify(() => local.recordActivity(
            TerminalActivityType.scan,
            context: any(named: 'context'),
            localRecordedAt: any(named: 'localRecordedAt'),
          )).called(1);
      verify(() => local.markActivitySynced(42)).called(1);
    });

    test('échec réseau : reste enregistré localement, jamais rethrow',
        () async {
      when(() => local.recordActivity(
            TerminalActivityType.error,
            context: any(named: 'context'),
            localRecordedAt: any(named: 'localRecordedAt'),
          )).thenAnswer((_) async => 7);
      when(() => local.readSerialNumber())
          .thenAnswer((_) async => 'P501-000123');
      when(() => remote.logActivity(
            'P501-000123',
            TerminalActivityType.error,
            context: any(named: 'context'),
          )).thenThrow(_dioError(const NetworkApiException()));

      await repository.logActivity(TerminalActivityType.error);

      verifyNever(() => local.markActivitySynced(any()));
    });

    test('aucun terminal lié : enregistre localement, ne tente pas le réseau',
        () async {
      when(() => local.recordActivity(
            TerminalActivityType.print,
            context: any(named: 'context'),
            localRecordedAt: any(named: 'localRecordedAt'),
          )).thenAnswer((_) async => 3);
      when(() => local.readSerialNumber()).thenAnswer((_) async => null);

      await repository.logActivity(TerminalActivityType.print);

      verifyNever(() => remote.logActivity(any(), any()));
    });
  });
}
