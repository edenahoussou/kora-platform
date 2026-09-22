import '../../../../core/result/result.dart';
import '../entities/auth_device.dart';
import '../entities/auth_session.dart';
import '../entities/auth_user.dart';
import '../entities/otp_challenge.dart';
import '../entities/otp_purpose.dart';

/// Couche DOMAIN : tout ce que l'app sait faire en matière de comptes.
///
/// Porté du contrat de kora-app-passenger
/// (apps/kora-app-passenger/lib/features/auth/domain/repositories/auth_repository.dart),
/// avec [Result] à la place d'`Either<Failure, T>` — kora-ops-mobile
/// utilise déjà [Result] partout ailleurs (scan, impression...), et
/// changer ça pour l'auth seule aurait introduit une incohérence de style
/// pour un ticket qui ne porte pas dessus.
///
/// Le `verification_token` circule d'une étape à l'autre — il est à usage
/// unique et ne vaut que pour le motif ([OtpPurpose]) demandé.
abstract class AuthRepository {
  /// Envoie un code à six chiffres à [email] pour [purpose].
  Future<Result<OtpChallenge>> requestOtp({
    required String email,
    required OtpPurpose purpose,
  });

  /// Vérifie le code saisi et rend le jeton à usage unique.
  Future<Result<OtpVerification>> verifyOtp({
    required String challengeUuid,
    required OtpPurpose purpose,
    required String code,
  });

  /// Connecte un compte existant (email + PIN).
  ///
  /// Rend un [LoginNeedsOtp] — pas une erreur — quand le serveur ne
  /// reconnaît pas l'appareil : il faut alors vérifier un code et rappeler
  /// cette méthode avec [verification].
  Future<Result<LoginOutcome>> login({
    required String email,
    required String pin,
    OtpVerification? verification,
  });

  /// Session en cours, ou `null` si personne n'est connecté. Ne contacte
  /// pas le réseau : relit le stockage sécurisé, purge si le token est
  /// expiré (pas de rafraîchissement possible côté API).
  Future<Result<AuthSession?>> currentSession();

  /// Adresse du dernier compte connecté sur cet appareil, ou `null` — sert
  /// à l'écran PIN, qui ne redemande pas l'e-mail sur un appareil déjà
  /// reconnu.
  Future<String?> lastEmail();

  /// L'appareil courant, tel qu'il est déclaré à l'API.
  Future<DeviceRegistration> currentDevice();

  /// Rafraîchit le profil (permissions incluses) depuis `auth/me`.
  Future<Result<AuthUser>> refreshUser();

  /// Révoque le token courant et purge le stockage local. Purge **même si
  /// l'appel réseau échoue** : une déconnexion demandée par
  /// l'utilisateur ne doit jamais le laisser connecté localement parce que
  /// le serveur était injoignable.
  Future<Result<void>> logout();

  /// Purge uniquement le stockage local, **sans appel réseau**. À utiliser
  /// quand le serveur a déjà rejeté le token (401 intercepté par
  /// [SessionExpiryInterceptor]) : appeler `logout()` ici referait un
  /// appel réseau qui répond lui-même 401, ce qui redéclenche
  /// l'intercepteur en boucle infinie.
  Future<void> purgeLocalSession();

  /// Choisit un nouveau PIN après un OTP `pin_reset`.
  Future<Result<void>> resetPin({
    required OtpVerification verification,
    required String pin,
  });

  /// Déverrouille un compte bloqué après un OTP `account_unlock`.
  Future<Result<void>> unlockAccount(OtpVerification verification);
}
