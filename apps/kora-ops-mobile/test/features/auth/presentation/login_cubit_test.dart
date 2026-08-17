import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/user_entity.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/forgot_password.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_saved_email.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/login_with_password.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/send_otp.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/verify_otp.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/verify_pin.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/login_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockGetSavedEmail extends Mock implements GetSavedEmail {}

class MockLoginWithPassword extends Mock implements LoginWithPassword {}

class MockForgotPassword extends Mock implements ForgotPassword {}

class MockSendOtp extends Mock implements SendOtp {}

class MockVerifyOtp extends Mock implements VerifyOtp {}

class MockVerifyPin extends Mock implements VerifyPin {}

void main() {
  late MockGetSavedEmail getSavedEmail;
  late MockLoginWithPassword loginWithPassword;
  late MockForgotPassword forgotPassword;
  late MockSendOtp sendOtp;
  late MockVerifyOtp verifyOtp;
  late MockVerifyPin verifyPin;

  const UserEntity user = UserEntity(
    id: 'agent-001',
    fullName: 'Aïcha Hounkpé',
    role: 'Convoyeur',
    email: 'aicha.hounkpe@kora-express.com',
  );

  setUp(() {
    getSavedEmail = MockGetSavedEmail();
    loginWithPassword = MockLoginWithPassword();
    forgotPassword = MockForgotPassword();
    sendOtp = MockSendOtp();
    verifyOtp = MockVerifyOtp();
    verifyPin = MockVerifyPin();
  });

  LoginCubit buildCubit() => LoginCubit(
        getSavedEmail: getSavedEmail,
        loginWithPassword: loginWithPassword,
        forgotPassword: forgotPassword,
        sendOtp: sendOtp,
        verifyOtp: verifyOtp,
        verifyPin: verifyPin,
      );

  group('submitEmailPassword (première connexion)', () {
    blocTest<LoginCubit, LoginState>(
      'émet success + pendingOtpActivation=true quand les identifiants sont valides',
      build: () {
        when(() => loginWithPassword(
                email: any(named: 'email'), password: any(named: 'password')))
            .thenAnswer((_) async => const Result<void>.success(null));
        return buildCubit();
      },
      act: (LoginCubit cubit) =>
          cubit.submitEmailPassword(user.email, 'Kora@2026'),
      expect: () => <dynamic>[
        predicate<LoginState>((s) => s.status == LoginStatus.submitting),
        predicate<LoginState>(
          (s) =>
              s.status == LoginStatus.success &&
              s.email == user.email &&
              s.pendingOtpActivation,
        ),
      ],
    );

    test('émet failure quand les identifiants sont invalides', () async {
      when(() => loginWithPassword(
              email: any(named: 'email'), password: any(named: 'password')))
          .thenAnswer((_) async => const Result<void>.failure(
              ValidationFailure('Identifiants invalides.')));
      final cubit = buildCubit();

      final result = await cubit.submitEmailPassword(user.email, 'wrong');

      expect(result, isFalse);
      expect(cubit.state.pendingOtpActivation, isFalse);
    });
  });

  group('loadSavedEmail (reconnexion)', () {
    test('renseigne email sans activer pendingOtpActivation', () async {
      when(() => getSavedEmail())
          .thenAnswer((_) async => Result<String?>.success(user.email));
      final cubit = buildCubit();

      final email = await cubit.loadSavedEmail();

      expect(email, user.email);
      expect(cubit.state.pendingOtpActivation, isFalse);
    });
  });

  group('submitPin', () {
    test('retourne true et stocke l\'utilisateur quand le PIN est correct',
        () async {
      when(() => getSavedEmail())
          .thenAnswer((_) async => Result<String?>.success(user.email));
      final cubit = buildCubit();
      await cubit.loadSavedEmail();

      when(() => verifyPin(email: any(named: 'email'), pin: any(named: 'pin')))
          .thenAnswer((_) async => const Result<UserEntity>.success(user));

      final result = await cubit.submitPin('1234');

      expect(result, isTrue);
      expect(cubit.state.user, user);
    });

    test('retourne false sans email connu', () async {
      final cubit = buildCubit();
      final result = await cubit.submitPin('1234');
      expect(result, isFalse);
    });
  });

  group('requestActivationOtp', () {
    test('envoie un OTP pour l\'email courant', () async {
      when(() => loginWithPassword(
              email: any(named: 'email'), password: any(named: 'password')))
          .thenAnswer((_) async => const Result<void>.success(null));
      when(() => sendOtp(email: any(named: 'email')))
          .thenAnswer((_) async => const Result<void>.success(null));
      final cubit = buildCubit();
      await cubit.submitEmailPassword(user.email, 'Kora@2026');

      final result = await cubit.requestActivationOtp();

      expect(result, isTrue);
      verify(() => sendOtp(email: user.email)).called(1);
    });
  });

  group('submitOtp', () {
    test(
        'retourne true, stocke l\'utilisateur et désactive pendingOtpActivation',
        () async {
      when(() => loginWithPassword(
              email: any(named: 'email'), password: any(named: 'password')))
          .thenAnswer((_) async => const Result<void>.success(null));
      final cubit = buildCubit();
      await cubit.submitEmailPassword(user.email, 'Kora@2026');

      when(() =>
              verifyOtp(email: any(named: 'email'), code: any(named: 'code')))
          .thenAnswer((_) async => const Result<UserEntity>.success(user));

      final result = await cubit.submitOtp('583187');

      expect(result, isTrue);
      expect(cubit.state.user, user);
      expect(cubit.state.pendingOtpActivation, isFalse);
    });

    test('retourne false quand le code est incorrect', () async {
      when(() => loginWithPassword(
              email: any(named: 'email'), password: any(named: 'password')))
          .thenAnswer((_) async => const Result<void>.success(null));
      final cubit = buildCubit();
      await cubit.submitEmailPassword(user.email, 'Kora@2026');

      when(() =>
              verifyOtp(email: any(named: 'email'), code: any(named: 'code')))
          .thenAnswer(
        (_) async => const Result<UserEntity>.failure(
            ValidationFailure('Code incorrect.')),
      );

      final result = await cubit.submitOtp('000000');

      expect(result, isFalse);
    });
  });

  group('submitForgotPassword', () {
    test('retourne true quand l\'envoi réussit', () async {
      when(() => forgotPassword(email: any(named: 'email')))
          .thenAnswer((_) async => const Result<void>.success(null));
      final cubit = buildCubit();

      final result = await cubit.submitForgotPassword(user.email);

      expect(result, isTrue);
    });
  });

  group('reset', () {
    test('revient à un état initial vide', () async {
      when(() => loginWithPassword(
              email: any(named: 'email'), password: any(named: 'password')))
          .thenAnswer((_) async => const Result<void>.success(null));
      final cubit = buildCubit();
      await cubit.submitEmailPassword(user.email, 'Kora@2026');

      cubit.reset();

      expect(cubit.state, const LoginState());
    });
  });
}
