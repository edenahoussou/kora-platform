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
