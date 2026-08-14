import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:kora_ops_mobile/core/exceptions/app_exceptions.dart';
import 'package:kora_ops_mobile/features/auth/data/datasources/auth_local_datasource.dart';
import 'package:kora_ops_mobile/features/auth/data/models/user_model.dart';
import 'package:kora_ops_mobile/features/auth/data/repositories/auth_repository_impl.dart';

class MockAuthLocalDataSource extends Mock implements AuthLocalDataSource {}

void main() {
  late MockAuthLocalDataSource dataSource;
  late AuthRepositoryImpl repository;

  setUp(() {
    dataSource = MockAuthLocalDataSource();
    repository = AuthRepositoryImpl(dataSource);
  });

  test('mappe une UnauthorizedException en AuthenticationFailure', () async {
    when(() => dataSource.login(username: any(named: 'username'), password: any(named: 'password')))
        .thenThrow(const UnauthorizedException());

    final result = await repository.login(username: 'x', password: 'y');

    expect(result.isFailure, isTrue);
  });

  test('retourne le UserModel du datasource en cas de succès', () async {
    const UserModel user = UserModel(id: '1', fullName: 'Agent', role: 'Chauffeur');
    when(() => dataSource.login(username: any(named: 'username'), password: any(named: 'password')))
        .thenAnswer((_) async => user);

    final result = await repository.login(username: 'x', password: 'y');

    expect(result.isSuccess, isTrue);
  });
}
