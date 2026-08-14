import '../../../../core/errors/failures.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/user_entity.dart';
import '../../domain/repositories/auth_repository.dart';
import '../datasources/auth_local_datasource.dart';

class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl(this._localDataSource);

  final AuthLocalDataSource _localDataSource;

  @override
  Future<Result<UserEntity>> login({required String username, required String password}) async {
    try {
      final UserEntity user = await _localDataSource.login(username: username, password: password);
      return Result<UserEntity>.success(user);
    } on AppException catch (error) {
      return Result<UserEntity>.failure(_mapException(error));
    }
  }

  @override
  Future<Result<void>> logout() async {
    try {
      await _localDataSource.logout();
      return const Result<void>.success(null);
    } on AppException catch (error) {
      return Result<void>.failure(_mapException(error));
    }
  }

  @override
  Future<Result<UserEntity?>> getCurrentUser() async {
    try {
      final UserEntity? user = await _localDataSource.getCurrentUser();
      return Result<UserEntity?>.success(user);
    } on AppException catch (error) {
      return Result<UserEntity?>.failure(_mapException(error));
    }
  }

  Failure _mapException(AppException exception) => switch (exception) {
        NetworkException() => NetworkFailure(exception.message),
        UnauthorizedException() => AuthenticationFailure(exception.message),
        ServerException() => ServerFailure(exception.message),
        ApiException() => ValidationFailure(exception.message),
        CacheException() => ServerFailure(exception.message),
    // TODO: Handle this case.
    ScannerException() => throw UnimplementedError(),
    // TODO: Handle this case.
    PrinterException() => throw UnimplementedError(),
      };
}
