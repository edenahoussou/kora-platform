import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/entities/device_info.dart';
import '../models/user_model.dart';

/// Datasource locale — simule pour l'instant tout le flux d'authentification
/// (voir spec ticket : "connectés aux API réelles au Sprint 5"). Le code OTP
/// et le PIN sont des valeurs fixes documentées ci-dessous et dans
/// document.md § Flux d'authentification, pour permettre de tester le
/// parcours complet sans backend.
class AuthLocalDataSource {
  AuthLocalDataSource(this._preferences);

  final SharedPreferences _preferences;

  static const String _savedEmailKey = 'auth.saved_email';
  static const String _currentUserKey = 'auth.current_user';

  /// Code OTP accepté par le mock — toute autre valeur à 6 chiffres déclenche
  /// l'écran "Code non valide".
  static const String mockOtpCode = '583187';

  /// PIN accepté par le mock — toute autre valeur à 4 chiffres est refusée.
  static const String mockPin = '1234';

  Future<String?> getSavedEmail() async =>
      _preferences.getString(_savedEmailKey);

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
      fullName: email.split('@').first,
      role: 'Convoyeur',
      email: email,
    );

    await _preferences.setString(_savedEmailKey, email);
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
      throw const ApiException('Aucun compte enregistré sur cet appareil.');
    }

    final UserModel user = UserModel(
      id: 'agent-001',
      fullName: email.split('@').first,
      role: 'Convoyeur',
      email: email,
    );
    await _preferences.setString(_currentUserKey, jsonEncode(user.toJson()));
    return user;
  }

  Future<DeviceInfo> getCurrentDeviceInfo() async {
    // Mocké — un vrai fingerprint (modèle, ville via géoloc/IP) sera
    // branché avec l'API au Sprint 5.
    await Future<void>.delayed(const Duration(milliseconds: 300));
    return const DeviceInfo(
      deviceModel: 'Tecno CAMON',
      city: 'Cotonou',
      attemptLabel: 'À l\'instant',
    );
  }

  Future<void> logout() async {
    // On efface la session courante mais on garde l'email de l'appareil,
    // pour retomber sur l'écran PIN (et non email) à la reconnexion.
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
