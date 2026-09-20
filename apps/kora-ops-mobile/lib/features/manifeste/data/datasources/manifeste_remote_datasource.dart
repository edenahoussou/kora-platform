import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../models/manifeste_dtos.dart';

class ManifesteFetchResult {
  const ManifesteFetchResult(
    this.passengers,
    this.manifestVersion, {
    this.isInternational,
  });

  final List<Passenger> passengers;
  final String? manifestVersion;

  /// Indicateur `international` de la réponse (MEZ-94) — `null` si le
  /// serveur ne le renvoie pas.
  final bool? isInternational;
}

/// Résultat de `POST /ops/sync/manifest-versions` pour un départ — voir
/// `CheckManifestVersionsAction` côté backend.
class ManifestVersionCheck {
  const ManifestVersionCheck({
    required this.currentVersion,
    required this.stale,
    required this.accessible,
  });

  final String? currentVersion;
  final bool stale;

  /// `false` si ce départ n'est plus affecté/accessible à l'utilisateur
  /// connecté (masqué comme "inconnu" côté serveur, sans révéler s'il
  /// existe — même logique que `viewAsCrew`).
  final bool accessible;
}

abstract class ManifesteRemoteDataSource {
  /// `per_page=200` (plafond serveur, `MAX_PER_PAGE` — voir
  /// `OpsDepartureController`) : un manifeste tient largement sur un bus,
  /// une seule page suffit toujours en pratique. Pas de recherche envoyée
  /// au serveur ici — la recherche manuelle (code court) filtre le
  /// manifeste déjà en cache, pour fonctionner aussi hors ligne.
  Future<ManifesteFetchResult> fetch(String departureUuid);

  /// Vérification légère de péremption (MEZ-92) — évite de retélécharger
  /// le manifeste complet quand rien n'a changé côté serveur. Un seul
  /// départ à la fois ici (le contrat backend en accepte jusqu'à 100,
  /// mais un seul terminal ne gère qu'un départ sélectionné à la fois).
  Future<ManifestVersionCheck> checkVersion(
    String departureUuid,
    String? knownVersion,
  );
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
      isInternational: ManifesteDtos.isInternational(response.data),
    );
  }

  @override
  Future<ManifestVersionCheck> checkVersion(
    String departureUuid,
    String? knownVersion,
  ) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.opsSyncManifestVersions,
      data: <String, dynamic>{
        'departures': <Map<String, dynamic>>[
          <String, dynamic>{
            'departure_uuid': departureUuid,
            'known_version': knownVersion,
          },
        ],
      },
    );

    final Object? body = response.data;
    final Object? rawData = body is Map ? body['data'] : null;
    final List<dynamic> rows = rawData is List ? rawData : const <dynamic>[];
    if (rows.isEmpty) {
      return const ManifestVersionCheck(
          currentVersion: null, stale: true, accessible: false);
    }

    final Object? rawRow = rows.first;
    final Map<dynamic, dynamic> row = rawRow is Map ? rawRow : const {};
    return ManifestVersionCheck(
      currentVersion: row['currentVersion'] as String?,
      stale: row['stale'] as bool? ?? true,
      accessible: row['accessible'] as bool? ?? false,
    );
  }
}
