import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/user_entity.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_current_user.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_saved_email.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/logout_user.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockGetCurrentUser extends Mock implements GetCurrentUser {}

class MockGetSavedEmail extends Mock implements GetSavedEmail {}

class MockLogoutUser extends Mock implements LogoutUser {}

void main() {
  late MockGetCurrentUser getCurrentUser;
  late MockGetSavedEmail getSavedEmail;
  late MockLogoutUser logoutUser;

  const UserEntity user = UserEntity(
    id: '1',
    fullName: 'aicha.hounkpe',
    role: 'Convoyeur',
    email: 'aicha.hounkpe@example.com',
  );

  setUp(() {
    getCurrentUser = MockGetCurrentUser();
    getSavedEmail = MockGetSavedEmail();
    logoutUser = MockLogoutUser();
  });

  AuthCubit buildCubit() => AuthCubit(
        getCurrentUser: getCurrentUser,
        getSavedEmail: getSavedEmail,
        logoutUser: logoutUser,
      );

  blocTest<AuthCubit, AuthState>(
    'émet [Loading, Authenticated] si une session existe déjà',
    build: () {
      when(() => getCurrentUser())
          .thenAnswer((_) async => const Result<UserEntity?>.success(user));
      return buildCubit();
    },
    act: (AuthCubit cubit) => cubit.checkAuthStatus(),
    expect: () =>
        <AuthState>[const AuthLoading(), const AuthAuthenticated(user)],
  );

  blocTest<AuthCubit, AuthState>(
    'émet [Loading, Unauthenticated(savedEmail)] si aucune session mais un email enregistré',
    build: () {
      when(() => getCurrentUser())
          .thenAnswer((_) async => const Result<UserEntity?>.success(null));
      when(() => getSavedEmail()).thenAnswer((_) async =>
          const Result<String?>.success('aicha.hounkpe@example.com'));
      return buildCubit();
    },
    act: (AuthCubit cubit) => cubit.checkAuthStatus(),
    expect: () => <AuthState>[
      const AuthLoading(),
      const AuthUnauthenticated(savedEmail: 'aicha.hounkpe@example.com'),
    ],
  );

  blocTest<AuthCubit, AuthState>(
    'émet [Loading, Unauthenticated(null)] si aucune session ni email enregistré',
    build: () {
      when(() => getCurrentUser())
          .thenAnswer((_) async => const Result<UserEntity?>.success(null));
      when(() => getSavedEmail())
          .thenAnswer((_) async => const Result<String?>.success(null));
      return buildCubit();
    },
    act: (AuthCubit cubit) => cubit.checkAuthStatus(),
    expect: () => <AuthState>[const AuthLoading(), const AuthUnauthenticated()],
  );

  blocTest<AuthCubit, AuthState>(
    'authenticate() émet directement AuthAuthenticated',
    build: buildCubit,
    act: (AuthCubit cubit) => cubit.authenticate(user),
    expect: () => <AuthState>[const AuthAuthenticated(user)],
  );

  blocTest<AuthCubit, AuthState>(
    'logout() émet [Loading, Unauthenticated(savedEmail conservé)]',
    build: () {
      when(() => logoutUser())
          .thenAnswer((_) async => const Result<void>.success(null));
      when(() => getSavedEmail()).thenAnswer((_) async =>
          const Result<String?>.success('aicha.hounkpe@example.com'));
      return buildCubit();
    },
    act: (AuthCubit cubit) => cubit.logout(),
    expect: () => <AuthState>[
      const AuthLoading(),
      const AuthUnauthenticated(savedEmail: 'aicha.hounkpe@example.com'),
    ],
  );
}
