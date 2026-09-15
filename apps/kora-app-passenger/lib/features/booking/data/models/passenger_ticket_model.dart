import '../../../../core/network/api_json.dart';

/// Un billet tel que `GET /api/v1/tickets` le rend — un par voyageur.
///
/// ```json
/// { "uuid": "…", "short_code": "K5F414H5", "qr_code": "base64url.sig",
///   "status": "confirmed", "reservation_uuid": "…", "amount": 25200,
///   "origin": { "city": "Cotonou", "city_code": "CTN", "stop_name": "…" },
///   "destination": { … },
///   "departure": { "uuid": "…", "departs_at": "…", "line_code": "L1" },
///   "seat": { "code": "4A" } }
/// ```
///
/// Le statut arrive déjà traduit dans le vocabulaire de l'app
/// (`confirmed`, `completed`, `cancelled`) : c'est `PassengerTicketResource`
/// qui s'en charge côté serveur.
class PassengerTicketModel {
  const PassengerTicketModel(this._json);

  final Map<String, dynamic> _json;

  String get uuid => ApiJson.string(_json['uuid']);

  String get shortCode => ApiJson.string(_json['short_code']);

  /// La charge signée à encoder dans le QR — jamais la référence.
  String get qrCode => ApiJson.string(_json['qr_code']);

  String get status => ApiJson.string(_json['status']);

  String? get reservationId => ApiJson.stringOrNull(_json['reservation_uuid']);

  /// Le montant de la **réservation**, pas celui du seul billet : tous les
  /// billets d'un groupe portent le même.
  int get amount => ApiJson.intOrNull(_json['amount']) ?? 0;

  Map<String, dynamic> get origin => ApiJson.map(_json['origin']);

  Map<String, dynamic> get destination => ApiJson.map(_json['destination']);

  Map<String, dynamic> get departure => ApiJson.map(_json['departure']);

  String get seatCode => ApiJson.string(ApiJson.map(_json['seat'])['code']);

  /// L'heure de départ, ramenée à l'heure du téléphone. `null` quand le
  /// serveur ne la donne pas.
  DateTime? get departsAt =>
      DateTime.tryParse(ApiJson.string(departure['departs_at']))?.toLocal();
}
