import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/depart.dart';
import '../models/departure_dtos.dart';

abstract class DepartureRemoteDataSource {
  /// `GET /ops/departures` — départs affectés à l'utilisateur connecté
  /// (voir `CrewDepartureScope` côté backend, filtrage déjà fait côté
  /// serveur). [date] au format `YYYY-MM-DD`.
  Future<List<Depart>> list({String? date, String? status});

  /// `GET /ops/departures/{uuid}` — détail complet (arrêts, équipage,
  /// `manifestVersion`).
  Future<Depart> show(String departureUuid);
}

class DepartureRemoteDataSourceImpl implements DepartureRemoteDataSource {
  DepartureRemoteDataSourceImpl(this._dio);

  final Dio _dio;

  @override
  Future<List<Depart>> list({String? date, String? status}) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.opsDepartures,
      queryParameters: <String, dynamic>{
        'per_page': 200,
        if (date != null) 'date': date,
        if (status != null) 'status': status,
      },
    );
    return DepartureDtos.list(response.data);
  }

  @override
  Future<Depart> show(String departureUuid) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.opsDepartureShow(departureUuid),
    );
    return DepartureDtos.fromShow(response.data);
  }
}
