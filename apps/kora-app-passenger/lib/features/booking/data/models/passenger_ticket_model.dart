import '../../../../core/network/api_json.dart';
import '../../domain/entities/ticket.dart';

/// Un billet tel que `GET /api/v1/tickets` le rend — un par voyageur.
///
/// ```json
/// { "uuid": "…", "short_code": "K5F414H5", "qr_code": "base64url.sig",
///   "status": "confirmed", "reservation_uuid": "…", "amount": 25200,
///   "origin": { "city": "Cotonou", "city_code": "CTN", "stop_name": "…" },
///   "destination": { … },
///   "departure": { "uuid": "…", "departs_at": "…", "arrives_at": "…",
///                  "timezone": "Africa/Porto-Novo", "line_code": "L1" },
///   "segments": [ { "leg_order": 1, "direction": "outbound",
///                   "origin_city": "Cotonou", "destination_city": "Bohicon",
///                   "seat_code": "4A", "departure_at": "…",
///                   "arrival_at": "…" } ],
///   "seat": { "code": "4A" } }
/// ```
///
/// Les heures sont celles des **arrêts achetés**, avec leur décalage :
/// le serveur ne rend plus l'horaire du départ de la ligne, qui ne
/// voulait rien dire pour qui monte en route.
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

  /// `Awa KORA`, ou vide si le serveur n'a rattaché aucun voyageur.
  String get passengerName {
    final passenger = ApiJson.map(_json['passenger']);
    final parts = [
      ApiJson.string(passenger['first_name']),
      ApiJson.string(passenger['last_name']),
    ].where((part) => part.isNotEmpty);

    return parts.join(' ');
  }

  /// L'heure de départ, ramenée à l'heure du téléphone. `null` quand le
  /// serveur ne la donne pas.
  DateTime? get departsAt =>
      DateTime.tryParse(ApiJson.string(departure['departs_at']))?.toLocal();

  /// L'arrivée au dernier arrêt acheté de l'aller.
  DateTime? get arrivesAt =>
      DateTime.tryParse(ApiJson.string(departure['arrives_at']))?.toLocal();

  /// Les bus du voyage, dans l'ordre du serveur.
  ///
  /// Vide sur un billet rendu par une version antérieure du serveur : la
  /// carte retombe alors sur le trajet d'un bout à l'autre, comme avant.
  List<TicketLeg> get legs => [
    for (final raw in ApiJson.list(_json['segments']))
      TicketLeg(
        order: ApiJson.intOrNull(raw['leg_order']) ?? 0,
        origin: ApiJson.string(raw['origin_city']),
        destination: ApiJson.string(raw['destination_city']),
        seatCode: ApiJson.string(raw['seat_code']),
        departsAt: DateTime.tryParse(
          ApiJson.string(raw['departure_at']),
        )?.toLocal(),
        arrivesAt: DateTime.tryParse(
          ApiJson.string(raw['arrival_at']),
        )?.toLocal(),
        isReturn: ApiJson.string(raw['direction']) == 'inbound',
      ),
  ]..sort((a, b) => a.order.compareTo(b.order));
}
