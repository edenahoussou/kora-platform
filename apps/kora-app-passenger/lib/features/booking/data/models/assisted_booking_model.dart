import '../../../../core/network/api_json.dart';
import '../../domain/entities/assisted_booking.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/seat_hold.dart';
import 'seat_hold_model.dart';

/// Couche DATA : le dossier rendu par `POST` et `GET
/// bookings/{uuid}/assistance` (`AssistedBookingResource`).
///
/// Les numéros arrivent à part, sous `channels` : ceux du support central
/// KORA, pas ceux d'une agence. `handling_agency` reste nul tant que le
/// réseau n'a affecté personne. Le tronçon, les sièges et le nombre de
/// voyageurs viennent du `snapshot` figé à l'envoi — ce que l'agence
/// verra aussi.
AssistedBooking assistedBookingFromJson(Map<String, dynamic> json) {
  final reservation = ApiJson.map(json['reservation']);
  final agency = ApiJson.map(json['handling_agency']);
  final channels = ApiJson.map(json['channels']);
  final amount = ApiJson.map(json['amount']);
  final snapshot = ApiJson.map(json['snapshot']);
  final actions = ApiJson.map(json['actions']);

  final seen = <String>{};
  final seats = <String>[];
  for (final seat in ApiJson.list(snapshot['requested_seats'])) {
    final code = ApiJson.string(seat['code']);
    if (code.isNotEmpty && seen.add(code)) seats.add(code);
  }

  final currency = ApiJson.string(amount['currency'], 'XOF');

  return AssistedBooking(
    id: ApiJson.string(json['uuid']),
    status: AssistanceStatus.fromWire(
      ApiJson.stringOrNull(json['status']),
      reservationStatus: ApiJson.stringOrNull(reservation['status']),
    ),
    channel: ApiJson.stringOrNull(json['channel']) == 'call'
        ? AssistanceChannel.call
        : AssistanceChannel.whatsapp,
    reservationId: ApiJson.string(reservation['uuid']),
    reference: ApiJson.string(reservation['reference']),
    agency: agency.isEmpty
        ? null
        : AssistanceAgency(
            id: ApiJson.string(agency['uuid']),
            name: ApiJson.string(agency['name']),
            code: ApiJson.stringOrNull(agency['code']),
            city: ApiJson.stringOrNull(agency['city']),
          ),
    support: AssistanceSupport(
      callPhone: ApiJson.stringOrNull(channels['call_phone']),
      whatsappPhone: ApiJson.stringOrNull(channels['whatsapp_phone']),
      callUrl: ApiJson.stringOrNull(channels['call_url']),
      whatsappUrl: ApiJson.stringOrNull(channels['whatsapp_url']),
    ),
    // Le montant recalculé quand l'agence l'a repris, celui de l'envoi
    // sinon.
    amount: Price(
      ApiJson.intOrNull(amount['current']) ??
          ApiJson.intOrNull(amount['original']) ??
          0,
      currency: _currency(currency),
    ),
    validUntil: _date(json['valid_until']),
    whatsappMessage: ApiJson.stringOrNull(json['whatsapp_message']),
    legs: [
      for (final leg in ApiJson.list(snapshot['legs']))
        AssistanceLeg(
          origin: ApiJson.string(leg['origin']),
          destination: ApiJson.string(leg['destination']),
          departsAt: _date(leg['departure_at']),
        ),
    ],
    seatLabels: seats,
    passengerCount: ApiJson.intOrNull(snapshot['passenger_count']) ?? 1,
    canResumeOnline: actions['resume_online'] == true,
  );
}

/// Couche DATA : la réservation reprise par `POST
/// bookings/{uuid}/assistance/resume-online`.
///
/// Pas la forme du blocage ordinaire : `amount` et `seats` au lieu de
/// `total_amount` et `segments`.
SeatHold resumedHoldFromJson(Map<String, dynamic> json) {
  final seen = <String>{};
  final seats = <String>[];
  for (final seat in ApiJson.list(json['seats'])) {
    final code = ApiJson.string(seat['seat_code']);
    if (code.isNotEmpty && seen.add(code)) seats.add(code);
  }

  return SeatHold(
    id: ApiJson.string(json['uuid']),
    expiresAt: parseExpiry(ApiJson.stringOrNull(json['expires_at'])),
    seatLabels: seats,
    total: Price(
      ApiJson.intOrNull(json['amount']) ?? 0,
      currency: _currency(ApiJson.string(json['currency'], 'XOF')),
    ),
  );
}

/// La trace locale, lue puis écrite en JSON dans les préférences.
AssistanceRecord recordFromJson(Map<String, dynamic> json) {
  return AssistanceRecord(
    reservationId: ApiJson.string(json['reservation_uuid']),
    reference: ApiJson.string(json['reference']),
    sentAt: _date(json['sent_at']) ?? DateTime.now(),
    origin: ApiJson.string(json['origin']),
    destination: ApiJson.string(json['destination']),
    departsAt: _date(json['departs_at']),
    agencyName: ApiJson.stringOrNull(json['agency_name']),
    seats: [
      for (final seat in ApiJson.list(json['seats']))
        AssistanceSeat(
          passengerIndex: ApiJson.intOrNull(seat['passenger_index']) ?? 0,
          legOrder: ApiJson.intOrNull(seat['leg_order']) ?? 1,
          seatId: ApiJson.string(seat['seat_uuid']),
          label: ApiJson.string(seat['seat_code']),
        ),
    ],
  );
}

Map<String, dynamic> recordToJson(AssistanceRecord record) {
  return {
    'reservation_uuid': record.reservationId,
    'reference': record.reference,
    'sent_at': record.sentAt.toUtc().toIso8601String(),
    'origin': record.origin,
    'destination': record.destination,
    'departs_at': ?record.departsAt?.toUtc().toIso8601String(),
    'agency_name': ?record.agencyName,
    'seats': [
      for (final seat in record.seats)
        {
          'passenger_index': seat.passengerIndex,
          'leg_order': seat.legOrder,
          'seat_uuid': seat.seatId,
          'seat_code': seat.label,
        },
    ],
  };
}

String _currency(String currency) =>
    currency.toUpperCase() == 'XOF' ? 'FCFA' : currency;

DateTime? _date(Object? raw) {
  final text = ApiJson.stringOrNull(raw);
  return text == null ? null : DateTime.tryParse(text)?.toLocal();
}
