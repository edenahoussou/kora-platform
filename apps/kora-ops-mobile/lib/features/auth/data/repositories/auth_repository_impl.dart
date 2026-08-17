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
  Future<Result<String?>> getSavedEmail() async {
    try {
      final String? email = await _localDataSource.getSavedEmail();
      return Result<String?>.success(email);
    } on AppException catch (error) {
      return Result<String?>.failure(_mapException(error));
    }
  }

  @override
  Future<Result<void>> login(
      {required String email, required String password}) async {
    try {
      await _localDataSource.login(email: email, password: password);
      return const Result<void>.success(null);
    } on AppException catch (error) {
      return Result<void>.failure(_mapException(error));
    }
  }

  @override
  Future<Result<void>> forgotPassword({required String email}) async {
    try {
      await _localDataSource.forgotPassword(email: email);
      return const Result<void>.success(null);
    } on AppException catch (error) {
      return Result<void>.failure(_mapException(error));
    }
  }

  @override
  Future<Result<void>> sendOtp({required String email}) async {
    try {
      await _localDataSource.sendOtp(email: email);
      return const Result<void>.success(null);
    } on AppException catch (error) {
      return Result<void>.failure(_mapException(error));
    }
  }

  @override
  Future<Result<UserEntity>> verifyOtp(
      {required String email, required String code}) async {
    try {
      final UserEntity user =
          await _localDataSource.verifyOtp(email: email, code: code);
      return Result<UserEntity>.success(user);
    } on AppException catch (error) {
      return Result<UserEntity>.failure(_mapException(error));
    }
  }

  @override
  Future<Result<UserEntity>> verifyPin(
      {required String email, required String pin}) async {
    try {
      final UserEntity user =
          await _localDataSource.verifyPin(email: email, pin: pin);
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
        ScannerException() => HardwareFailure(exception.message),
        PrinterException() => HardwareFailure(exception.message),
      };
}
