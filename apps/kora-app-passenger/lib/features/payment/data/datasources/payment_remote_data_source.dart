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
    required String method,
    required String provider,
    required String idempotencyKey,
    String? network,
    String? phone,
    int? walletAmount,
    int? pointsAmount,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.paymentInitiate,
      data: {
        'reservation_uuid': reservationId,
        'method': method,
        // `wallet` n'a pas d'encaisseur : le serveur refuse un provider
        // sur un paiement que le solde règle en entier.
        'provider': ?(provider.isEmpty ? null : provider),
        'idempotency_key': idempotencyKey,
        // Omis quand ils sont nuls : le serveur distingue « pas de
        // Wallet dans ce paiement » de « zéro franc de Wallet ».
        'wallet_amount': ?walletAmount,
        'points_amount': ?pointsAmount,
        // Le réseau et le numéro ne valent que pour un encaisseur qui
        // pousse la demande lui-même. KKiaPay les recueille dans son
        // widget, et la carte n'en a pas : le serveur les refuse alors
        // comme superflus plutôt que de les exiger.
        'mobile_money_network': ?network,
        'phone': ?phone,
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
  ///
  /// [transactionId] vient du widget KKiaPay, qui encaisse côté client et
  /// rend cet identifiant. C'est **le serveur** qui conclut, en
  /// l'interrogeant chez KKiaPay : la réponse du widget ne vaut rien par
  /// elle-même. La liaison est définitive côté serveur — un identifiant
  /// divergent sur un paiement déjà lié est rejeté (422).
  /// Abandonne un paiement encore en vol.
  ///
  /// Sans ça, une demande laissée en attente verrouille son montant :
  /// la clé d'idempotence rend la même demande à chaque essai, et le
  /// voyageur ne peut ni la payer ni en ouvrir une autre.
  Future<PaymentAttempt> cancel(String paymentId) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.paymentCancel(paymentId),
    );

    return PaymentAttemptModel.fromJson(
      ApiJson.map(_payload(response.data)),
    ).toEntity();
  }

  Future<PaymentAttempt> verify(
    String paymentId, {
    String? transactionId,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.paymentVerify(paymentId),
      data: transactionId == null ? null : {'transaction_id': transactionId},
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
