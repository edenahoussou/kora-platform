import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/app_update_info.dart';
import '../../domain/entities/app_version.dart';
import '../models/app_update_dtos.dart';

/// Couche DATA : vérification de mise à jour au lancement (MEZ-150) —
/// `GET /api/v1/apps/ops-mobile/update`, publique (aucune session requise :
/// une version obsolète doit être bloquée avant même la connexion).
abstract class AppUpdateRemoteDataSource {
  /// `null` si aucune version n'est publiée côté serveur.
  Future<AppUpdateInfo?> check(AppVersion? installed);
}

class AppUpdateRemoteDataSourceImpl implements AppUpdateRemoteDataSource {
  AppUpdateRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<AppUpdateInfo?> check(AppVersion? installed) async {
    final Response<dynamic> response = await _dio.get<dynamic>(
      ApiConstants.appUpdateCheck,
      queryParameters: <String, dynamic>{
        if (installed != null) 'version': installed.semver,
        if (installed?.build != null) 'build': installed!.build,
      },
    );

    return AppUpdateDtos.fromCheckResponse(response.data);
  }
}
