import '../../../../core/network/api_json.dart';
import '../../domain/entities/group_ticket.dart';

/// La copie d'un billet gardée sur le téléphone.
///
/// Forme à plat, et non celle du serveur : c'est un format à nous, relu
/// par nous, et y recopier `passenger` et `segments` entiers n'apporterait
/// rien à l'écran des QR.
Map<String, dynamic> localGroupTicketToJson(GroupTicket ticket) {
  return {
    'uuid': ticket.id,
    'short_code': ticket.shortCode,
    'status': ticket.status.name,
    'qr_code': ticket.qrCode,
    'passenger_name': ticket.passengerName,
    'seat_code': ticket.seatCode,
  };
}

/// Relue telle quelle, et marquée comme venant du téléphone.
GroupTicket localGroupTicketFromJson(Map<String, dynamic> json) {
  return GroupTicket(
    id: ApiJson.string(json['uuid']),
    shortCode: ApiJson.string(json['short_code']),
    status: GroupTicketStatus.fromWire(ApiJson.string(json['status'])),
    qrCode: ApiJson.string(json['qr_code']),
    passengerName: ApiJson.string(json['passenger_name']),
    seatCode: ApiJson.string(json['seat_code']),
    isStale: true,
  );
}

/// Couche DATA : un élément de `GET bookings/{uuid}/tickets`.
class GroupTicketModel {
  const GroupTicketModel._(this._json);

  factory GroupTicketModel.fromJson(Map<String, dynamic> json) =>
      GroupTicketModel._(json);

  final Map<String, dynamic> _json;

  GroupTicket toEntity() {
    return GroupTicket(
      id: ApiJson.string(_json['uuid']),
      shortCode: ApiJson.string(_json['short_code']),
      status: GroupTicketStatus.fromWire(ApiJson.string(_json['status'])),
      qrCode: ApiJson.string(_json['qr_code']),
      passengerName: _passengerName(),
      seatCode: _seatCode(),
    );
  }

  /// `Aïcha HOUNKPÈ`, ou vide si le serveur n'a rattaché aucun voyageur.
  String _passengerName() {
    final passenger = _json['passenger'];
    if (passenger is! Map) return '';

    final parts = [
      ApiJson.string(passenger['first_name']),
      ApiJson.string(passenger['last_name']),
    ].where((part) => part.isNotEmpty);

    return parts.join(' ');
  }

  /// Le siège du **premier** tronçon.
  ///
  /// Un billet peut en porter plusieurs sur un trajet à correspondance ; la
  /// liste du groupe n'a la place que d'un seul, et c'est celui de départ
  /// qui aide à se repérer à l'embarquement.
  String _seatCode() {
    final segments = ApiJson.list(_json['segments']);
    if (segments.isEmpty) return '';

    return ApiJson.string(segments.first['seat_code']);
  }
}
