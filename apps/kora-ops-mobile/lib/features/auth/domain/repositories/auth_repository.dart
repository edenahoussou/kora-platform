import '../../../../core/result/result.dart';
import '../entities/device_info.dart';
import '../entities/user_entity.dart';

/// Contrat du flux d'authentification KORA OPS : email → code OTP → PIN,
/// avec vérification d'appareil (voir document.md § Flux d'authentification
/// pour le détail de chaque étape et les données mockées utilisées ce
/// sprint).
abstract interface class AuthRepository {
  /// Email déjà enregistré sur cet appareil (utilisateur déjà passé par la
  /// création de compte), ou `null` si c'est la première utilisation —
  /// détermine le premier écran affiché (email vs PIN).
  Future<Result<String?>> getSavedEmail();

  /// Déclenche l'envoi (simulé) d'un code OTP à 6 chiffres à [email].
  Future<Result<void>> sendOtp({required String email});

  /// Vérifie le code OTP saisi. En cas de succès, enregistre [email]
  /// comme email de l'appareil et retourne l'utilisateur authentifié.
  Future<Result<UserEntity>> verifyOtp({required String email, required String code});

  /// Vérifie le PIN à 4 chiffres pour l'utilisateur déjà enregistré sur cet
  /// appareil.
  Future<Result<UserEntity>> verifyPin({required String pin});

  /// Informations mockées sur l'appareil courant, pour l'écran "Nouvel
  /// appareil détecté".
  Future<Result<DeviceInfo>> getCurrentDeviceInfo();

  Future<Result<void>> logout();
  Future<Result<UserEntity?>> getCurrentUser();
}
