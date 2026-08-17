import '../../../../core/result/result.dart';
import '../repositories/auth_repository.dart';

/// Vérifie email + mot de passe — première connexion sur un appareil (voir
/// document.md § Flux d'authentification).
class LoginWithPassword {
  const LoginWithPassword(this._repository);

  final AuthRepository _repository;

  Future<Result<void>> call(
          {required String email, required String password}) =>
      _repository.login(email: email, password: password);
}
