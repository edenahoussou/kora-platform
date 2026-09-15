import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../models/manifeste_dtos.dart';

class ManifesteFetchResult {
  const ManifesteFetchResult(this.passengers, this.manifestVersion);

  final List<Passenger> passengers;
  final String? manifestVersion;
}

abstract class ManifesteRemoteDataSource {
  /// `per_page=200` (plafond serveur, `MAX_PER_PAGE` — voir
  /// `OpsDepartureController`) : un manifeste tient largement sur un bus,
  /// une seule page suffit toujours en pratique. Pas de recherche envoyée
  /// au serveur ici — la recherche manuelle (code court) filtre le
  /// manifeste déjà en cache, pour fonctionner aussi hors ligne.
  Future<ManifesteFetchResult> fetch(String departureUuid);
}

class ManifesteRemoteDataSourceImpl implements ManifesteRemoteDataSource {
  ManifesteRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<ManifesteFetchResult> fetch(String departureUuid) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.opsDepartureManifest(departureUuid),
      queryParameters: const <String, dynamic>{'per_page': 200},
    );

    return ManifesteFetchResult(
      ManifesteDtos.passengers(response.data),
      ManifesteDtos.manifestVersion(response.data),
    );
  }
}
