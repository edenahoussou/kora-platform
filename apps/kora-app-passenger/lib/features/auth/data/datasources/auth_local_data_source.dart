import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../../../core/constants/storage_keys.dart';
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

  /// Efface tout ce qui touche à la session — **pas** l'empreinte de
  /// l'appareil, qui doit survivre à une déconnexion pour éviter un OTP
  /// à la reconnexion.
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

    return AuthSession(
      token: token,
      // Sans échéance connue, on considère la session expirée : mieux
      // vaut redemander une connexion qu'appeler l'API avec un token
      // mort et laisser l'utilisateur devant des erreurs.
      expiresAt: expiresAt ?? DateTime.fromMillisecondsSinceEpoch(0),
      user: AuthDtos.user(jsonDecode(rawUser)),
    );
  }

  @override
  Future<void> saveUser(AuthUser user) => _storage.write(
    key: StorageKeys.authUser,
    value: jsonEncode(_userToJson(user)),
  );

  @override
  Future<void> clear() async {
    await Future.wait([
      _storage.delete(key: StorageKeys.accessToken),
      _storage.delete(key: StorageKeys.tokenExpiresAt),
      _storage.delete(key: StorageKeys.userId),
      _storage.delete(key: StorageKeys.authUser),
    ]);
  }

  /// Reprend les noms de champs de l'API, pour que [AuthDtos.user] sache
  /// relire ce qu'on écrit ici sans second lecteur à maintenir.
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
