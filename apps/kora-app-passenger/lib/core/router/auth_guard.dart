import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../constants/storage_keys.dart';
import '../di/injector.dart';

/// Vérifie si l'utilisateur a une session active, pour le guard de
/// navigation de [AppRouter] (voir `redirect` dans `app_router.dart`).
///
/// **Volontairement minimal** : vérifie seulement la présence d'un token
/// d'accès en stockage sécurisé, sans le valider (expiration, signature).
/// Cette vérification approfondie (refresh automatique sur 401...)
/// appartient à la couche `data` de la future feature `auth` — voir
/// GUIDE.md §13, "Ce qui n'est PAS encore fait". Ce guard sert seulement
/// à empêcher un utilisateur non connecté d'atteindre les écrans
/// protégés, et un utilisateur déjà connecté de revoir l'écran de login.
class AuthGuard {
  const AuthGuard._();

  static Future<bool> get isAuthenticated async {
    final token = await getIt<FlutterSecureStorage>().read(
      key: StorageKeys.accessToken,
    );
    return token != null && token.isNotEmpty;
  }
}
