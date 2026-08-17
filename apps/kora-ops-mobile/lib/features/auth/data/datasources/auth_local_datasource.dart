import 'package:shared_preferences/shared_preferences.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../models/user_model.dart';

/// Datasource locale — simule tout le flux d'authentification (voir spec
/// ticket : "connectés aux API réelles au Sprint 5"). Identifiants, PIN et
/// code OTP sont des valeurs fixes documentées ci-dessous et dans
/// document.md § Flux d'authentification.
///
/// **L'email n'est enregistré comme "activé sur cet appareil" qu'après un
/// parcours OTP réussi** ([verifyOtp]) — tant que ce n'est pas fait,
/// [getSavedEmail] retourne `null` et l'app redemande email + mot de passe
/// à chaque lancement.
///
/// **La session (`current_user`) reste en mémoire uniquement, jamais
/// persistée** (voir échange du 18/08/2026 — app en mode démo) : à chaque
/// redémarrage à froid, le PIN (ou email + mot de passe pour un appareil
/// jamais activé) est redemandé, même si l'appareil est déjà activé —
/// seule l'activation de l'appareil (`saved_email`) survit à un
/// redémarrage, pas la session en cours.
class AuthLocalDataSource {
  AuthLocalDataSource(this._preferences);

  final SharedPreferences _preferences;

  static const String _savedEmailKey = 'auth.saved_email';

  /// Session en cours — volontairement **non persistée**, voir docstring.
  UserModel? _currentUser;

  static const String provisionedFullName = 'Aïcha Hounkpé';
  static const String provisionedRole = 'Convoyeur';

  /// Identifiants acceptés par le mock — toute autre combinaison échoue.
  static const String mockEmail = 'aicha.hounkpe@kora-express.com';
  static const String mockPassword = 'Kora@2026';

  /// PIN accepté par le mock — toute autre valeur à 4 chiffres est refusée.
  static const String mockPin = '1234';

  /// Code OTP accepté par le mock — toute autre valeur à 6 chiffres déclenche
  /// l'écran "Code non valide".
  static const String mockOtpCode = '583187';

  Future<String?> getSavedEmail() async =>
      _preferences.getString(_savedEmailKey);

  Future<void> login({required String email, required String password}) async {
    await Future<void>.delayed(const Duration(milliseconds: 600));
    if (email.trim().toLowerCase() != mockEmail || password != mockPassword) {
      throw const ApiException('Email ou mot de passe incorrect.');
    }
  }

  Future<void> forgotPassword({required String email}) async {
    await Future<void>.delayed(const Duration(milliseconds: 600));
    if (email.trim().toLowerCase() != mockEmail) {
      throw const ApiException('Aucun compte associé à cet email.');
    }
    // Simule l'envoi d'un lien de réinitialisation — aucun envoi réel à ce
    // sprint.
  }

  Future<void> sendOtp({required String email}) async {
    // Simule la latence d'envoi d'email — aucun envoi réel à ce sprint.
    await Future<void>.delayed(const Duration(milliseconds: 500));
  }

  Future<UserModel> verifyOtp(
      {required String email, required String code}) async {
    await Future<void>.delayed(const Duration(milliseconds: 500));
    if (code != mockOtpCode) {
      throw const ApiException('Le code est incorrect ou expiré.');
    }

    final UserModel user = UserModel(
      id: 'agent-001',
      fullName: provisionedFullName,
      role: provisionedRole,
      email: email,
    );
    // L'appareil est désormais reconnu pour cet email — les prochaines
    // connexions n'auront plus qu'à saisir le PIN (mais toujours à chaque
    // lancement, voir docstring de la classe).
    await _preferences.setString(_savedEmailKey, email);
    _currentUser = user;
    return user;
  }

  Future<UserModel> verifyPin(
      {required String email, required String pin}) async {
    await Future<void>.delayed(const Duration(milliseconds: 400));
    if (pin != mockPin) {
      throw const ApiException('PIN incorrect.');
    }

    final UserModel user = UserModel(
      id: 'agent-001',
      fullName: provisionedFullName,
      role: provisionedRole,
      email: email,
    );
    _currentUser = user;
    return user;
  }

  Future<void> logout() async {
    // On efface la session courante mais on garde l'email activé, pour
    // retomber sur l'écran PIN (et non email + mot de passe) à la
    // reconnexion.
    _currentUser = null;
  }

  Future<UserModel?> getCurrentUser() async => _currentUser;
}
