import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/auth_session.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/auth_user.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/otp_challenge.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/otp_purpose.dart';
import 'package:kora_ops_mobile/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_ops_mobile/features/auth/presentation/bloc/auth_flow_bloc.dart';
import 'package:mocktail/mocktail.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

void main() {
  setUpAll(() {
    registerFallbackValue(OtpPurpose.loginNewDevice);
    registerFallbackValue(const OtpVerification(
      challengeUuid: 'fallback-chal',
      purpose: OtpPurpose.loginNewDevice,
      verificationToken: 'fallback-token',
    ));
  });

  late MockAuthRepository repository;

  const AuthUser user = AuthUser(
    uuid: 'u-1',
    name: 'Aïcha Hounkpè',
    email: 'aicha.hounkpe@kora-express.com',
  );

  final AuthSession session = AuthSession(
    token: 'tok',
    expiresAt: DateTime.now().add(const Duration(days: 7)),
    user: user,
  );

  final OtpChallengeRef challengeRef = OtpChallengeRef(
    uuid: 'chal-1',
    expiresAt: DateTime.now().add(const Duration(minutes: 15)),
  );

  setUp(() {
    repository = MockAuthRepository();
  });

  AuthFlowBloc buildBloc() => AuthFlowBloc(repository);

  group('AuthFlowEmailEntered', () {
    blocTest<AuthFlowBloc, AuthFlowState>(
      'mémorise l’email et passe à l’étape PIN sans appel réseau',
      build: buildBloc,
      act: (AuthFlowBloc bloc) => bloc
          .add(const AuthFlowEmailEntered('aicha.hounkpe@kora-express.com')),
      expect: () => <AuthFlowState>[
        const AuthFlowState(
          email: 'aicha.hounkpe@kora-express.com',
          step: AuthFlowStep.pin,
        ),
      ],
      verify: (_) {
        verifyNever(() => repository.requestOtp(
            email: any(named: 'email'), purpose: any(named: 'purpose')));
      },
    );
  });

  group('AuthFlowLoginSubmitted', () {
    blocTest<AuthFlowBloc, AuthFlowState>(
      'connecte directement quand l’appareil est déjà connu',
      build: () {
        when(() => repository.login(
              email: any(named: 'email'),
              pin: any(named: 'pin'),
              verification: any(named: 'verification'),
            )).thenAnswer((_) async => Result<LoginOutcome>.success(
              LoginSucceeded(session),
            ));
        return buildBloc();
      },
      act: (AuthFlowBloc bloc) => bloc.add(const AuthFlowLoginSubmitted(
          email: 'aicha.hounkpe@kora-express.com', pin: '1234')),
      expect: () => <AuthFlowState>[
        const AuthFlowState(
          isSubmitting: true,
          email: 'aicha.hounkpe@kora-express.com',
          pendingPin: '1234',
        ),
        AuthFlowState(
          step: AuthFlowStep.done,
          email: 'aicha.hounkpe@kora-express.com',
          pendingPin: '1234',
          session: session,
        ),
      ],
    );

    blocTest<AuthFlowBloc, AuthFlowState>(
      'passe à l’étape code quand l’appareil est inconnu (202)',
      build: () {
        when(() => repository.login(
                  email: any(named: 'email'),
                  pin: any(named: 'pin'),
                  verification: any(named: 'verification'),
                ))
            .thenAnswer((_) async =>
                Result<LoginOutcome>.success(LoginNeedsOtp(challengeRef)));
        return buildBloc();
      },
      act: (AuthFlowBloc bloc) => bloc.add(const AuthFlowLoginSubmitted(
          email: 'aicha.hounkpe@kora-express.com', pin: '1234')),
      expect: () => <dynamic>[
        const AuthFlowState(
          isSubmitting: true,
          email: 'aicha.hounkpe@kora-express.com',
          pendingPin: '1234',
        ),
        isA<AuthFlowState>()
            .having((s) => s.step, 'step', AuthFlowStep.code)
            .having((s) => s.purpose, 'purpose', OtpPurpose.loginNewDevice)
            .having((s) => s.challenge?.uuid, 'challenge.uuid', 'chal-1'),
      ],
    );

    blocTest<AuthFlowBloc, AuthFlowState>(
      'signale un compte verrouillé sans changer d’étape',
      build: () {
        when(() => repository.login(
                  email: any(named: 'email'),
                  pin: any(named: 'pin'),
                  verification: any(named: 'verification'),
                ))
            .thenAnswer((_) async => const Result<LoginOutcome>.failure(
                AccountLockedFailure('Compte verrouillé.')));
        return buildBloc();
      },
      act: (AuthFlowBloc bloc) => bloc.add(const AuthFlowLoginSubmitted(
          email: 'aicha.hounkpe@kora-express.com', pin: '0000')),
      expect: () => <dynamic>[
        const AuthFlowState(
          isSubmitting: true,
          email: 'aicha.hounkpe@kora-express.com',
          pendingPin: '0000',
        ),
        isA<AuthFlowState>()
            .having((s) => s.step, 'step', AuthFlowStep.email)
            .having((s) => s.isAccountLocked, 'isAccountLocked', isTrue)
            .having(
                (s) => s.errorMessage, 'errorMessage', 'Compte verrouillé.'),
      ],
    );
  });

  group('parcours OTP — appareil inconnu', () {
    blocTest<AuthFlowBloc, AuthFlowState>(
      'vérifie le code puis rejoue login en ligne (pas de nouvel add) et termine connecté',
      build: () {
        when(() => repository.verifyOtp(
              challengeUuid: any(named: 'challengeUuid'),
              purpose: any(named: 'purpose'),
              code: any(named: 'code'),
            )).thenAnswer((_) async => const Result<OtpVerification>.success(
              OtpVerification(
                challengeUuid: 'chal-1',
                purpose: OtpPurpose.loginNewDevice,
                verificationToken: 'verif-token',
              ),
            ));
        when(() => repository.login(
                  email: any(named: 'email'),
                  pin: any(named: 'pin'),
                  verification: any(named: 'verification'),
                ))
            .thenAnswer((_) async =>
                Result<LoginOutcome>.success(LoginSucceeded(session)));
        return buildBloc();
      },
      seed: () => AuthFlowState(
        step: AuthFlowStep.code,
        purpose: OtpPurpose.loginNewDevice,
        email: 'aicha.hounkpe@kora-express.com',
        pendingPin: '1234',
        challenge: OtpChallenge(
          uuid: 'chal-1',
          purpose: OtpPurpose.loginNewDevice,
          email: 'aicha.hounkpe@kora-express.com',
          expiresAt: DateTime.now().add(const Duration(minutes: 15)),
        ),
      ),
      act: (AuthFlowBloc bloc) =>
          bloc.add(const AuthFlowCodeSubmitted('654321')),
      // Comportement volontaire (voir commentaire "Bug corrigé" dans
      // AuthFlowBloc._onCodeSubmitted) : entre la vérification du code et
      // le rejeu de login, le bloc NE repasse PAS par isSubmitting:false —
      // sinon la page capte cet état intermédiaire comme "fin de
      // soumission" avant que la connexion soit réellement terminée.
      // 3 émissions seulement : Bloc ignore par ailleurs l'émission
      // isSubmitting:true du rejeu, identique à l'état déjà courant.
      expect: () => <dynamic>[
        // 1. isSubmitting pendant verifyOtp
        isA<AuthFlowState>()
            .having((s) => s.isSubmitting, 'isSubmitting', isTrue)
            .having((s) => s.verification, 'verification', isNull),
        // 2. verification obtenue, toujours isSubmitting=true (le rejeu
        //    de login s'enchaîne sans passage intermédiaire à false)
        isA<AuthFlowState>()
            .having((s) => s.isSubmitting, 'isSubmitting', isTrue)
            .having((s) => s.verification?.verificationToken, 'token',
                'verif-token'),
        // 3. terminé, connecté
        isA<AuthFlowState>()
            .having((s) => s.step, 'step', AuthFlowStep.done)
            .having((s) => s.session, 'session', session),
      ],
      verify: (_) {
        // La preuve que le rejeu se fait "en ligne" et non via un nouvel
        // événement : un seul appel à verifyOtp, un seul à login.
        verify(() => repository.verifyOtp(
              challengeUuid: any(named: 'challengeUuid'),
              purpose: any(named: 'purpose'),
              code: any(named: 'code'),
            )).called(1);
        verify(() => repository.login(
              email: any(named: 'email'),
              pin: any(named: 'pin'),
              verification: any(named: 'verification'),
            )).called(1);
      },
    );
  });

  group('parcours PIN oublié', () {
    blocTest<AuthFlowBloc, AuthFlowState>(
      'demande un OTP pin_reset puis passe à l’étape code',
      build: () {
        when(() => repository.requestOtp(
              email: any(named: 'email'),
              purpose: OtpPurpose.pinReset,
            )).thenAnswer((_) async => Result<OtpChallenge>.success(
              OtpChallenge(
                uuid: 'chal-2',
                purpose: OtpPurpose.pinReset,
                email: 'aicha.hounkpe@kora-express.com',
                expiresAt: DateTime.now().add(const Duration(minutes: 15)),
              ),
            ));
        return buildBloc();
      },
      act: (AuthFlowBloc bloc) => bloc.add(
          const AuthFlowPinResetRequested('aicha.hounkpe@kora-express.com')),
      expect: () => <dynamic>[
        isA<AuthFlowState>()
            .having((s) => s.isSubmitting, 'isSubmitting', isTrue),
        isA<AuthFlowState>()
            .having((s) => s.step, 'step', AuthFlowStep.code)
            .having((s) => s.purpose, 'purpose', OtpPurpose.pinReset),
      ],
    );

    blocTest<AuthFlowBloc, AuthFlowState>(
      'code vérifié pour pin_reset passe à l’étape newPin (pas de rejeu de login)',
      build: () {
        when(() => repository.verifyOtp(
              challengeUuid: any(named: 'challengeUuid'),
              purpose: any(named: 'purpose'),
              code: any(named: 'code'),
            )).thenAnswer((_) async => const Result<OtpVerification>.success(
              OtpVerification(
                challengeUuid: 'chal-2',
                purpose: OtpPurpose.pinReset,
                verificationToken: 'verif-token-2',
              ),
            ));
        return buildBloc();
      },
      seed: () => AuthFlowState(
        step: AuthFlowStep.code,
        purpose: OtpPurpose.pinReset,
        email: 'aicha.hounkpe@kora-express.com',
        challenge: OtpChallenge(
          uuid: 'chal-2',
          purpose: OtpPurpose.pinReset,
          email: 'aicha.hounkpe@kora-express.com',
          expiresAt: DateTime.now().add(const Duration(minutes: 15)),
        ),
      ),
      act: (AuthFlowBloc bloc) =>
          bloc.add(const AuthFlowCodeSubmitted('111111')),
      // Pour le motif pin_reset, le bloc émet verification + step:newPin
      // + isSubmitting:false en un seul état combiné (une seule ligne
      // `emit` dans le case OtpPurpose.pinReset) : 2 émissions au total.
      expect: () => <dynamic>[
        // 1. isSubmitting pendant verifyOtp
        isA<AuthFlowState>()
            .having((s) => s.isSubmitting, 'isSubmitting', isTrue),
        // 2. verification obtenue ET passage à newPin en même temps
        isA<AuthFlowState>()
            .having((s) => s.step, 'step', AuthFlowStep.newPin)
            .having((s) => s.isSubmitting, 'isSubmitting', isFalse)
            .having((s) => s.verification?.verificationToken, 'token',
                'verif-token-2'),
      ],
      verify: (_) {
        verifyNever(() => repository.login(
              email: any(named: 'email'),
              pin: any(named: 'pin'),
              verification: any(named: 'verification'),
            ));
      },
    );

    blocTest<AuthFlowBloc, AuthFlowState>(
      'nouveau PIN accepté renvoie au tout début du parcours',
      build: () {
        when(() => repository.resetPin(
              verification: any(named: 'verification'),
              pin: any(named: 'pin'),
            )).thenAnswer((_) async => const Result<void>.success(null));
        return buildBloc();
      },
      seed: () => const AuthFlowState(
        step: AuthFlowStep.newPin,
        purpose: OtpPurpose.pinReset,
        email: 'aicha.hounkpe@kora-express.com',
        verification: OtpVerification(
          challengeUuid: 'chal-2',
          purpose: OtpPurpose.pinReset,
          verificationToken: 'verif-token-2',
        ),
      ),
      act: (AuthFlowBloc bloc) =>
          bloc.add(const AuthFlowNewPinSubmitted('4321')),
      expect: () => <dynamic>[
        isA<AuthFlowState>()
            .having((s) => s.isSubmitting, 'isSubmitting', isTrue),
        const AuthFlowState(),
      ],
    );
  });

  blocTest<AuthFlowBloc, AuthFlowState>(
    'AuthFlowReset remet le parcours à zéro',
    build: buildBloc,
    seed: () => const AuthFlowState(
        step: AuthFlowStep.pin, email: 'aicha.hounkpe@kora-express.com'),
    act: (AuthFlowBloc bloc) => bloc.add(const AuthFlowReset()),
    expect: () => <AuthFlowState>[const AuthFlowState()],
  );
}
