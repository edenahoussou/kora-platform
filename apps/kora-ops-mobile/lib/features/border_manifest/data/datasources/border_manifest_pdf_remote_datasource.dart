import 'dart:convert';

import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/exceptions/app_exceptions.dart';

/// Récupère le manifeste frontière PDF (`GET /ops/departures/{uuid}/
/// manifest/pdf`) — mode téléphone uniquement. Voir, côté backend,
/// `OpsDepartureController::manifestPdf` : jamais de montant dans ce PDF.
abstract class BorderManifestPdfRemoteDataSource {
  Future<List<int>> fetch({required String departureUuid});
}

/// Le serveur a refusé d'émettre le manifeste : au moins une information
/// obligatoire manque (422 `manifest_incomplete`). [details] liste les
/// passagers concernés, déjà formulés pour l'affichage.
class BorderManifestIncompleteException extends ApiException {
  const BorderManifestIncompleteException(
    super.message, {
    this.details = const <String>[],
  }) : super(statusCode: 422);

  final List<String> details;
}

class BorderManifestPdfRemoteDataSourceImpl
    implements BorderManifestPdfRemoteDataSource {
  BorderManifestPdfRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<List<int>> fetch({required String departureUuid}) async {
    try {
      final Response<List<int>> response = await _dio.get<List<int>>(
        ApiConstants.opsDepartureManifestPdf(departureUuid),
        options: Options(responseType: ResponseType.bytes),
      );

      final List<int>? bytes = response.data;
      if (bytes == null || bytes.isEmpty) {
        throw const ServerException('Manifeste frontière PDF vide.');
      }
      return bytes;
    } on DioException catch (error) {
      // Corps d'erreur JSON reçu en bytes bruts (`responseType.bytes`) :
      // décodage manuel, comme `BoardingPdfRemoteDataSourceImpl`.
      final Map<String, dynamic>? apiError = _errorBody(error.response?.data);
      final String? message = apiError?['message'] as String?;
      final String? code = apiError?['code'] as String?;
      final int? status = error.response?.statusCode;

      throw switch (status) {
        401 || 403 => UnauthorizedException(message ?? 'Accès refusé.'),
        422 when code == 'manifest_incomplete' =>
          BorderManifestIncompleteException(
            message ?? 'Manifeste frontière incomplet.',
            details: _detailsOf(apiError?['passengers']),
          ),
        422 => ApiException(
            message ?? 'Manifeste frontière impossible pour ce départ.',
            statusCode: 422,
          ),
        404 => ApiException(
            message ?? 'Terminal ou départ introuvable.',
            statusCode: 404,
          ),
        null => const NetworkException(),
        _ => ServerException(
            message ?? 'Le manifeste frontière n\'a pas pu être généré.',
          ),
      };
    }
  }

  Map<String, dynamic>? _errorBody(Object? rawBody) {
    try {
      final dynamic body =
          rawBody is List<int> ? jsonDecode(utf8.decode(rawBody)) : rawBody;
      if (body is Map<String, dynamic>) {
        final dynamic error = body['error'];
        if (error is Map<String, dynamic>) return error;
      }
    } on Object {
      // Corps non-JSON (page d'erreur HTML d'un proxy) : message générique.
    }
    return null;
  }

  /// `[{name, seat, missing: [...]}]` → « Awa Traoré (1A) : manque
  /// nationalité, n° de document ».
  List<String> _detailsOf(Object? passengers) {
    if (passengers is! List) return const <String>[];

    return passengers.whereType<Map<dynamic, dynamic>>().map((row) {
      final Object? missing = row['missing'];
      final String fields = missing is List ? missing.join(', ') : '';
      return '${row['name']} (${row['seat']})'
          '${fields.isEmpty ? '' : ' : manque $fields'}';
    }).toList();
  }
}
