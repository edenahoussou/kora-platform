import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/exceptions/app_exceptions.dart';
import 'package:kora_ops_mobile/features/auth/data/datasources/auth_local_datasource.dart';
import 'package:kora_ops_mobile/features/auth/data/models/user_model.dart';
import 'package:kora_ops_mobile/features/auth/data/repositories/auth_repository_impl.dart';
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
      when(() => dataSource.getSavedEmail())
          .thenAnswer((_) async => 'aicha@example.com');

      final result = await repository.getSavedEmail();

      result.when(
        onSuccess: (email) => expect(email, 'aicha@example.com'),
        onFailure: (_) => fail('ne devrait pas échouer'),
      );
    });

    test('retourne null si aucun email n\'est enregistré', () async {
      when(() => dataSource.getSavedEmail()).thenAnswer((_) async => null);

      final result = await repository.getSavedEmail();

      result.when(
          onSuccess: (email) => expect(email, isNull),
          onFailure: (_) => fail('échec inattendu'));
    });
  });

  group('login', () {
    test('réussit avec des identifiants valides', () async {
      when(() => dataSource.login(
          email: any(named: 'email'),
          password: any(named: 'password'))).thenAnswer((_) async {});

      final result =
          await repository.login(email: 'x@example.com', password: 'secret');

      expect(result.isSuccess, isTrue);
    });

    test('mappe ApiException (identifiants invalides) en ValidationFailure',
        () async {
      when(() => dataSource.login(
              email: any(named: 'email'), password: any(named: 'password')))
          .thenThrow(const ApiException('Email ou mot de passe incorrect.'));

      final result =
          await repository.login(email: 'x@example.com', password: 'wrong');

      expect(result.isFailure, isTrue);
    });
  });

  group('forgotPassword', () {
    test('réussit pour un email connu', () async {
      when(() => dataSource.forgotPassword(email: any(named: 'email')))
          .thenAnswer((_) async {});

      final result = await repository.forgotPassword(email: 'x@example.com');

      expect(result.isSuccess, isTrue);
    });
  });

  group('verifyOtp', () {
    test('retourne un UserEntity en cas de succès', () async {
      const UserModel user = UserModel(
        id: 'agent-001',
        fullName: 'Aïcha Hounkpé',
        role: 'Convoyeur',
        email: 'aicha.hounkpe@kora-express.com',
      );
      when(() => dataSource.verifyOtp(
          email: any(named: 'email'),
          code: any(named: 'code'))).thenAnswer((_) async => user);

      final result =
          await repository.verifyOtp(email: user.email, code: '583187');

      expect(result.isSuccess, isTrue);
    });

    test('mappe ApiException (code invalide) en ValidationFailure', () async {
      when(() => dataSource.verifyOtp(
              email: any(named: 'email'), code: any(named: 'code')))
          .thenThrow(const ApiException('Le code est incorrect ou expiré.'));

      final result =
          await repository.verifyOtp(email: 'x@example.com', code: '000000');

      expect(result.isFailure, isTrue);
    });
  });

  group('verifyPin', () {
    test('retourne un UserEntity en cas de succès', () async {
      const UserModel user = UserModel(
        id: 'agent-001',
        fullName: 'Aïcha',
        role: 'Convoyeur',
        email: 'aicha@example.com',
      );
      when(() => dataSource.verifyPin(
          email: any(named: 'email'),
          pin: any(named: 'pin'))).thenAnswer((_) async => user);

      final result = await repository.verifyPin(email: user.email, pin: '1234');

      expect(result.isSuccess, isTrue);
    });

    test('mappe ApiException (PIN incorrect) en ValidationFailure', () async {
      when(() => dataSource.verifyPin(
              email: any(named: 'email'), pin: any(named: 'pin')))
          .thenThrow(const ApiException('PIN incorrect.'));

      final result =
          await repository.verifyPin(email: 'x@example.com', pin: '0000');

      expect(result.isFailure, isTrue);
    });
  });
}
