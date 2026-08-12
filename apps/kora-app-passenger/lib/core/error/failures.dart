import 'package:equatable/equatable.dart';

/// Une [Failure] représente un échec **côté métier** (domain), après
/// traduction d'une erreur technique (exception Dio, erreur de cache...).
///
/// Pourquoi ne pas juste laisser remonter les exceptions Dio jusqu'au
/// Bloc ? Parce que le Bloc ne doit jamais connaître les détails
/// techniques (`DioException`, code HTTP...). Il ne connaît que des
/// [Failure], et sait afficher [message] à l'utilisateur.
///
/// Volontairement écrite à la main (pas de `freezed` ici) : c'est une
/// classe critique utilisée absolument partout dans l'app, on ne veut
/// pas qu'elle dépende d'un fichier généré par `build_runner`. Ça garantit
/// que le projet compile même avant d'avoir lancé la génération de code.
sealed class Failure extends Equatable {
  const Failure(this.message);

  final String message;

  @override
  List<Object?> get props => [message];
}

/// Le serveur a répondu avec une erreur (400, 401, 404, 500...).
class ServerFailure extends Failure {
  const ServerFailure([super.message = 'Une erreur serveur est survenue.']);
}

/// Pas de connexion internet, ou requête expirée (timeout).
class NetworkFailure extends Failure {
  const NetworkFailure([
    super.message = 'Vérifie ta connexion internet et réessaie.',
  ]);
}

/// Échec de lecture/écriture dans le stockage local (cache, préférences).
class CacheFailure extends Failure {
  const CacheFailure([super.message = 'Erreur de stockage local.']);
}

/// Les données saisies par l'utilisateur ne sont pas valides
/// (formulaire, ex: email mal formé).
class ValidationFailure extends Failure {
  const ValidationFailure(super.message);
}

/// Erreur d'authentification (token expiré, identifiants invalides).
class AuthFailure extends Failure {
  const AuthFailure([
    super.message = 'Session expirée, merci de te reconnecter.',
  ]);
}

/// Filet de sécurité pour toute erreur non prévue explicitement.
class UnexpectedFailure extends Failure {
  const UnexpectedFailure([
    super.message = 'Une erreur inattendue est survenue.',
  ]);
}
