import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/payment_attempt.dart';
import '../../domain/entities/payment_catalog.dart';
import '../models/payment_attempt_model.dart';
import '../models/payment_catalog_model.dart';

/// Couche DATA : le catalogue des moyens de paiement.
///
/// L'endpoint exige une session (`auth:sanctum`) : sans token il répond
/// 401, et le repository traduira ça en `AuthFailure`. Comme les autres
/// data sources, celui-ci laisse remonter les `DioException` telles
/// quelles.
class PaymentRemoteDataSource {
  const PaymentRemoteDataSource(this._dio);

  final Dio _dio;

  /// Les moyens de paiement activés côté serveur.
  Future<PaymentCatalog> catalog() async {
    final response = await _dio.get<dynamic>(ApiConstants.paymentMethods);

    return PaymentCatalogModel.fromJson(
      ApiJson.map(_payload(response.data)),
    ).toEntity();
  }

  /// Lance un paiement pour une réservation retenue.
  ///
  /// [idempotencyKey] protège du double débit : un réseau qui coupe après
  /// l'envoi mais avant la réponse laisse le voyageur retaper « Payer »,
  /// et c'est cette clé qui fait répondre au serveur le paiement déjà créé
  /// (200) au lieu d'en ouvrir un second (201).
  Future<PaymentAttempt> initiate({
    required String reservationId,
    required String provider,
    required String network,
    required String phone,
    required String idempotencyKey,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.paymentInitiate,
      data: {
        'reservation_uuid': reservationId,
        // Seule valeur que le serveur accepte aujourd'hui.
        'method': 'mobile_money',
        'provider': provider,
        'mobile_money_network': network,
        'phone': phone,
        'idempotency_key': idempotencyKey,
      },
    );

    return PaymentAttemptModel.fromJson(
      ApiJson.map(_payload(response.data)),
    ).toEntity();
  }

  /// Relit l'état d'un paiement, sans rien demander au provider.
  Future<PaymentAttempt> status(String paymentId) async {
    final response = await _dio.get<dynamic>(ApiConstants.payment(paymentId));

    return PaymentAttemptModel.fromJson(
      ApiJson.map(_payload(response.data)),
    ).toEntity();
  }

  /// Demande au serveur d'interroger le provider maintenant.
  ///
  /// Réservé au geste explicite du voyageur (« J'ai payé ») : la boucle
  /// automatique passe par [status], moins coûteuse pour le provider, qui
  /// impose ses propres limites d'appel.
  Future<PaymentAttempt> verify(String paymentId) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.paymentVerify(paymentId),
    );

    return PaymentAttemptModel.fromJson(
      ApiJson.map(_payload(response.data)),
    ).toEntity();
  }

  /// Déballe l'enveloppe `{ "data": … }` quand elle est là.
  ///
  /// Tolère une réponse déjà à plat : une API qui cesserait d'envelopper
  /// ne doit pas vider l'écran de paiement.
  static Object? _payload(Object? body) {
    if (body is Map && body['data'] != null) return body['data'];
    return body;
  }
}
