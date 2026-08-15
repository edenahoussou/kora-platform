import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/device_info.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/user_entity.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_current_device_info.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_saved_email.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/send_otp.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/verify_otp.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/verify_pin.dart';
import 'package:kora_ops_mobile/features/auth/presentation/cubit/login_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockGetSavedEmail extends Mock implements GetSavedEmail {}

class MockSendOtp extends Mock implements SendOtp {}

class MockVerifyOtp extends Mock implements VerifyOtp {}

class MockVerifyPin extends Mock implements VerifyPin {}

class MockGetCurrentDeviceInfo extends Mock implements GetCurrentDeviceInfo {}

void main() {
  late MockGetSavedEmail getSavedEmail;
  late MockSendOtp sendOtp;
  late MockVerifyOtp verifyOtp;
  late MockVerifyPin verifyPin;
  late MockGetCurrentDeviceInfo getCurrentDeviceInfo;

  const UserEntity user = UserEntity(
    id: 'agent-001',
    fullName: 'aicha.hounkpe',
    role: 'Convoyeur',
    email: 'aicha.hounkpe@example.com',
  );
  const DeviceInfo device = DeviceInfo(
    deviceModel: 'Tecno CAMON',
    city: 'Cotonou',
    attemptLabel: 'À l\'instant',
  );

  setUp(() {
    getSavedEmail = MockGetSavedEmail();
    sendOtp = MockSendOtp();
    verifyOtp = MockVerifyOtp();
    verifyPin = MockVerifyPin();
    getCurrentDeviceInfo = MockGetCurrentDeviceInfo();
  });

  LoginCubit buildCubit() => LoginCubit(
        getSavedEmail: getSavedEmail,
        sendOtp: sendOtp,
        verifyOtp: verifyOtp,
        verifyPin: verifyPin,
        getCurrentDeviceInfo: getCurrentDeviceInfo,
      );

  group('submitEmail', () {
    blocTest<LoginCubit, LoginState>(
      'émet submitting puis success (accountCreation) quand sendOtp réussit',
      build: () {
        when(() => sendOtp(email: any(named: 'email')))
            .thenAnswer((_) async => const Result<void>.success(null));
        return buildCubit();
      },
      act: (LoginCubit cubit) => cubit.submitEmail('aicha.hounkpe@example.com'),
      expect: () => <dynamic>[
        predicate<LoginState>((s) => s.status == LoginStatus.submitting),
        predicate<LoginState>(
          (s) =>
              s.status == LoginStatus.success &&
              s.otpPurpose == OtpPurpose.accountCreation,
        ),
      ],
    );

    test('retourne true en cas de succès', () async {
      when(() => sendOtp(email: any(named: 'email')))
          .thenAnswer((_) async => const Result<void>.success(null));
      final cubit = buildCubit();
      final result = await cubit.submitEmail('aicha.hounkpe@example.com');
      expect(result, isTrue);
    });

    test('retourne false et expose le message d\'erreur en cas d\'échec',
        () async {
      when(() => sendOtp(email: any(named: 'email'))).thenAnswer((_) async =>
          const Result<void>.failure(NetworkFailure('Pas de réseau.')));
      final cubit = buildCubit();
      final result = await cubit.submitEmail('aicha.hounkpe@example.com');
      expect(result, isFalse);
      expect(cubit.state.errorMessage, 'Pas de réseau.');
    });
  });

  group('submitOtp', () {
    test('retourne true et stocke l\'utilisateur quand le code est correct',
        () async {
      when(() => sendOtp(email: any(named: 'email')))
          .thenAnswer((_) async => const Result<void>.success(null));
      final cubit = buildCubit();
      await cubit.submitEmail(user.email);

      when(() =>
              verifyOtp(email: any(named: 'email'), code: any(named: 'code')))
          .thenAnswer((_) async => const Result<UserEntity>.success(user));

      final result = await cubit.submitOtp('583187');

      expect(result, isTrue);
      expect(cubit.state.user, user);
    });

    test('retourne false quand le code est incorrect', () async {
      when(() => sendOtp(email: any(named: 'email')))
          .thenAnswer((_) async => const Result<void>.success(null));
      final cubit = buildCubit();
      await cubit.submitEmail(user.email);

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

  group('submitPin', () {
    blocTest<LoginCubit, LoginState>(
      'charge les infos de l\'appareil et émet success quand le PIN est correct',
      build: () {
        when(() => verifyPin(pin: any(named: 'pin')))
            .thenAnswer((_) async => const Result<UserEntity>.success(user));
        when(() => getCurrentDeviceInfo())
            .thenAnswer((_) async => const Result<DeviceInfo>.success(device));
        return buildCubit();
      },
      act: (LoginCubit cubit) => cubit.submitPin('1234'),
      expect: () => <dynamic>[
        predicate<LoginState>((s) => s.status == LoginStatus.submitting),
        predicate<LoginState>(
          (s) =>
              s.status == LoginStatus.success &&
              s.user == user &&
              s.deviceInfo == device,
        ),
      ],
    );

    blocTest<LoginCubit, LoginState>(
      'émet failure quand le PIN est incorrect',
      build: () {
        when(() => verifyPin(pin: any(named: 'pin'))).thenAnswer(
          (_) async => const Result<UserEntity>.failure(
              ValidationFailure('PIN incorrect.')),
        );
        return buildCubit();
      },
      act: (LoginCubit cubit) => cubit.submitPin('0000'),
      expect: () => <dynamic>[
        predicate<LoginState>((s) => s.status == LoginStatus.submitting),
        predicate<LoginState>(
          (s) =>
              s.status == LoginStatus.failure &&
              s.errorMessage == 'PIN incorrect.',
        ),
      ],
    );
  });

  group('requestDeviceConfirmationOtp', () {
    test('bascule otpPurpose vers deviceConfirmation en cas de succès',
        () async {
      when(() => sendOtp(email: any(named: 'email')))
          .thenAnswer((_) async => const Result<void>.success(null));
      final cubit = buildCubit();
      await cubit.submitEmail(user.email);

      final result = await cubit.requestDeviceConfirmationOtp();

      expect(result, isTrue);
      expect(cubit.state.otpPurpose, OtpPurpose.deviceConfirmation);
    });
  });

  group('reset', () {
    test('revient à un état initial vide', () async {
      when(() => sendOtp(email: any(named: 'email')))
          .thenAnswer((_) async => const Result<void>.success(null));
      final cubit = buildCubit();
      await cubit.submitEmail(user.email);

      cubit.reset();

      expect(cubit.state, const LoginState());
    });
  });
}
