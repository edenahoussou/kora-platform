import '../../../../core/result/result.dart';
import '../entities/user_entity.dart';
import '../repositories/auth_repository.dart';

class VerifyOtp {
  const VerifyOtp(this._repository);

  final AuthRepository _repository;

  Future<Result<UserEntity>> call(
          {required String email, required String code}) =>
      _repository.verifyOtp(email: email, code: code);
}
