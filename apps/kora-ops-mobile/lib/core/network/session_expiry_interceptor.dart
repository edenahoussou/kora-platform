import 'package:dio/dio.dart';

import '../constants/api_constants.dart';

/// Détecte un 401 sur **n'importe quel** appel API protégé et déclenche
/// [onSessionExpired] — c'est ce qui fait que "les appels API protégés
/// utilisent le mécanisme d'authentification validé" (périmètre du
/// ticket) ne dépend pas de chaque écran pour réagir à une session
/// expirée : un seul point, ici, couvre toute l'app.
///
/// Vérifie le code HTTP brut plutôt que le type [ApiException] déjà
/// traduit : l'ordre d'exécution des intercepteurs Dio sur une erreur est
/// l'inverse de leur ordre d'ajout, et cet intercepteur est ajouté
/// après ceux de `DioClient` — le statut HTTP, lui, est fiable quel que
/// soit cet ordre.
class SessionExpiryInterceptor extends Interceptor {
  SessionExpiryInterceptor({required this.onSessionExpired});

  final void Function() onSessionExpired;

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    // `logout` lui-même peut répondre 401 (token déjà invalide côté
    // serveur, c'est précisément pour ça qu'on l'appelle) : ne pas
    // redéclencher onSessionExpired dessus, sinon on retombe sur logout()
    // → 401 → onSessionExpired → logout() ... en boucle infinie. Filet de
    // sécurité en plus du fix côté AuthCubit.sessionExpired().
    final isLogoutCall = err.requestOptions.path == ApiConstants.logout;
    if (err.response?.statusCode == 401 && !isLogoutCall) {
      onSessionExpired();
    }
    handler.next(err);
  }
}
