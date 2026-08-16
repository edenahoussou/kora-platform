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
    fullName: 'Aïcha Hounkpé',
    role: 'Convoyeur',
    email: 'aicha.hounkpe@kora-express.com',
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

  group('loadSavedEmail', () {
    test('stocke l\'email dans le state et le retourne', () async {
      when(() => getSavedEmail()).thenAnswer((_) async =>
          const Result<String?>.success('aicha.hounkpe@kora-express.com'));
      final cubit = buildCubit();

      final result = await cubit.loadSavedEmail();

      expect(result, 'aicha.hounkpe@kora-express.com');
      expect(cubit.state.email, 'aicha.hounkpe@kora-express.com');
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

    test('retourne un booléen reflétant le succès/échec', () async {
      when(() => verifyPin(pin: any(named: 'pin')))
          .thenAnswer((_) async => const Result<UserEntity>.success(user));
      when(() => getCurrentDeviceInfo())
          .thenAnswer((_) async => const Result<DeviceInfo>.success(device));
      final cubit = buildCubit();

      final result = await cubit.submitPin('1234');

      expect(result, isTrue);
    });
  });

  group('requestDeviceConfirmationOtp', () {
    test('retourne false sans email connu', () async {
      final cubit = buildCubit();
      final result = await cubit.requestDeviceConfirmationOtp();
      expect(result, isFalse);
    });

    test(
        'envoie un OTP quand un email est déjà présent dans le state (post-PIN)',
        () async {
      when(() => verifyPin(pin: any(named: 'pin')))
          .thenAnswer((_) async => const Result<UserEntity>.success(user));
      when(() => getCurrentDeviceInfo())
          .thenAnswer((_) async => const Result<DeviceInfo>.success(device));
      when(() => sendOtp(email: any(named: 'email')))
          .thenAnswer((_) async => const Result<void>.success(null));

      final cubit = buildCubit();
      await cubit.loadSavedEmail().catchError((_) => null);
      // Simule le passage par l'écran PIN, qui alimente state.user (email
      // du provisionné, cohérent avec le flux réel PIN → nouvel appareil).
      when(() => getSavedEmail()).thenAnswer((_) async =>
          const Result<String?>.success('aicha.hounkpe@kora-express.com'));
      await cubit.loadSavedEmail();

      final bool result = await cubit.requestDeviceConfirmationOtp();

      expect(result, isTrue);
      verify(() => sendOtp(email: 'aicha.hounkpe@kora-express.com')).called(1);
    });
  });

  group('submitOtp', () {
    test('retourne true et stocke l\'utilisateur quand le code est correct',
        () async {
      when(() => getSavedEmail())
          .thenAnswer((_) async => Result<String?>.success(user.email));
      final cubit = buildCubit();
      await cubit.loadSavedEmail();

      when(() =>
              verifyOtp(email: any(named: 'email'), code: any(named: 'code')))
          .thenAnswer((_) async => const Result<UserEntity>.success(user));

      final result = await cubit.submitOtp('583187');

      expect(result, isTrue);
      expect(cubit.state.user, user);
    });

    test('retourne false quand le code est incorrect', () async {
      when(() => getSavedEmail())
          .thenAnswer((_) async => Result<String?>.success(user.email));
      final cubit = buildCubit();
      await cubit.loadSavedEmail();

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

  group('reset', () {
    test('revient à un état initial vide', () async {
      when(() => getSavedEmail())
          .thenAnswer((_) async => Result<String?>.success(user.email));
      final cubit = buildCubit();
      await cubit.loadSavedEmail();

      cubit.reset();

      expect(cubit.state, const LoginState());
    });
  });
}
