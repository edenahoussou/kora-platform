import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/exceptions/app_exceptions.dart';
import 'package:kora_ops_mobile/features/auth/data/datasources/auth_local_datasource.dart';
import 'package:kora_ops_mobile/features/auth/data/models/user_model.dart';
import 'package:kora_ops_mobile/features/auth/data/repositories/auth_repository_impl.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/device_info.dart';
import 'package:mocktail/mocktail.dart';

class MockAuthLocalDataSource extends Mock implements AuthLocalDataSource {}

void main() {
  late MockAuthLocalDataSource dataSource;
  late AuthRepositoryImpl repository;

  setUp(() {
    dataSource = MockAuthLocalDataSource();
    repository = AuthRepositoryImpl(dataSource);
  });

  group('getSavedEmail', () {
    test('retourne l\'email enregistré', () async {
      when(() => dataSource.getSavedEmail()).thenAnswer((_) async => 'aicha@example.com');

      final result = await repository.getSavedEmail();

      expect(result.isSuccess, isTrue);
      result.when(
        onSuccess: (email) => expect(email, 'aicha@example.com'),
        onFailure: (_) => fail('ne devrait pas échouer'),
      );
    });

    test('retourne null si aucun email n\'est enregistré', () async {
      when(() => dataSource.getSavedEmail()).thenAnswer((_) async => null);

      final result = await repository.getSavedEmail();

      result.when(onSuccess: (email) => expect(email, isNull), onFailure: (_) => fail('échec inattendu'));
    });
  });

  group('verifyOtp', () {
    test('retourne un UserEntity en cas de succès', () async {
      const UserModel user = UserModel(
        id: 'agent-001',
        fullName: 'aicha.hounkpe',
        role: 'Convoyeur',
        email: 'aicha.hounkpe@example.com',
      );
      when(() => dataSource.verifyOtp(email: any(named: 'email'), code: any(named: 'code')))
          .thenAnswer((_) async => user);

      final result = await repository.verifyOtp(email: user.email, code: '583187');

      expect(result.isSuccess, isTrue);
    });

    test('mappe ApiException (code invalide) en ValidationFailure', () async {
      when(() => dataSource.verifyOtp(email: any(named: 'email'), code: any(named: 'code')))
          .thenThrow(const ApiException('Le code est incorrect ou expiré.'));

      final result = await repository.verifyOtp(email: 'x@example.com', code: '000000');

      expect(result.isFailure, isTrue);
    });
  });

  group('verifyPin', () {
    test('retourne un UserEntity en cas de succès', () async {
      const UserModel user = UserModel(
        id: 'agent-001',
        fullName: 'aicha',
        role: 'Convoyeur',
        email: 'aicha@example.com',
      );
      when(() => dataSource.verifyPin(pin: any(named: 'pin'))).thenAnswer((_) async => user);

      final result = await repository.verifyPin(pin: '1234');

      expect(result.isSuccess, isTrue);
    });

    test('mappe ApiException (PIN incorrect) en ValidationFailure', () async {
      when(() => dataSource.verifyPin(pin: any(named: 'pin')))
          .thenThrow(const ApiException('PIN incorrect.'));

      final result = await repository.verifyPin(pin: '0000');

      expect(result.isFailure, isTrue);
    });
  });

  group('getCurrentDeviceInfo', () {
    test('retourne les informations mockées', () async {
      const DeviceInfo device = DeviceInfo(
        deviceModel: 'Tecno CAMON',
        city: 'Cotonou',
        attemptLabel: 'À l\'instant',
      );
      when(() => dataSource.getCurrentDeviceInfo()).thenAnswer((_) async => device);

      final result = await repository.getCurrentDeviceInfo();

      result.when(
        onSuccess: (info) => expect(info, device),
        onFailure: (_) => fail('ne devrait pas échouer'),
      );
    });
  });
}
