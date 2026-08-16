import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/entities/device_info.dart';
import '../models/user_model.dart';

/// Datasource locale — simule tout le flux d'authentification (voir spec
/// ticket : "connectés aux API réelles au Sprint 5"). Le code OTP et le PIN
/// sont des valeurs fixes documentées ci-dessous et dans document.md §
/// Flux d'authentification.
///
/// **Pas d'auto-inscription côté OPS** : les agents sont provisionnés par
/// un administrateur, donc l'email de l'agent est pré-enregistré sur
/// l'appareil dès le premier lancement ([_seedProvisionedAccountIfNeeded])
/// plutôt que saisi via un écran de création de compte.
class AuthLocalDataSource {
  AuthLocalDataSource(this._preferences);

  final SharedPreferences _preferences;

  static const String _savedEmailKey = 'auth.saved_email';
  static const String _currentUserKey = 'auth.current_user';

  /// Compte provisionné par défaut sur ce terminal (mock — un vrai
  /// provisioning arrivera avec l'API au Sprint 5).
  static const String provisionedEmail = 'aicha.hounkpe@kora-express.com';
  static const String provisionedFullName = 'Aïcha Hounkpé';
  static const String provisionedRole = 'Convoyeur';

  /// Code OTP accepté par le mock — toute autre valeur à 6 chiffres déclenche
  /// l'écran "Code non valide".
  static const String mockOtpCode = '583187';

  /// PIN accepté par le mock — toute autre valeur à 4 chiffres est refusée.
  static const String mockPin = '1234';

  Future<String?> getSavedEmail() async {
    await _seedProvisionedAccountIfNeeded();
    return _preferences.getString(_savedEmailKey);
  }

  Future<void> _seedProvisionedAccountIfNeeded() async {
    if (_preferences.getString(_savedEmailKey) == null) {
      await _preferences.setString(_savedEmailKey, provisionedEmail);
    }
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
    await _preferences.setString(_currentUserKey, jsonEncode(user.toJson()));
    return user;
  }

  Future<UserModel> verifyPin({required String pin}) async {
    await Future<void>.delayed(const Duration(milliseconds: 400));
    if (pin != mockPin) {
      throw const ApiException('PIN incorrect.');
    }

    final String? email = await getSavedEmail();
    if (email == null) {
      throw const ApiException('Aucun compte provisionné sur cet appareil.');
    }

    final UserModel user = UserModel(
      id: 'agent-001',
      fullName: provisionedFullName,
      role: provisionedRole,
      email: email,
    );
    await _preferences.setString(_currentUserKey, jsonEncode(user.toJson()));
    return user;
  }

  Future<DeviceInfo> getCurrentDeviceInfo() async {
    // Mocké — un vrai fingerprint (modèle, ville via géoloc/IP) sera
    // branché avec l'API au Sprint 5. La détection "nouvel appareil"
    // elle-même est toujours déclenchée après un PIN valide, pour la
    // démonstration du parcours (voir document.md).
    await Future<void>.delayed(const Duration(milliseconds: 300));
    return const DeviceInfo(
      deviceModel: 'Tecno CAMON',
      city: 'Cotonou',
      attemptLabel: 'À l\'instant',
    );
  }

  Future<void> logout() async {
    // On efface la session courante mais on garde l'email provisionné, pour
    // retomber sur l'écran PIN à la reconnexion.
    await _preferences.remove(_currentUserKey);
  }

  Future<UserModel?> getCurrentUser() async {
    final String? raw = _preferences.getString(_currentUserKey);
    if (raw == null) return null;
    try {
      return UserModel.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      throw const CacheException('Session locale corrompue.');
    }
  }
}
