import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/device_info.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/user_entity.dart';
import 'package:kora_ops_mobile/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_current_device_info.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_saved_email.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/send_otp.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/verify_otp.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/verify_pin.dart';
import 'package:mocktail/mocktail.dart';

class MockAuthRepository extends Mock implements AuthRepository {}

void main() {
  late MockAuthRepository repository;

  setUp(() => repository = MockAuthRepository());

  const UserEntity user = UserEntity(
    id: '1',
    fullName: 'aicha.hounkpe',
    role: 'Convoyeur',
    email: 'aicha.hounkpe@example.com',
  );

  test('GetSavedEmail délègue au repository', () async {
    when(() => repository.getSavedEmail())
        .thenAnswer((_) async => const Result<String?>.success('aicha.hounkpe@example.com'));

    final result = await GetSavedEmail(repository)();

    result.when(
      onSuccess: (email) => expect(email, 'aicha.hounkpe@example.com'),
      onFailure: (_) => fail('ne devrait pas échouer'),
    );
  });

  test('SendOtp délègue au repository avec l\'email fourni', () async {
    when(() => repository.sendOtp(email: any(named: 'email')))
        .thenAnswer((_) async => const Result<void>.success(null));

    final result = await SendOtp(repository)(email: 'x@example.com');

    expect(result.isSuccess, isTrue);
    verify(() => repository.sendOtp(email: 'x@example.com')).called(1);
  });

  test('VerifyOtp retourne un échec de validation pour un code refusé', () async {
    when(() => repository.verifyOtp(email: any(named: 'email'), code: any(named: 'code')))
        .thenAnswer((_) async => const Result<UserEntity>.failure(ValidationFailure('Code invalide.')));

    final result = await VerifyOtp(repository)(email: user.email, code: '000000');

    expect(result.isFailure, isTrue);
  });

  test('VerifyPin retourne l\'utilisateur pour un PIN correct', () async {
    when(() => repository.verifyPin(pin: any(named: 'pin')))
        .thenAnswer((_) async => const Result<UserEntity>.success(user));

    final result = await VerifyPin(repository)(pin: '1234');

    result.when(onSuccess: (u) => expect(u, user), onFailure: (_) => fail('ne devrait pas échouer'));
  });

  test('GetCurrentDeviceInfo délègue au repository', () async {
    const DeviceInfo device = DeviceInfo(
      deviceModel: 'Tecno CAMON',
      city: 'Cotonou',
      attemptLabel: 'À l\'instant',
    );
    when(() => repository.getCurrentDeviceInfo())
        .thenAnswer((_) async => const Result<DeviceInfo>.success(device));

    final result = await GetCurrentDeviceInfo(repository)();

    result.when(onSuccess: (d) => expect(d, device), onFailure: (_) => fail('ne devrait pas échouer'));
  });
}
