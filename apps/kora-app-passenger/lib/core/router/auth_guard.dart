import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../constants/storage_keys.dart';
import '../di/injector.dart';

/// Vérifie si un token d'accès est présent en stockage sécurisé.
///
/// **N'est plus branché sur la navigation** : `_redirect`
/// (`app_router.dart`) s'appuie sur l'état d'`AuthBloc`, restauré au
/// lancement par `AuthRepository.currentSession` et mis à jour sur 401 par
/// `SessionExpiry`. La vérification ici reste volontairement minimale
/// (présence du token, sans validation d'expiration ni de révocation) ;
/// supprimer la classe si aucun usage ne la justifie.
class AuthGuard {
  const AuthGuard._();

  static Future<bool> get isAuthenticated async {
    final token = await getIt<FlutterSecureStorage>().read(
      key: StorageKeys.accessToken,
    );
    return token != null && token.isNotEmpty;
  }
}
