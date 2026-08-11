/// Exceptions **techniques**, levées dans la couche `data` (data sources).
///
/// Différence avec [Failure] (`core/error/failures.dart`) : une exception
/// est levée là où l'erreur se produit vraiment (ex: dans un data source
/// qui appelle Dio). Le repository l'attrape ensuite (`try/catch`) et la
/// traduit en [Failure] avant de la faire remonter au Bloc via un
/// `Either<Failure, T>` (dartz). Le Bloc ne voit donc jamais ces
/// exceptions directement.
///
/// Exemple dans un repository :
/// ```dart
/// @override
/// Future<Either<Failure, User>> login(...) async {
///   try {
///     final user = await remoteDataSource.login(...);
///     return Right(user);
///   } on ServerException catch (e) {
///     return Left(ServerFailure(e.message));
///   } on NetworkException {
///     return const Left(NetworkFailure());
///   }
/// }
/// ```
class ServerException implements Exception {
  const ServerException([this.message = 'Erreur serveur.']);
  final String message;
}

class NetworkException implements Exception {
  const NetworkException([this.message = 'Erreur réseau.']);
  final String message;
}

class CacheException implements Exception {
  const CacheException([this.message = 'Erreur de cache.']);
  final String message;
}

class AuthException implements Exception {
  const AuthException([this.message = "Erreur d'authentification."]);
  final String message;
}
