import '../../../../core/result/result.dart';
import '../repositories/auth_repository.dart';

class SendOtp {
  const SendOtp(this._repository);

  final AuthRepository _repository;

  Future<Result<void>> call({required String email}) =>
      _repository.sendOtp(email: email);
}
