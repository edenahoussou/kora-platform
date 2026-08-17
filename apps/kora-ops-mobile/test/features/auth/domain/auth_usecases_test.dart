import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/auth/domain/entities/user_entity.dart';
import 'package:kora_ops_mobile/features/auth/domain/repositories/auth_repository.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/forgot_password.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/get_saved_email.dart';
import 'package:kora_ops_mobile/features/auth/domain/usecases/login_with_password.dart';
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
    fullName: 'Aïcha Hounkpé',
    role: 'Convoyeur',
    email: 'aicha.hounkpe@kora-express.com',
  );

  test('GetSavedEmail délègue au repository', () async {
    when(() => repository.getSavedEmail()).thenAnswer((_) async =>
        const Result<String?>.success('aicha.hounkpe@kora-express.com'));

    final result = await GetSavedEmail(repository)();

    result.when(
      onSuccess: (email) => expect(email, 'aicha.hounkpe@kora-express.com'),
      onFailure: (_) => fail('ne devrait pas échouer'),
    );
  });

  test('LoginWithPassword délègue au repository avec email et mot de passe',
      () async {
    when(() => repository.login(
            email: any(named: 'email'), password: any(named: 'password')))
        .thenAnswer((_) async => const Result<void>.success(null));

    final result = await LoginWithPassword(repository)(
        email: 'x@example.com', password: 'secret');

    expect(result.isSuccess, isTrue);
    verify(() => repository.login(email: 'x@example.com', password: 'secret'))
        .called(1);
  });

  test('ForgotPassword délègue au repository', () async {
    when(() => repository.forgotPassword(email: any(named: 'email')))
        .thenAnswer((_) async => const Result<void>.success(null));

    final result = await ForgotPassword(repository)(email: 'x@example.com');

    expect(result.isSuccess, isTrue);
  });

  test('SendOtp délègue au repository avec l\'email fourni', () async {
    when(() => repository.sendOtp(email: any(named: 'email')))
        .thenAnswer((_) async => const Result<void>.success(null));

    final result = await SendOtp(repository)(email: 'x@example.com');

    expect(result.isSuccess, isTrue);
    verify(() => repository.sendOtp(email: 'x@example.com')).called(1);
  });

  test('VerifyOtp retourne un échec de validation pour un code refusé',
      () async {
    when(() =>
        repository.verifyOtp(
            email: any(named: 'email'),
            code: any(named: 'code'))).thenAnswer((_) async =>
        const Result<UserEntity>.failure(ValidationFailure('Code invalide.')));

    final result =
        await VerifyOtp(repository)(email: user.email, code: '000000');

    expect(result.isFailure, isTrue);
  });

  test('VerifyPin retourne l\'utilisateur pour un email + PIN corrects',
      () async {
    when(() => repository.verifyPin(
            email: any(named: 'email'), pin: any(named: 'pin')))
        .thenAnswer((_) async => const Result<UserEntity>.success(user));

    final result = await VerifyPin(repository)(email: user.email, pin: '1234');

    result.when(
        onSuccess: (u) => expect(u, user),
        onFailure: (_) => fail('ne devrait pas échouer'));
  });
}
