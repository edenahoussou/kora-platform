import '../../../../core/result/result.dart';
import '../entities/auth_device.dart';
import '../entities/auth_session.dart';
import '../entities/auth_user.dart';
import '../entities/otp_challenge.dart';
import '../entities/otp_purpose.dart';

/// Couche DOMAIN : tout ce que l'app sait faire en matière de comptes.
///
/// [identifier] est un email OU un numéro de téléphone au format
/// international (E.164, ex. `+22961234567`) — le backend accepte les
/// deux indifféremment (voir `AuthIdentifierResolver` côté API, migration
/// du 25/09/2026 qui a renommé la colonne `email` en `identifier`).
///
/// Porté du contrat de kora-app-passenger, avec [Result] à la place
/// d'`Either<Failure, T>`.
///
/// Le `verification_token` circule d'une étape à l'autre — il est à usage
/// unique et ne vaut que pour le motif ([OtpPurpose]) demandé.
abstract class AuthRepository {
  /// Envoie un code à six chiffres à [identifier] pour [purpose].
  Future<Result<OtpChallenge>> requestOtp({
    required String identifier,
    required OtpPurpose purpose,
  });

  /// Vérifie le code saisi et rend le jeton à usage unique.
  Future<Result<OtpVerification>> verifyOtp({
    required String challengeUuid,
    required OtpPurpose purpose,
    required String code,
  });

  /// Connecte un compte existant (email ou téléphone + PIN).
  ///
  /// Rend un [LoginNeedsOtp] — pas une erreur — quand le serveur ne
  /// reconnaît pas l'appareil : il faut alors vérifier un code et rappeler
  /// cette méthode avec [verification].
  Future<Result<LoginOutcome>> login({
    required String identifier,
    required String pin,
    OtpVerification? verification,
  });

  /// Session en cours, ou `null` si personne n'est connecté. Ne contacte
  /// pas le réseau : relit le stockage sécurisé, purge si le token est
  /// expiré (pas de rafraîchissement possible côté API).
  Future<Result<AuthSession?>> currentSession();

  /// Identifiant (email ou téléphone) qui a servi à la dernière connexion
  /// réussie sur cet appareil, ou `null` — sert à l'écran PIN, qui ne le
  /// redemande pas sur un appareil déjà reconnu.
  Future<String?> lastIdentifier();

  /// L'appareil courant, tel qu'il est déclaré à l'API.
  Future<DeviceRegistration> currentDevice();

  /// Rafraîchit le profil (permissions incluses) depuis `auth/me`.
  Future<Result<AuthUser>> refreshUser();

  /// Révoque le token courant et purge le stockage local. Purge **même si
  /// l'appel réseau échoue** : une déconnexion demandée par l'utilisateur
  /// ne doit jamais le laisser connecté localement parce que le serveur
  /// était injoignable.
  Future<Result<void>> logout();

  /// Purge uniquement le stockage local, **sans appel réseau**. À utiliser
  /// quand le serveur a déjà rejeté le token (401 intercepté par
  /// `SessionExpiryInterceptor`).
  Future<void> purgeLocalSession();

  /// Choisit un nouveau PIN après un OTP `pin_reset`.
  Future<Result<void>> resetPin({
    required OtpVerification verification,
    required String pin,
  });

  /// Change le PIN d'un utilisateur déjà connecté (Réglages → Sécurité),
  /// à partir du PIN actuel — pas d'OTP.
  Future<Result<void>> changePin({
    required String currentPin,
    required String newPin,
  });

  /// Déverrouille un compte bloqué après un OTP `account_unlock`.
  Future<Result<void>> unlockAccount(OtpVerification verification);
}
