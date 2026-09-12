import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../../core/constants/storage_keys.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/entities/auth_device.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/auth_user.dart';
import '../../domain/entities/device_platform.dart';

/// Couche DATA : persistance locale de la session, dans le stockage
/// **sécurisé** (jamais SharedPreferences — voir périmètre du ticket
/// "aucun secret en dur / stocké en clair").
///
/// La session survit à un redémarrage à froid : au lancement, l'app relit
/// le token ici (sans appel réseau, voir AuthRepository.currentSession)
/// pour décider si l'utilisateur est déjà connecté.
abstract class AuthLocalDataSource {
  Future<AuthSession?> readSession();
  Future<void> saveSession(AuthSession session);
  Future<void> saveUser(AuthUser user);
  Future<String?> readLastEmail();
  Future<void> clear();
}

class AuthLocalDataSourceImpl implements AuthLocalDataSource {
  AuthLocalDataSourceImpl(this._storage);

  final FlutterSecureStorage _storage;

  @override
  Future<AuthSession?> readSession() async {
    try {
      final raw = await _storage.read(key: StorageKeys.session);
      if (raw == null || raw.isEmpty) return null;

      final json = jsonDecode(raw) as Map<String, dynamic>;
      return _sessionFromJson(json);
    } on Object catch (error) {
      throw CacheException('Session locale illisible : $error');
    }
  }

  @override
  Future<void> saveSession(AuthSession session) async {
    try {
      await _storage.write(
        key: StorageKeys.session,
        value: jsonEncode(_sessionToJson(session)),
      );
      await _storage.write(
        key: StorageKeys.lastEmail,
        value: session.user.email,
      );
    } on Object catch (error) {
      throw CacheException('Impossible d’enregistrer la session : $error');
    }
  }

  @override
  Future<void> saveUser(AuthUser user) async {
    final session = await readSession();
    if (session == null) return;

    await saveSession(session.copyWith(user: user));
  }

  @override
  Future<String?> readLastEmail() => _storage.read(key: StorageKeys.lastEmail);

  @override
  Future<void> clear() async {
    // La session part, mais pas `deviceFingerprint` : l'appareil doit
    // rester reconnu du serveur après une déconnexion, sinon la prochaine
    // connexion redemanderait un OTP pour rien.
    await _storage.delete(key: StorageKeys.session);
    // `lastEmail` reste volontairement : l'écran PIN, à la prochaine
    // ouverture, propose le même compte plutôt que de redemander l'e-mail.
  }

  Map<String, dynamic> _sessionToJson(AuthSession session) => <String, dynamic>{
        'token': session.token,
        'token_type': session.tokenType,
        'expires_at': session.expiresAt.toIso8601String(),
        'user': <String, dynamic>{
          'uuid': session.user.uuid,
          'name': session.user.name,
          'email': session.user.email,
          'phone': session.user.phone,
          'status': session.user.status,
          'avatar_url': session.user.avatarUrl,
          'permissions': session.user.permissions,
        },
        if (session.device != null)
          'device': <String, dynamic>{
            'uuid': session.device!.uuid,
            'name': session.device!.name,
            'platform': session.device!.platform.value,
            'status': session.device!.status,
            'trusted_at': session.device!.trustedAt?.toIso8601String(),
            'last_seen_at': session.device!.lastSeenAt?.toIso8601String(),
          },
      };

  AuthSession _sessionFromJson(Map<String, dynamic> json) {
    final userJson = json['user'] as Map<String, dynamic>? ?? const {};
    final deviceJson = json['device'] as Map<String, dynamic>?;

    return AuthSession(
      token: json['token'] as String? ?? '',
      tokenType: json['token_type'] as String? ?? 'Bearer',
      expiresAt: DateTime.tryParse(json['expires_at'] as String? ?? '') ??
          DateTime.now(),
      user: AuthUser(
        uuid: userJson['uuid'] as String? ?? '',
        name: userJson['name'] as String? ?? '',
        email: userJson['email'] as String? ?? '',
        phone: userJson['phone'] as String?,
        status: userJson['status'] as String? ?? 'active',
        avatarUrl: userJson['avatar_url'] as String?,
        permissions: (userJson['permissions'] as List<dynamic>? ?? const [])
            .map((value) => '$value')
            .toList(),
      ),
      device: deviceJson == null
          ? null
          : AuthDevice(
              uuid: deviceJson['uuid'] as String? ?? '',
              name: deviceJson['name'] as String? ?? '',
              platform: DevicePlatform.fromValue(
                deviceJson['platform'] as String?,
              ),
              status: deviceJson['status'] as String? ?? 'trusted',
              trustedAt: deviceJson['trusted_at'] == null
                  ? null
                  : DateTime.tryParse(deviceJson['trusted_at'] as String),
              lastSeenAt: deviceJson['last_seen_at'] == null
                  ? null
                  : DateTime.tryParse(deviceJson['last_seen_at'] as String),
            ),
    );
  }
}
