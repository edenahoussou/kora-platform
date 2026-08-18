import '../../../../core/result/result.dart';
import '../repositories/auth_repository.dart';

/// Déclenche l'envoi d'un lien/code de réinitialisation de mot de passe.
class ForgotPassword {
  const ForgotPassword(this._repository);

  final AuthRepository _repository;

  Future<Result<void>> call({required String email}) =>
      _repository.forgotPassword(email: email);
}
