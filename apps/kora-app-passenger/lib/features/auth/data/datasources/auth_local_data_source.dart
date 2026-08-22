import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../../core/constants/storage_keys.dart';
import '../../domain/entities/auth_device.dart';
import '../../domain/entities/auth_session.dart';
import '../../domain/entities/auth_user.dart';
import '../models/auth_dtos.dart';

/// Couche DATA : la session sur l'appareil.
///
/// Tout passe par le **stockage sécurisé** — Keystore sur Android,
/// Keychain sur iOS — et jamais par `SharedPreferences` : un token y
/// serait lisible par n'importe quelle app sur un téléphone rooté.
///
/// L'échéance et le profil sont rangés à côté du token pour que l'app
/// puisse ouvrir sur le nom de l'utilisateur, et savoir que sa session est
/// périmée, **sans réseau**. Il n'existe pas d'endpoint de
/// rafraîchissement : une session expirée est une session finie.
abstract class AuthLocalDataSource {
  Future<void> saveSession(AuthSession session);

  /// Session stockée, ou `null` si personne n'est connecté.
  Future<AuthSession?> readSession();

  /// Met à jour le seul profil, après un `auth/me`.
  Future<void> saveUser(AuthUser user);

  /// Adresse du dernier compte connecté, ou `null` si l'app n'a jamais
  /// servi.
  Future<String?> readLastEmail();

  /// Efface tout ce qui touche à la session.
  ///
  /// **Ni l'empreinte de l'appareil ni la dernière adresse** : la
  /// première évite un OTP à la reconnexion, la seconde évite de
  /// redemander qui vous êtes. Aucune des deux ne donne accès au compte.
  Future<void> clear();
}

class AuthLocalDataSourceImpl implements AuthLocalDataSource {
  AuthLocalDataSourceImpl(this._storage);

  final FlutterSecureStorage _storage;

  @override
  Future<void> saveSession(AuthSession session) async {
    await Future.wait([
      _storage.write(key: StorageKeys.accessToken, value: session.token),
      _storage.write(
        key: StorageKeys.tokenExpiresAt,
        value: session.expiresAt.toIso8601String(),
      ),
      _storage.write(key: StorageKeys.userId, value: session.user.uuid),
      _storage.write(
        key: StorageKeys.authUser,
        value: jsonEncode(_userToJson(session.user)),
      ),
      _storage.write(key: StorageKeys.lastEmail, value: session.user.email),
      // Nul si le serveur n'a pas renvoyé d'appareil : on efface alors
      // celui d'une session précédente plutôt que de le laisser mentir.
      _storage.write(
        key: StorageKeys.authDevice,
        value: session.device == null
            ? null
            : jsonEncode(_deviceToJson(session.device!)),
      ),
    ]);
  }

  @override
  Future<AuthSession?> readSession() async {
    final token = await _storage.read(key: StorageKeys.accessToken);
    if (token == null || token.isEmpty) return null;

    final rawUser = await _storage.read(key: StorageKeys.authUser);
    if (rawUser == null || rawUser.isEmpty) {
      // Un token sans son profil : état incohérent, laissé par une
      // écriture interrompue. On repart de zéro plutôt que d'ouvrir l'app
      // sur un utilisateur vide.
      await clear();
      return null;
    }

    final expiresAt = DateTime.tryParse(
      await _storage.read(key: StorageKeys.tokenExpiresAt) ?? '',
    );

    final rawDevice = await _storage.read(key: StorageKeys.authDevice);

    return AuthSession(
      token: token,
      // Sans échéance connue, on considère la session expirée : mieux
      // vaut redemander une connexion qu'appeler l'API avec un token
      // mort et laisser l'utilisateur devant des erreurs.
      expiresAt: expiresAt ?? DateTime.fromMillisecondsSinceEpoch(0),
      user: AuthDtos.user(jsonDecode(rawUser)),
      device: rawDevice == null || rawDevice.isEmpty
          ? null
          : AuthDtos.device(jsonDecode(rawDevice)),
    );
  }

  @override
  Future<void> saveUser(AuthUser user) => _storage.write(
    key: StorageKeys.authUser,
    value: jsonEncode(_userToJson(user)),
  );

  @override
  Future<String?> readLastEmail() => _storage.read(key: StorageKeys.lastEmail);

  @override
  Future<void> clear() async {
    await Future.wait([
      _storage.delete(key: StorageKeys.accessToken),
      _storage.delete(key: StorageKeys.tokenExpiresAt),
      _storage.delete(key: StorageKeys.userId),
      _storage.delete(key: StorageKeys.authUser),
      _storage.delete(key: StorageKeys.authDevice),
    ]);
  }

  /// Reprend les noms de champs de l'API, pour que [AuthDtos.user] sache
  /// relire ce qu'on écrit ici sans second lecteur à maintenir.
  Map<String, dynamic> _deviceToJson(AuthDevice device) => {
    'uuid': device.uuid,
    'name': device.name,
    'platform': device.platform.value,
    'status': device.status,
    'trusted_at': device.trustedAt?.toIso8601String(),
    'last_seen_at': device.lastSeenAt?.toIso8601String(),
  };

  Map<String, dynamic> _userToJson(AuthUser user) => {
    'uuid': user.uuid,
    'name': user.name,
    'email': user.email,
    'phone': user.phone,
    'country_code': user.countryCode,
    'birth_date': user.birthDate?.toIso8601String(),
    'status': user.status,
    'avatar_url': user.avatarUrl,
    'permissions': user.permissions,
  };
}
