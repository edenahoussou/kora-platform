import 'package:dartz/dartz.dart';
import 'package:dio/dio.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show
        ApiException,
        ApiExceptionMapper,
        CancelledApiException,
        ForbiddenApiException,
        LockedApiException,
        NetworkApiException,
        NotFoundApiException,
        ServerApiException,
        TimeoutApiException,
        UnauthorizedApiException,
        UnknownApiException,
        ValidationApiException;

import 'exceptions.dart';
import 'failures.dart';

/// Traduction des erreurs du package réseau en échecs métier.
///
/// Deux dépôts s'appuient dessus — le tunnel de réservation et la
/// recherche de départs — et un troisième viendra. La règle vit donc ici,
/// à un seul endroit : la dupliquer laisserait deux écrans réagir
/// différemment à la même panne.
mixin ApiFailureMapper {
  /// Exécute [call] et traduit tout ce qui peut en sortir en [Failure].
  ///
  /// Le `catch` final est volontaire : une réponse dans un format
  /// inattendu ne doit pas remonter en exception non gérée jusqu'au Bloc,
  /// qui n'a aucun moyen de l'afficher.
  Future<Either<Failure, T>> attempt<T>(Future<T> Function() call) async {
    try {
      return Right(await call());
    } on DioException catch (error) {
      // On repasse par le mapper plutôt que de lire `error.apiException` :
      // le résultat est le même quand `ErrorInterceptor` a fait son
      // travail, mais un `Dio` monté sans lui (un test, un futur client)
      // ne retombe pas pour autant sur « erreur inattendue ».
      return Left(failureOf(ApiExceptionMapper.fromDioException(error)));
    } on ApiException catch (error) {
      return Left(failureOf(error));
    } on ServerException catch (error) {
      return Left(ServerFailure(error.message));
    } on NetworkException {
      return const Left(NetworkFailure());
    } catch (error) {
      return Left(UnexpectedFailure('$error'));
    }
  }

  /// Traduit une erreur du package réseau en échec métier.
  ///
  /// Un `switch` sur le type plutôt que sur le code HTTP : c'est déjà le
  /// travail d'`ApiExceptionMapper` de lire le statut, le refaire ici
  /// dupliquerait la règle à deux endroits.
  Failure failureOf(ApiException error) => switch (error) {
    NetworkApiException() ||
    TimeoutApiException() => NetworkFailure(error.message),
    UnauthorizedApiException() ||
    ForbiddenApiException() => AuthFailure(error.message),
    ValidationApiException() => ValidationFailure(error.message),
    LockedApiException() => AccountLockedFailure(error.message),
    // Pas de résultat n'est pas une erreur de recherche, mais un 404 sur
    // le catalogue veut dire que l'endpoint lui-même a bougé : c'est bien
    // une panne serveur du point de vue du voyageur.
    NotFoundApiException() ||
    ServerApiException() => ServerFailure(error.message),
    // La requête a été abandonnée (écran quitté avant la réponse) : rien
    // à reprocher au réseau, et rien à afficher non plus.
    CancelledApiException() => UnexpectedFailure(error.message),
    UnknownApiException() => UnexpectedFailure(error.message),
  };
}
