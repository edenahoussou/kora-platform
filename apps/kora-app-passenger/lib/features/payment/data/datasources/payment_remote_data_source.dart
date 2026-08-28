import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/payment_catalog.dart';
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

  /// Déballe l'enveloppe `{ "data": … }` quand elle est là.
  ///
  /// Tolère une réponse déjà à plat : une API qui cesserait d'envelopper
  /// ne doit pas vider l'écran de paiement.
  static Object? _payload(Object? body) {
    if (body is Map && body['data'] != null) return body['data'];
    return body;
  }
}
