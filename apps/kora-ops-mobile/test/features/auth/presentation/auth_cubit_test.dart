import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/auth_session.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/auth_user.dart';
import 'package:kora_ops_mobile/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/auth_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

void main() {
  late MockAuthRepository repository;

  const AuthUser user = AuthUser(
    uuid: 'u-1',
    name: 'Aïcha Hounkpè',
    email: 'aicha.hounkpe@kora-express.com',
    permissions: <String>['boarding.scan', 'departures.view'],
  );

  final AuthSession session = AuthSession(
    token: 'token-123',
    expiresAt: DateTime.now().add(const Duration(days: 7)),
    user: user,
  );

  setUp(() {
    repository = MockAuthRepository();
  });

  AuthCubit buildCubit() => AuthCubit(repository);

  group('checkAuthStatus', () {
    blocTest<AuthCubit, AuthState>(
      'émet [Loading, Authenticated] si une session valide existe déjà',
      build: () {
        when(() => repository.currentSession())
            .thenAnswer((_) async => Result<AuthSession?>.success(session));
        return buildCubit();
      },
      act: (AuthCubit cubit) => cubit.checkAuthStatus(),
      expect: () =>
          <AuthState>[const AuthLoading(), const AuthAuthenticated(user)],
    );

    blocTest<AuthCubit, AuthState>(
      'émet [Loading, Unauthenticated(savedIdentifier)] si aucune session mais un identifiant enregistré',
      build: () {
        when(() => repository.currentSession())
            .thenAnswer((_) async => const Result<AuthSession?>.success(null));
        when(() => repository.lastIdentifier())
            .thenAnswer((_) async => 'aicha.hounkpe@kora-express.com');
        return buildCubit();
      },
      act: (AuthCubit cubit) => cubit.checkAuthStatus(),
      expect: () => <AuthState>[
        const AuthLoading(),
        const AuthUnauthenticated(
            savedIdentifier: 'aicha.hounkpe@kora-express.com'),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'émet [Loading, Unauthenticated(null)] si aucune session ni identifiant enregistré',
      build: () {
        when(() => repository.currentSession())
            .thenAnswer((_) async => const Result<AuthSession?>.success(null));
        when(() => repository.lastIdentifier()).thenAnswer((_) async => null);
        return buildCubit();
      },
      act: (AuthCubit cubit) => cubit.checkAuthStatus(),
      expect: () =>
          <AuthState>[const AuthLoading(), const AuthUnauthenticated()],
    );

    blocTest<AuthCubit, AuthState>(
      'émet [Loading, AuthCheckFailed] si la lecture locale échoue (ex. stockage inaccessible)',
      build: () {
        when(() => repository.currentSession()).thenAnswer((_) async =>
            const Result<AuthSession?>.failure(
                UnknownFailure('stockage sécurisé inaccessible')));
        when(() => repository.lastIdentifier()).thenAnswer((_) async => null);
        return buildCubit();
      },
      act: (AuthCubit cubit) => cubit.checkAuthStatus(),
      expect: () => <dynamic>[
        const AuthLoading(),
        isA<AuthCheckFailed>(),
      ],
    );
  });

  blocTest<AuthCubit, AuthState>(
    'authenticate() émet directement AuthAuthenticated',
    build: buildCubit,
    act: (AuthCubit cubit) => cubit.authenticate(user),
    expect: () => <AuthState>[const AuthAuthenticated(user)],
  );

  group('sessionExpired', () {
    blocTest<AuthCubit, AuthState>(
      'purge la session et émet Unauthenticated(savedIdentifier conservé) sur un 401',
      build: () {
        when(() => repository.purgeLocalSession()).thenAnswer((_) async {});
        when(() => repository.lastIdentifier())
            .thenAnswer((_) async => 'aicha.hounkpe@kora-express.com');
        return buildCubit();
      },
      act: (AuthCubit cubit) => cubit.sessionExpired(),
      expect: () => <AuthState>[
        const AuthUnauthenticated(
            savedIdentifier: 'aicha.hounkpe@kora-express.com'),
      ],
      verify: (_) {
        // Pas d'appel réseau ici : le serveur a déjà rejeté ce token,
        // relancer logout() referait un 401 qui redéclencherait
        // SessionExpiryInterceptor en boucle infinie.
        verify(() => repository.purgeLocalSession()).called(1);
        verifyNever(() => repository.logout());
      },
    );
  });

  group('logout', () {
    blocTest<AuthCubit, AuthState>(
      'émet [Loading, Unauthenticated(savedIdentifier conservé)]',
      build: () {
        when(() => repository.logout())
            .thenAnswer((_) async => const Result<void>.success(null));
        when(() => repository.lastIdentifier())
            .thenAnswer((_) async => 'aicha.hounkpe@kora-express.com');
        return buildCubit();
      },
      act: (AuthCubit cubit) => cubit.logout(),
      expect: () => <AuthState>[
        const AuthLoading(),
        const AuthUnauthenticated(
            savedIdentifier: 'aicha.hounkpe@kora-express.com'),
      ],
    );

    blocTest<AuthCubit, AuthState>(
      'reste déconnecté localement même si l’appel réseau de logout échoue',
      build: () {
        when(() => repository.logout()).thenAnswer(
            (_) async => const Result<void>.failure(NetworkFailure()));
        when(() => repository.lastIdentifier())
            .thenAnswer((_) async => 'aicha.hounkpe@kora-express.com');
        return buildCubit();
      },
      act: (AuthCubit cubit) => cubit.logout(),
      expect: () => <AuthState>[
        const AuthLoading(),
        const AuthUnauthenticated(
            savedIdentifier: 'aicha.hounkpe@kora-express.com'),
      ],
    );
  });
}
