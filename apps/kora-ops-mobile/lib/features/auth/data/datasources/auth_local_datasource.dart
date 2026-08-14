import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../models/user_model.dart';

/// Datasource locale — simule pour l'instant l'authentification en mémoire
/// persistée (voir spec § 6 : "ne pas implémenter un véritable backend
/// d'authentification à ce stade"). Prête à être doublée d'une
/// `AuthRemoteDataSource` appelant [NetworkClient] quand l'API sera prête.
class AuthLocalDataSource {
  AuthLocalDataSource(this._preferences);

  final SharedPreferences _preferences;

  static const String _userKey = 'auth.current_user';

  Future<UserModel> login({required String username, required String password}) async {
    if (username.trim().isEmpty || password.trim().isEmpty) {
      throw const ApiException('Identifiants requis.');
    }
    // Authentification simulée — tout couple non vide est accepté.
    final UserModel user = UserModel(id: 'agent-001', fullName: username, role: 'Convoyeur');
    await _preferences.setString(_userKey, jsonEncode(user.toJson()));
    return user;
  }

  Future<void> logout() async {
    await _preferences.remove(_userKey);
  }

  Future<UserModel?> getCurrentUser() async {
    final String? raw = _preferences.getString(_userKey);
    if (raw == null) return null;
    try {
      return UserModel.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      throw const CacheException('Session locale corrompue.');
    }
  }
}
