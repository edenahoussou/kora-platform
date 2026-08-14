import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/user_entity.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_current_user.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/login_user.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/logout_user.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';

class MockLoginUser extends Mock implements LoginUser {}

class MockLogoutUser extends Mock implements LogoutUser {}

class MockGetCurrentUser extends Mock implements GetCurrentUser {}

void main() {
  late MockLoginUser loginUser;
  late MockLogoutUser logoutUser;
  late MockGetCurrentUser getCurrentUser;

  const UserEntity user = UserEntity(id: '1', fullName: 'Agent Test', role: 'Convoyeur');

  setUp(() {
    loginUser = MockLoginUser();
    logoutUser = MockLogoutUser();
    getCurrentUser = MockGetCurrentUser();
  });

  AuthCubit buildCubit() => AuthCubit(
        loginUser: loginUser,
        logoutUser: logoutUser,
        getCurrentUser: getCurrentUser,
      );

  blocTest<AuthCubit, AuthState>(
    'émet [Loading, Authenticated] quand login réussit',
    build: () {
      when(() => loginUser(username: any(named: 'username'), password: any(named: 'password')))
          .thenAnswer((_) async => const Result<UserEntity>.success(user));
      return buildCubit();
    },
    act: (AuthCubit cubit) => cubit.login(username: 'agent', password: 'secret'),
    expect: () => <AuthState>[const AuthLoading(), const AuthAuthenticated(user)],
  );

  blocTest<AuthCubit, AuthState>(
    'émet [Loading, Failure] quand login échoue',
    build: () {
      when(() => loginUser(username: any(named: 'username'), password: any(named: 'password')))
          .thenAnswer((_) async => const Result<UserEntity>.failure(AuthenticationFailure('Erreur')));
      return buildCubit();
    },
    act: (AuthCubit cubit) => cubit.login(username: 'agent', password: 'bad'),
    expect: () => <AuthState>[const AuthLoading(), const AuthFailure('Erreur')],
  );
}
