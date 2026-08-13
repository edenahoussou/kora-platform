import 'package:dio/dio.dart';

/// Une requête mutante (`POST`/`PUT`/`PATCH`/`DELETE`) mise en attente par
/// [OfflineRequestQueue], en vue d'être rejouée une fois la connexion
/// revenue.
class QueuedRequest {
  QueuedRequest(this.options);

  final RequestOptions options;

  /// Horodatage de mise en file, utile pour un affichage type "en attente
  /// depuis...".
  final DateTime queuedAt = DateTime.now();
}

/// File d'attente des requêtes échouées faute de connexion.
///
/// **Volontairement pas branchée automatiquement sur [DioClient]** : mettre
/// en file un `POST` de réservation ou de paiement sans que l'app en
/// décide explicitement serait dangereux (l'utilisateur croirait l'action
/// terminée). C'est donc à l'app d'appeler [enqueue] explicitement, dans le
/// `catch` d'un repository, pour les endpoits où différer l'action est
/// réellement acceptable :
/// ```dart
/// try {
///   await dio.post(ApiConstants.someMutation, data: payload);
/// } on DioException catch (e) {
///   if (e.apiException is NetworkApiException) {
///     offlineQueue.enqueue(e.requestOptions);
///     return const Right(QueuedForSync());
///   }
///   rethrow;
/// }
/// ```
///
/// Le contenu de la file ne survit pas au redémarrage de l'app (file en
/// mémoire) — à combiner avec un stockage persistant côté app si des
/// requêtes doivent survivre à un kill de process.
class OfflineRequestQueue {
  final _pending = <QueuedRequest>[];

  /// Requêtes actuellement en attente, dans l'ordre de mise en file.
  List<QueuedRequest> get pending => List.unmodifiable(_pending);

  bool get isEmpty => _pending.isEmpty;
  bool get isNotEmpty => _pending.isNotEmpty;

  void enqueue(RequestOptions options) {
    _pending.add(QueuedRequest(options));
  }

  void clear() => _pending.clear();

  /// Rejoue chaque requête en attente, dans l'ordre, via [dio]. Une requête
  /// qui réussit est retirée de la file ; une requête qui échoue à nouveau
  /// y reste, et interrompt le traitement des suivantes (pour préserver
  /// l'ordre — ex: ne pas rejouer une mise à jour avant la création dont
  /// elle dépend).
  ///
  /// Retourne la liste des requêtes effectivement rejouées avec succès.
  Future<List<QueuedRequest>> flush(Dio dio) async {
    final flushed = <QueuedRequest>[];

    while (_pending.isNotEmpty) {
      final next = _pending.first;
      try {
        await dio.fetch<dynamic>(next.options);
        flushed.add(next);
        _pending.removeAt(0);
      } on DioException {
        break;
      }
    }

    return flushed;
  }
}
