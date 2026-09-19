import 'dart:convert';

import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/exceptions/app_exceptions.dart';

/// Récupère le PDF de confirmation d'embarquement — mode téléphone
/// uniquement (pas d'imprimante thermique). Voir ticket "Supporter le
/// scan par caméra téléphone en alternative au P501" et, côté backend,
/// `OpsBoardingController::pdf` (jamais de montant/prix dans ce PDF).
abstract class BoardingPdfRemoteDataSource {
  Future<List<int>> fetch({
    required String departureUuid,
    required String ticketUuid,
  });
}

class BoardingPdfRemoteDataSourceImpl implements BoardingPdfRemoteDataSource {
  BoardingPdfRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<List<int>> fetch({
    required String departureUuid,
    required String ticketUuid,
  }) async {
    try {
      final Response<List<int>> response = await _dio.get<List<int>>(
        ApiConstants.opsBoardingTicketPdf(departureUuid, ticketUuid),
        options: Options(responseType: ResponseType.bytes),
      );

      final List<int>? bytes = response.data;
      if (bytes == null || bytes.isEmpty) {
        throw const ServerException('PDF de confirmation vide.');
      }
      return bytes;
    } on DioException catch (error) {
      // La requête demande des bytes (`responseType.bytes`), donc un
      // corps d'erreur JSON ({ "error": { "code", "message" } }, voir
      // OpsBoardingController::pdf) arrive lui aussi en bytes bruts —
      // décodage manuel, comme BoardingRemoteDataSourceImpl le fait déjà
      // pour ses propres codes métier (404 `qr_unknown`).
      final String? message = _errorMessage(error.response?.data);
      final int? status = error.response?.statusCode;

      throw switch (status) {
        401 || 403 => UnauthorizedException(message ?? 'Accès refusé.'),
        422 => ApiException(
            message ?? 'Ce billet ne correspond pas à ce départ.',
            statusCode: 422,
          ),
        404 => ApiException(
            message ?? 'Terminal ou billet introuvable.',
            statusCode: 404,
          ),
        null => const NetworkException(),
        _ => ServerException(
            message ?? "Le PDF de confirmation n'a pas pu être généré.",
          ),
      };
    }
  }

  String? _errorMessage(Object? rawBody) {
    try {
      final dynamic body =
          rawBody is List<int> ? jsonDecode(utf8.decode(rawBody)) : rawBody;
      if (body is Map<String, dynamic>) {
        final dynamic error = body['error'];
        if (error is Map<String, dynamic>) return error['message'] as String?;
      }
    } on Object {
      // Corps non-JSON (ex. page d'erreur HTML d'un proxy) : message
      // générique ci-dessus, pas d'exception secondaire.
    }
    return null;
  }
}
