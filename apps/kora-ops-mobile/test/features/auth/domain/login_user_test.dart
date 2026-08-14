import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/user_entity.dart';
import 'package:kora_ops_mobile/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/login_user.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

void main() {
  late MockAuthRepository repository;
  late LoginUser useCase;

  setUp(() {
    repository = MockAuthRepository();
    useCase = LoginUser(repository);
  });

  const UserEntity user = UserEntity(id: '1', fullName: 'Agent Test', role: 'Convoyeur');

  test('retourne un UserEntity quand le repository réussit', () async {
    when(() => repository.login(username: 'agent', password: 'secret'))
        .thenAnswer((_) async => const Result<UserEntity>.success(user));

    final Result<UserEntity> result = await useCase(username: 'agent', password: 'secret');

    expect(result.isSuccess, isTrue);
    result.when(
      onSuccess: (UserEntity value) => expect(value, user),
      onFailure: (_) => fail('ne devrait pas échouer'),
    );
  });

  test('propage un Failure quand le repository échoue', () async {
    when(() => repository.login(username: 'agent', password: 'wrong')).thenAnswer(
      (_) async => const Result<UserEntity>.failure(AuthenticationFailure('Identifiants invalides.')),
    );

    final Result<UserEntity> result = await useCase(username: 'agent', password: 'wrong');

    expect(result.isFailure, isTrue);
  });
}
