import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/auth_device.dart';
import '../entities/auth_session.dart';
import '../entities/auth_user.dart';
import '../entities/otp_challenge.dart';
import '../entities/otp_purpose.dart';
import '../entities/user_preferences.dart';

/// Couche DOMAIN : tout ce que l'app sait faire en matière de comptes.
///
/// Le découpage suit le flux documenté (`docs/mintlify/authentication.mdx`)
/// : demander un code, le vérifier, puis **soit** créer le compte, **soit**
/// se connecter. Le `verification_token` circule d'une étape à l'autre —
/// il est à usage unique et ne vaut que pour le motif demandé.
abstract class AuthRepository {
  /// Envoie un code à six chiffres à [email].
  Future<Either<Failure, OtpChallenge>> requestOtp({
    required String email,
    required OtpPurpose purpose,
  });

  /// Vérifie le code saisi et rend le jeton à usage unique.
  Future<Either<Failure, OtpVerification>> verifyOtp({
    required String challengeUuid,
    required OtpPurpose purpose,
    required String code,
  });

  /// Crée le compte : PIN choisi + enregistrement de l'appareil.
  Future<Either<Failure, AuthSession>> completeRegistration({
    required OtpVerification verification,
    required String pin,
  });

  /// Connecte un compte existant.
  ///
  /// Rend un [LoginNeedsOtp] — et non une erreur — quand le serveur ne
  /// reconnaît pas l'appareil : il faut alors vérifier un code et
  /// rappeler cette méthode avec [verification].
  Future<Either<Failure, LoginOutcome>> login({
    required String email,
    required String pin,
    OtpVerification? verification,
  });

  /// Session en cours, ou `null` si personne n'est connecté.
  ///
  /// Relit le token stocké et le compte associé ; ne contacte pas le
  /// réseau, pour que le démarrage de l'app ne dépende pas de la
  /// connectivité.
  Future<Either<Failure, AuthSession?>> currentSession();

  /// Adresse du dernier compte connecté sur cet appareil, ou `null`.
  ///
  /// Sert à l'écran de connexion, qui ne demande que le PIN : la maquette
  /// suppose qu'on sait déjà de quel compte il s'agit.
  Future<String?> lastEmail();

  /// L'appareil courant, tel qu'il est déclaré à l'API.
  ///
  /// Sert à l'écran « Nouvel appareil » : montrer le nom que le serveur
  /// vient de refuser est le seul moyen, pour l'utilisateur, de savoir
  /// s'il regarde sa propre tentative.
  Future<DeviceRegistration> currentDevice();

  /// Rafraîchit le profil depuis `auth/me`.
  Future<Either<Failure, AuthUser>> refreshUser();

  /// Complète le profil (`PUT auth/me`).
  ///
  /// L'inscription ne demande qu'une adresse e-mail : le serveur crée le
  /// compte avec un nom dérivé de celle-ci. C'est ici que le vrai nom et
  /// le téléphone arrivent, juste après l'écran des autorisations.
  ///
  /// L'e-mail, que l'API exige, n'est pas demandé : la couche data le
  /// reprend de la session en cours.
  Future<Either<Failure, AuthUser>> updateProfile({
    required String name,
    String? email,
    String? phone,
    String? countryCode,
    DateTime? birthDate,
  });

  /// Réglages du compte (`GET auth/preferences`).
  Future<Either<Failure, UserPreferences>> preferences();

  /// Enregistre les réglages (`PUT auth/preferences`) et renvoie ce que
  /// le serveur a retenu.
  Future<Either<Failure, UserPreferences>> savePreferences(
    UserPreferences preferences,
  );

  /// Révoque le token courant et purge le stockage local.
  ///
  /// Purge **même si l'appel réseau échoue** : un utilisateur qui demande
  /// à se déconnecter ne doit pas rester connecté sur son téléphone parce
  /// que le serveur était injoignable.
  Future<Either<Failure, void>> logout();

  /// Choisit un nouveau PIN après un OTP `pin_reset`.
  Future<Either<Failure, void>> resetPin({
    required OtpVerification verification,
    required String pin,
  });

  /// Change le PIN d'un compte connecté.
  Future<Either<Failure, void>> changePin({
    required String currentPin,
    required String newPin,
  });

  /// Déverrouille un compte bloqué après un OTP `account_unlock`.
  Future<Either<Failure, void>> unlockAccount(OtpVerification verification);

  /// Échange le jeton d'un fournisseur (Google, Facebook, TikTok) contre
  /// une session KORA.
  Future<Either<Failure, AuthSession>> loginWithProvider({
    required String provider,
    required String providerToken,
  });

  /// Appareils de confiance du compte.
  Future<Either<Failure, List<AuthDevice>>> devices();

  /// Retire la confiance accordée à un appareil.
  Future<Either<Failure, void>> revokeDevice(String uuid);
}
