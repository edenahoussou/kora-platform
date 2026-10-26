import 'package:equatable/equatable.dart';

/// Représente un échec métier exploitable par la couche Presentation.
///
/// Stratégie retenue (voir document.md § Gestion des erreurs) : un
/// [Result]<[Failure], T> maison plutôt que `dartz`'s `Either`, pour éviter
/// une dépendance supplémentaire au vocabulaire fonctionnel peu familier
/// aux futurs contributeurs, tout en gardant la même garantie — un
/// UseCase ne lève jamais d'exception vers Presentation, il retourne un
/// [Result].
sealed class Failure extends Equatable {
  const Failure(this.message);

  final String message;

  @override
  List<Object?> get props => <Object?>[message];
}

class ServerFailure extends Failure {
  const ServerFailure([super.message = 'Erreur serveur inattendue.']);
}

class NetworkFailure extends Failure {
  const NetworkFailure([super.message = 'Pas de connexion réseau.']);
}

class AuthenticationFailure extends Failure {
  const AuthenticationFailure([super.message = 'Authentification requise.']);
}

/// Compte temporairement verrouillé après trop d'erreurs de PIN (423).
/// Distinct d'[AuthenticationFailure] : l'écran ne doit pas proposer de
/// retenter le PIN, mais un déverrouillage par OTP.
class AccountLockedFailure extends Failure {
  const AccountLockedFailure(
      [super.message =
          'Compte temporairement verrouillé après trop d’essais.']);
}

/// Compte suspendu par un administrateur (403 métier) — distinct d'un 403
/// « pas les droits sur cette ressource » : ici, aucune action de
/// l'utilisateur ne rouvre le compte.
class AccountSuspendedFailure extends Failure {
  const AccountSuspendedFailure(
      [super.message = 'Compte suspendu. Contactez votre responsable.']);
}

class ValidationFailure extends Failure {
  const ValidationFailure([super.message = 'Données invalides.']);
}

class UnknownFailure extends Failure {
  const UnknownFailure([super.message = 'Une erreur inconnue est survenue.']);
}

/// Échec d'un adaptateur matériel P501 (scanner ou imprimante).
class HardwareFailure extends Failure {
  const HardwareFailure([super.message = 'Erreur matérielle (P501).']);
}

/// Le numéro de série saisi ne correspond à aucun terminal P501 enregistré
/// côté serveur (`terminal_unknown`, 404 — voir `TerminalOpsMobileController.
/// handshake`).
class TerminalUnknownFailure extends Failure {
  const TerminalUnknownFailure(
      [super.message =
          'Ce terminal n’est pas enregistré. Contactez votre agence.']);
}

/// Terminal enregistré mais inutilisable en l'état (`terminal_lost`,
/// `terminal_maintenance` ou `terminal_inactive`, 403). [code] permet à la
/// présentation d'adapter le message/l'action proposée sans reparser la
/// chaîne.
class TerminalNotUsableFailure extends Failure {
  const TerminalNotUsableFailure(this.code, super.message);

  final String code;

  @override
  List<Object?> get props => <Object?>[message, code];
}

/// Échec de la mise à jour hors store (MEZ-150) — téléchargement,
/// vérification d'intégrité (sha256) ou lancement de l'installeur. [code]
/// distingue les cas pour adapter le message et l'action proposée.
class AppUpdateFailure extends Failure {
  const AppUpdateFailure(this.code, super.message);

  /// Le fichier téléchargé ne correspond pas au sha256 annoncé : il est
  /// supprimé et jamais installé.
  static const String integrity = 'integrity';

  /// Le téléchargement a échoué (réseau, espace disque, serveur).
  static const String download = 'download';

  /// L'installeur Android n'a pas pu être lancé.
  static const String install = 'install';

  /// Plateforme sans installation d'APK possible (iOS, web…).
  static const String unsupported = 'unsupported';

  final String code;

  @override
  List<Object?> get props => <Object?>[message, code];
}
