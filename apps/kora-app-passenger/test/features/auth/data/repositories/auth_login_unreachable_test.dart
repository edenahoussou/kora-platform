// Une requête sans réponse : hors ligne, ou serveur qui traîne ?
//
// Les deux cas se ressemblent — aucun code HTTP, rien à lire — mais ils
// ne demandent pas la même chose au voyageur. « Vérifie ta connexion
// internet » l'envoie chercher une panne chez lui, alors qu'un appel
// bloqué côté serveur expire au bout de quinze secondes sans qu'il
// puisse rien y faire. C'est ce qui est arrivé à la connexion par
// e-mail, dont l'appel restait suspendu en production.

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/auth/data/datasources/auth_local_data_source.dart';
import 'package:kora_app_passenger/features/auth/data/datasources/auth_remote_data_source.dart';
import 'package:kora_app_passenger/features/auth/data/datasources/device_info_data_source.dart';
import 'package:kora_app_passenger/features/auth/data/repositories/auth_repository_impl.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_device.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/device_platform.dart';
import 'package:mocktail/mocktail.dart';

class _MockRemote extends Mock implements AuthRemoteDataSource {}

class _MockLocal extends Mock implements AuthLocalDataSource {}

class _StubDeviceInfo implements DeviceInfoDataSource {
  @override
  Future<DeviceRegistration> currentDevice({String? pushToken}) async =>
      const DeviceRegistration(
        name: 'Infinix X6525',
        platform: DevicePlatform.android,
        fingerprint: 'android-empreinte-de-test',
      );
}

/// Une erreur Dio sans réponse : le serveur n'a rien renvoyé.
DioException _silent(DioExceptionType type) => DioException(
  type: type,
  requestOptions: RequestOptions(path: '/api/v1/auth/login'),
);

void main() {
  late _MockRemote remote;
  late AuthRepositoryImpl repository;

  setUpAll(() {
    registerFallbackValue(
      const DeviceRegistration(
        name: 'Infinix X6525',
        platform: DevicePlatform.android,
        fingerprint: 'android-empreinte-de-test',
      ),
    );
  });

  setUp(() {
    remote = _MockRemote();
    repository = AuthRepositoryImpl(remote, _MockLocal(), _StubDeviceInfo());
  });

  /// Fait échouer la connexion avec [error] et rend l'échec traduit.
  Future<Failure> failureFor(DioException error) async {
    when(
      () => remote.login(
        identifier: any(named: 'identifier'),
        pin: any(named: 'pin'),
        device: any(named: 'device'),
        verification: any(named: 'verification'),
      ),
    ).thenThrow(error);

    final result = await repository.login(
      identifier: 'voyageur@example.com',
      pin: '1234',
    );

    return result.fold((failure) => failure, (_) => fail('succès inattendu'));
  }

  test('un serveur qui ne répond pas à temps n’accuse pas le réseau', () async {
    for (final type in [
      DioExceptionType.connectionTimeout,
      DioExceptionType.sendTimeout,
      DioExceptionType.receiveTimeout,
    ]) {
      final failure = await failureFor(_silent(type));

      expect(failure, isA<TimeoutFailure>(), reason: '$type');
      expect(failure.message, contains('trop de temps'));
      expect(failure.message, isNot(contains('connexion internet')));
    }
  });

  test('une connexion perdue reste une panne de réseau', () async {
    final failure = await failureFor(_silent(DioExceptionType.connectionError));

    expect(failure, isA<NetworkFailure>());
    expect(failure.message, contains('connexion internet'));
  });

  test('une réponse du serveur n’est jamais une panne de réseau', () async {
    // 504 : la passerelle a renoncé avant le serveur. Il y a bien une
    // réponse, donc un échec serveur — pas de quoi parler de réseau.
    final failure = await failureFor(
      DioException(
        requestOptions: RequestOptions(path: '/api/v1/auth/login'),
        response: Response<dynamic>(
          requestOptions: RequestOptions(path: '/api/v1/auth/login'),
          statusCode: 504,
        ),
      ),
    );

    expect(failure, isA<ServerFailure>());
  });
}
