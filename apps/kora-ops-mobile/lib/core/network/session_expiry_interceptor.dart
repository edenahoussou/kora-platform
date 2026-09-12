import 'package:dio/dio.dart';

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
    if (err.response?.statusCode == 401) {
      onSessionExpired();
    }
    handler.next(err);
  }
}
