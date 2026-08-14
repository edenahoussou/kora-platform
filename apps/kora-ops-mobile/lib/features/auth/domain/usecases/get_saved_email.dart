import '../../../../core/result/result.dart';
import '../repositories/auth_repository.dart';

class GetSavedEmail {
  const GetSavedEmail(this._repository);

  final AuthRepository _repository;

  Future<Result<String?>> call() => _repository.getSavedEmail();
}
