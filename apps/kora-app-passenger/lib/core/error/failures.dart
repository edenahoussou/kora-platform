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
  const ValidationFailure(super.message, {this.field});

  /// Le champ refusé par le serveur (`referral_code`), quand sa réponse
  /// n'en nomme qu'un.
  ///
  /// Un 422 qui ne porte que sur un champ se corrige dans ce champ :
  /// l'écran y pose le message, au lieu de l'afficher au milieu du
  /// formulaire où rien n'indique quoi changer.
  final String? field;

  @override
  List<Object?> get props => [message, field];
}

/// Erreur d'authentification (token expiré, identifiants invalides).
class AuthFailure extends Failure {
  const AuthFailure([
    super.message = 'Session expirée, merci de te reconnecter.',
  ]);
}

/// Le compte est verrouillé après trop d'erreurs de PIN (423).
///
/// Distinct d'un [AuthFailure] parce que la suite n'est pas la même : ce
/// n'est pas un identifiant à corriger, c'est un déverrouillage à
/// demander par code OTP. L'écran doit pouvoir le proposer sans lire le
/// message d'erreur pour le deviner.
class AccountLockedFailure extends Failure {
  const AccountLockedFailure([
    super.message = 'Compte temporairement verrouillé après trop d’essais.',
  ]);
}

/// L'identifiant donné à l'inscription porte déjà un compte avec PIN
/// (409 `account_exists`).
///
/// Distinct d'un [AuthFailure] parce que la suite n'est pas la même :
/// il n'y a rien à corriger dans le formulaire, c'est une connexion
/// qu'il faut. L'écran doit pouvoir y mener sans lire le message pour le
/// deviner.
class AccountExistsFailure extends Failure {
  const AccountExistsFailure([
    super.message = 'Un compte existe déjà pour cet identifiant.',
  ]);
}

/// Un siège demandé vient d'être pris (409 sur `POST bus/holds`).
///
/// Distinct d'un [ValidationFailure] parce que l'écran a de quoi agir : le
/// plan connaît le siège fautif, il peut le repeindre en occupé et ne
/// demander qu'un remplaçant, au lieu de renvoyer le voyageur au début
/// avec un message générique.
class SeatUnavailableFailure extends Failure {
  const SeatUnavailableFailure(super.message, {this.seatId, this.seatLabel});

  /// L'identifiant du siège refusé, tel que le renvoie le serveur.
  final String? seatId;

  /// Son numéro (`4A`), pour le nommer dans le message.
  final String? seatLabel;

  @override
  List<Object?> get props => [message, seatId, seatLabel];
}

/// Filet de sécurité pour toute erreur non prévue explicitement.
class UnexpectedFailure extends Failure {
  const UnexpectedFailure([
    super.message = 'Une erreur inattendue est survenue.',
  ]);
}
