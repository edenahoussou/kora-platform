import '../../../../core/result/result.dart';
import '../entities/user_entity.dart';

abstract interface class AuthRepository {
  Future<Result<UserEntity>> login({required String username, required String password});
  Future<Result<void>> logout();
  Future<Result<UserEntity?>> getCurrentUser();
}
