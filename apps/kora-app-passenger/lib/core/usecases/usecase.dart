import 'package:dartz/dartz.dart';
import '../error/failures.dart';

/// Couche DOMAIN : contrat de base que suit chaque "cas d'usage" (use
/// case) de l'application. Un use case = **une seule action métier**
/// (ex: `LoginUseCase`, `GetWalletBalanceUseCase`, `TrackParcelUseCase`).
///
/// Pourquoi un use case entre le Bloc et le Repository, et pas un appel
/// direct Bloc -> Repository ? Parce que la logique métier qui combine
/// plusieurs repositories (ex: "réserver un trajet ET débiter le wallet")
/// doit vivre quelque part qui n'est ni le Bloc (couche presentation) ni
/// le Repository (une seule source de données). Le use case est cet
/// endroit. Pour une action simple à un seul repository, un use case
/// reste une bonne pratique : ça garde le Bloc totalement indépendant du
/// détail de l'implémentation du repository.
///
/// [ResultType] est ce que retourne le use case en cas de succès.
/// [Params] est ce dont il a besoin en entrée (utilise [NoParams] s'il
/// n'a besoin de rien).
///
/// Exemple concret :
/// ```dart
/// class LoginUseCase implements UseCase<User, LoginParams> {
///   LoginUseCase(this._repository);
///   final AuthRepository _repository;
///
///   @override
///   Future<Either<Failure, User>> call(LoginParams params) {
///     return _repository.login(email: params.email, password: params.password);
///   }
/// }
///
/// class LoginParams extends Equatable {
///   const LoginParams({required this.email, required this.password});
///   final String email;
///   final String password;
///   @override
///   List<Object?> get props => [email, password];
/// }
/// ```
///
/// Et dans le Bloc :
/// ```dart
/// final result = await _loginUseCase(LoginParams(email: ..., password: ...));
/// result.fold(
///   (failure) => emit(state.copyWith(status: AuthStatus.failure, errorMessage: failure.message)),
///   (user) => emit(state.copyWith(status: AuthStatus.success, user: user)),
/// );
/// ```
abstract class UseCase<ResultType, Params> {
  Future<Either<Failure, ResultType>> call(Params params);
}

/// À utiliser comme [Params] pour un use case qui n'a besoin d'aucun
/// paramètre (ex: `GetWalletBalanceUseCase`).
class NoParams {
  const NoParams();
}
