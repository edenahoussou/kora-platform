import '../../../../core/network/api_json.dart';
import '../../domain/entities/line_stop.dart';

/// Un arrêt tel que `GET bus/lines/{uuid}` le rend, dans `stops`.
///
/// ```json
/// { "uuid": "...", "name": "Bohicon", "city": "Bohicon",
///   "country": "BJ", "stop_order": 2, "km_from_origin": 128.0 }
/// ```
class LineStopModel {
  const LineStopModel(this._json);

  final Map<String, dynamic> _json;

  LineStop toEntity() {
    final km = _json['km_from_origin'];

    return LineStop(
      order: ApiJson.intOrNull(_json['stop_order']) ?? 0,
      name: ApiJson.string(_json['name']),
      city: ApiJson.stringOrNull(_json['city']),
      // Le serveur rend un flottant ; un entier passerait aussi, et une
      // chaîne le jour où la sérialisation change d'avis.
      kmFromOrigin: km is num ? km.toDouble() : double.tryParse('$km'),
    );
  }
}
