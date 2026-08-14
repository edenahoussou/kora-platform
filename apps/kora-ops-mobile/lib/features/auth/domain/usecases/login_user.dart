import '../../../../core/result/result.dart';
import '../entities/user_entity.dart';
import '../repositories/auth_repository.dart';

/// UseCase à responsabilité unique : authentifier un agent terrain.
class LoginUser {
  const LoginUser(this._repository);

  final AuthRepository _repository;

  Future<Result<UserEntity>> call({required String username, required String password}) {
    return _repository.login(username: username, password: password);
  }
}
