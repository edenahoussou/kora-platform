import '../../../../core/result/result.dart';
import '../entities/user_entity.dart';

/// Contrat du flux d'authentification KORA OPS (voir document.md § Flux
/// d'authentification) :
///
///   Première connexion sur un appareil :
///     email + mot de passe → PIN → OTP d'activation → connecté
///   Appareil déjà reconnu (email déjà activé une fois sur cet appareil) :
///     PIN seul → connecté (pas d'email, pas d'OTP)
abstract interface class AuthRepository {
  /// Email déjà activé sur cet appareil (a terminé le parcours OTP au
  /// moins une fois), ou `null` si c'est la première utilisation —
  /// détermine le premier écran affiché (email+mot de passe vs PIN direct).
  Future<Result<String?>> getSavedEmail();

  /// Vérifie l'email et le mot de passe saisis (première connexion sur cet
  /// appareil). Ne marque pas encore l'appareil comme reconnu — cela
  /// n'arrive qu'après l'OTP d'activation, voir [verifyOtp].
  Future<Result<void>> login({required String email, required String password});

  /// Déclenche l'envoi (simulé) d'un lien/code de réinitialisation à
  /// [email] — écran "Mot de passe oublié".
  Future<Result<void>> forgotPassword({required String email});

  /// Déclenche l'envoi (simulé) d'un code OTP à 6 chiffres à [email].
  Future<Result<void>> sendOtp({required String email});

  /// Vérifie le code OTP saisi. En cas de succès, marque [email] comme
  /// activé sur cet appareil (les connexions suivantes n'auront plus qu'à
  /// saisir le PIN) et retourne l'utilisateur authentifié.
  Future<Result<UserEntity>> verifyOtp(
      {required String email, required String code});

  /// Vérifie le PIN à 4 chiffres pour [email] (celui saisi à l'étape
  /// précédente pour une première connexion, ou l'email déjà enregistré
  /// sur l'appareil pour une reconnexion).
  Future<Result<UserEntity>> verifyPin(
      {required String email, required String pin});

  Future<Result<void>> logout();
  Future<Result<UserEntity?>> getCurrentUser();
}
