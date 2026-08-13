import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

/// Une "réponse" simulée par [FakeHttpClientAdapter] : soit un
/// [ResponseBody] (succès ou erreur HTTP avec `statusCode`), soit une
/// [DioException] à lever directement (timeout, perte de connexion...).
typedef FakeAdapterStep =
    FutureOr<ResponseBody> Function(RequestOptions options);

/// Adapter [Dio] de test : remplace le vrai transport HTTP par une suite de
/// [FakeAdapterStep] fournie par le test, ce qui permet d'exercer toute la
/// chaîne d'interceptors d'un [Dio] réel (auth, retry, cache, erreurs...)
/// sans jamais toucher au réseau.
///
/// Chaque appel à [fetch] consomme l'étape suivante de [steps] ; le dernier
/// élément est réutilisé pour tous les appels excédentaires.
class FakeHttpClientAdapter implements HttpClientAdapter {
  FakeHttpClientAdapter(this.steps);

  final List<FakeAdapterStep> steps;

  /// Toutes les [RequestOptions] reçues, dans l'ordre — utile pour vérifier
  /// qu'un interceptor a bien modifié la requête (header ajouté...).
  final List<RequestOptions> capturedRequests = [];

  int get callCount => capturedRequests.length;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    capturedRequests.add(options);
    final index = capturedRequests.length - 1;
    final step = steps[index < steps.length ? index : steps.length - 1];
    return step(options);
  }

  @override
  void close({bool force = false}) {}
}

/// Construit un [ResponseBody] JSON prêt à l'emploi.
ResponseBody jsonResponseBody(Object? data, int statusCode) {
  return ResponseBody.fromString(
    jsonEncode(data),
    statusCode,
    headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    },
  );
}
