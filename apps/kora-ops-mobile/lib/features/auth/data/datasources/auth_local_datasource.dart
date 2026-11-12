import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../../core/constants/storage_keys.dart';
import '../../../../core/exceptions/app_exceptions.dart';
import '../../domain/entities/auth_device.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/auth_user.dart';
import '../../domain/entities/device_platform.dart';

/// Couche DATA : persistance locale de la session, dans le stockage
/// **sécurisé** (jamais SharedPreferences).
///
/// La session survit à un redémarrage à froid : au lancement, l'app relit
/// le token ici (sans appel réseau, voir AuthRepository.currentSession)
/// pour décider si l'utilisateur est déjà connecté.
abstract class AuthLocalDataSource {
  Future<AuthSession?> readSession();
  Future<void> saveSession(AuthSession session);
  Future<void> saveUser(AuthUser user);

  /// Identifiant (email ou téléphone) qui a servi à la dernière connexion
  /// réussie sur cet appareil — sert à l'écran PIN, qui ne le redemande
  /// pas sur un appareil déjà reconnu.
  Future<void> saveLastIdentifier(String identifier);
  Future<String?> readLastIdentifier();

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
  Future<void> saveLastIdentifier(String identifier) =>
      _storage.write(key: StorageKeys.lastIdentifier, value: identifier);

  @override
  Future<String?> readLastIdentifier() async {
    final identifier = await _storage.read(key: StorageKeys.lastIdentifier);
    if (identifier != null) return identifier;

    // Compatibilité : appareils mis à jour depuis une version où seul un
    // email était mémorisé (clé `auth_last_email`), avant l'ajout du
    // login par téléphone — évite de redemander l'identifiant une fois
    // pour rien à la mise à jour.
    return _storage.read(key: StorageKeys.legacyLastEmail);
  }

  @override
  Future<void> clear() async {
    // La session part, mais pas `deviceFingerprint` ni `lastIdentifier` :
    // l'appareil doit rester reconnu du serveur après une déconnexion, et
    // l'écran PIN doit proposer le même compte à la prochaine ouverture
    // plutôt que de redemander l'identifiant.
    await _storage.delete(key: StorageKeys.session);
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
          'roles': session.user.roles,
          'requires_terminal': session.user.requiresTerminal,
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
        roles: (userJson['roles'] as List<dynamic>? ?? const [])
            .map((value) => '$value')
            .toList(),
        requiresTerminal: userJson['requires_terminal'] as bool? ?? true,
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
