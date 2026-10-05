import 'dart:async';

import 'package:dio/dio.dart';

/// Ce qui prévient l'app qu'un token n'est plus accepté.
///
/// Le token vaut sept jours et rien ne le renouvelle. Sa péremption est
/// vérifiée au **lancement** ([AuthRepository.currentSession]), mais une
/// app restée ouverte — ou reprise sans redémarrage — traverse l'échéance
/// sans s'en apercevoir : le serveur répond alors 401 à tout, et l'accueil
/// affichait « Impossible de charger l'accueil · Unauthenticated » avec un
/// bouton « Réessayer » qui ne pouvait que réessayer en vain.
///
/// Le serveur peut aussi révoquer le token avant l'heure — appareil retiré
/// depuis l'écran Sécurité, compte suspendu. Le cas est le même : c'est la
/// connexion qu'il faut, pas un nouvel essai.
class SessionExpiry {
  SessionExpiry();

  final _expired = StreamController<void>.broadcast();

  Stream<void> get expirations => _expired.stream;

  void notify() => _expired.add(null);

  Future<void> close() => _expired.close();
}

/// Signale une session refusée, sans toucher à la requête.
///
/// **Seules les requêtes qui portaient un token comptent.** Un PIN erroné
/// fait aussi répondre 401 à `auth/login`, mais celle-là part sans
/// `Authorization` : la confondre avec une session périmée renverrait
/// l'utilisateur à l'écran de connexion... qu'il a sous les yeux.
class SessionExpiredInterceptor extends Interceptor {
  SessionExpiredInterceptor(this._expiry);

  final SessionExpiry _expiry;

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final hadToken = err.requestOptions.headers.containsKey('Authorization');
    if (err.response?.statusCode == 401 && hadToken) _expiry.notify();

    handler.next(err);
  }
}
