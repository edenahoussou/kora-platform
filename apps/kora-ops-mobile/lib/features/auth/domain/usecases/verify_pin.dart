import '../../../../core/result/result.dart';
import '../entities/user_entity.dart';
import '../repositories/auth_repository.dart';

class VerifyPin {
  const VerifyPin(this._repository);

  final AuthRepository _repository;

  Future<Result<UserEntity>> call(
          {required String email, required String pin}) =>
      _repository.verifyPin(email: email, pin: pin);
}
