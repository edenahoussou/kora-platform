import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/line.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/ticket.dart';

/// Ce que l'app retient des voyages qu'elle a elle-même achetés.
///
/// **L'API ne sait pas lister les réservations d'un voyageur.** Elle n'a
/// que `GET bookings/{uuid}`, qui exige de connaître l'identifiant — et
/// `GET bookings/{uuid}/tickets` pour les billets émis. Sans liste côté
/// serveur, l'onglet Voyages n'avait rien à montrer et affichait un jeu de
/// démonstration.
///
/// Ce journal comble le trou par le seul bout accessible : l'app sait ce
/// qu'elle vient de payer, puisque c'est elle qui a bloqué les sièges. Elle
/// note l'identifiant et de quoi réafficher le trajet, puis redemande au
/// serveur l'état de chacun.
///
/// **Ses limites sont réelles et assumées** : un billet acheté au guichet,
/// sur un autre téléphone, ou avant l'installation, n'y figure pas. C'est
/// un pis-aller jusqu'à ce que le serveur expose `GET /bookings` — le jour
/// où il le fera, ce fichier disparaît d'un bloc.
class BookedTripsStore {
  const BookedTripsStore();

  /// Une clé par compte : deux voyageurs qui se succèdent sur le même
  /// téléphone ne doivent pas hériter des voyages l'un de l'autre.
  static String _keyFor(String accountId) => 'kora.booked_trips.$accountId';

  /// Au-delà, on oublie les plus anciens. Le journal sert à retrouver ses
  /// voyages, pas à tenir une comptabilité.
  static const int _maximum = 60;

  /// Ajoute un voyage payé, ou rafraîchit celui qu'on connaissait déjà.
  Future<void> remember(
    String accountId, {
    required String reservationId,
    required Departure departure,
    required String seatLabel,
    required Price total,
  }) async {
    final preferences = await SharedPreferences.getInstance();
    final trips = _read(preferences, accountId)
      ..removeWhere((trip) => trip['reservation_id'] == reservationId);

    trips.insert(0, {
      'reservation_id': reservationId,
      'origin': departure.line.origin.name,
      'destination': departure.line.destination.name,
      'departs_at': departure.departsAt.toIso8601String(),
      'seat': seatLabel,
      'total': total.amount,
      'line_code': departure.line.code,
      'vehicle': departure.busLabel,
    });

    if (trips.length > _maximum) trips.removeRange(_maximum, trips.length);

    await preferences.setString(_keyFor(accountId), jsonEncode(trips));
  }

  /// Les voyages retenus, du plus récemment acheté au plus ancien.
  Future<List<BookedTrip>> all(String accountId) async {
    final preferences = await SharedPreferences.getInstance();

    return [
      for (final trip in _read(preferences, accountId))
        ?BookedTrip.fromJson(trip),
    ];
  }

  /// Oublie tout d'un compte — à la déconnexion.
  Future<void> forget(String accountId) async {
    final preferences = await SharedPreferences.getInstance();
    await preferences.remove(_keyFor(accountId));
  }

  List<Map<String, dynamic>> _read(
    SharedPreferences preferences,
    String accountId,
  ) {
    final raw = preferences.getString(_keyFor(accountId));
    if (raw == null || raw.isEmpty) return [];

    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return [];

      return [
        for (final item in decoded)
          if (item is Map) Map<String, dynamic>.from(item),
      ];
    } on FormatException {
      // Un journal illisible ne vaut pas mieux qu'un journal vide, et il
      // ne doit surtout pas empêcher l'écran de s'ouvrir.
      return [];
    }
  }
}

/// Un voyage retenu localement, avant que le serveur n'en donne l'état.
class BookedTrip {
  const BookedTrip({
    required this.reservationId,
    required this.departure,
    required this.seat,
    required this.total,
  });

  /// `null` quand l'entrée est inexploitable — un journal écrit par une
  /// version plus ancienne, une date corrompue. On l'écarte plutôt que de
  /// faire tomber tout l'écran.
  static BookedTrip? fromJson(Map<String, dynamic> json) {
    final id = json['reservation_id'];
    final departsAt = DateTime.tryParse('${json['departs_at']}');
    if (id is! String || id.isEmpty || departsAt == null) return null;

    final origin = '${json['origin']}';
    final destination = '${json['destination']}';

    return BookedTrip(
      reservationId: id,
      departure: Departure(
        // Le départ n'est pas relu du serveur : `bookings/{uuid}` n'en
        // rend que l'identifiant, sans date ni ligne. Ce qu'on montre est
        // donc ce que le voyageur a vu au moment d'acheter.
        id: '${json['departure_id'] ?? ''}',
        line: Line(
          id: '',
          code: json['line_code'] as String?,
          // `City.named` : une ville reconstruite depuis un billet n'a
          // pas d'arrêt physique, et ne peut donc pas servir de départ à
          // une nouvelle recherche. C'est exactement ce qu'elle est ici.
          origin: City.named(origin),
          destination: City.named(destination),
          duration: Duration.zero,
          distanceKm: 0,
        ),
        departsAt: departsAt,
        comfort: BusComfort.confort,
        price: Price(json['total'] is int ? json['total'] as int : 0),
        seatsTotal: 0,
        seatsAvailable: 0,
        busLabel: json['vehicle'] as String?,
      ),
      seat: Seat(
        id: '',
        label: '${json['seat']}',
        // Le plan des sièges n'est pas rejoué ici : seules l'étiquette et
        // la vente comptent pour l'affichage d'un billet.
        row: 0,
        column: 0,
        status: SeatStatus.occupied,
      ),
      total: Price(json['total'] is int ? json['total'] as int : 0),
    );
  }

  final String reservationId;
  final Departure departure;
  final Seat seat;
  final Price total;

  /// Le billet tel que l'onglet Voyages l'affiche, une fois le statut
  /// connu du serveur.
  Ticket toTicket(TicketStatus status) => Ticket(
    reference: reservationId,
    departure: departure,
    seat: seat,
    status: status,
  );
}
