import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/company_account.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/group_payer.dart';
import '../../domain/entities/group_quote.dart';
import '../../domain/entities/group_ticket.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/quote.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/seat_hold.dart';
import '../models/company_account_model.dart';
import '../models/group_quote_model.dart';
import '../models/group_ticket_model.dart';
import '../models/quote_model.dart';
import '../models/seat_hold_model.dart';

/// Couche DATA : devis, blocage et libération des sièges.
///
/// Les trois endpoints exigent une session (`auth:sanctum`) — contrairement
/// au catalogue, qui est public. Comme les autres data sources, celui-ci
/// laisse remonter les `DioException` : c'est le repository qui les traduit
/// en `Failure`.
class ReservationRemoteDataSource {
  const ReservationRemoteDataSource(this._dio);

  final Dio _dio;

  Future<Quote> quote({
    required Departure departure,
    required int passengers,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.busQuotes,
      data: {
        'departure_uuid': departure.id,
        'origin_stop_uuid': departure.originStopId,
        'destination_stop_uuid': departure.destinationStopId,
        'passenger_count': passengers,
      },
    );

    return QuoteModel.fromJson(ApiJson.map(_payload(response.data))).toEntity();
  }

  /// Le devis d'un groupe, avec sa remise.
  ///
  /// Passe par `bus/itinerary-quotes` et non par `bus/quotes` : c'est le
  /// seul point d'entrée qui applique la remise de groupe et la détaille
  /// au passager. Un seul tronçon ici — les correspondances viendront avec
  /// le parcours multi-lignes, que ce point d'entrée sait déjà traiter.
  Future<GroupQuote> groupQuote({
    required Departure departure,
    required int passengers,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.itineraryQuotes,
      data: {
        'passenger_count': passengers,
        'legs': [
          {
            'leg_order': 1,
            'departure_uuid': departure.id,
            'origin_stop_uuid': departure.originStopId,
            'destination_stop_uuid': departure.destinationStopId,
          },
        ],
      },
    );

    // Réponse à plat, sans enveloppe `data`, contrairement au reste.
    return GroupQuoteModel.fromJson(
      ApiJson.map(response.data),
    ).toEntity(passengerCount: passengers);
  }

  Future<SeatHold> hold({
    required Departure departure,
    required Quote quote,
    required List<Seat> seats,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.busHolds,
      data: {
        'departure_uuid': departure.id,
        'origin_stop_uuid': departure.originStopId,
        'destination_stop_uuid': departure.destinationStopId,
        'price_quote_uuid': quote.id,
        // `passenger_index` suit l'ordre de sélection : le premier siège
        // choisi ira au premier voyageur du formulaire suivant.
        'seats': [
          for (final (index, seat) in seats.indexed)
            {'seat_uuid': seat.id, 'passenger_index': index},
        ],
      },
    );

    return SeatHoldModel.fromJson(
      ApiJson.map(_payload(response.data)),
    ).toEntity();
  }

  /// Enregistre les identités et rend la réservation rafraîchie.
  ///
  /// Le serveur renvoie `status` et `expires_at` à jour : c'est lui qui
  /// fait autorité sur le temps restant, et cet appel est l'occasion de
  /// resynchroniser le décompte.
  Future<SeatHold> savePassengers({
    required String reservationId,
    required List<Passenger> passengers,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.bookingPassengers(reservationId),
      data: {
        'passengers': [
          for (final passenger in passengers) _passengerJson(passenger),
        ],
      },
    );

    return SeatHoldModel.fromJson(
      ApiJson.map(_payload(response.data)),
    ).toEntity();
  }

  /// Retient d'un seul coup tous les sièges d'un groupe.
  ///
  /// **Atomique côté serveur** : soit les N sièges partent ensemble, soit
  /// aucun. C'est ce qui distingue ce point d'entrée de `bus/holds` — sur
  /// un groupe de dix, retenir neuf sièges puis échouer sur le dixième
  /// laisserait le voyageur avec une réservation inutilisable et neuf
  /// places retirées de la vente.
  ///
  /// L'ordre des sièges fait foi : le voyageur d'indice `i` occupe le
  /// i-ième siège choisi, et c'est ce même ordre que suivra le formulaire
  /// des identités.
  Future<SeatHold> holdGroup({
    required GroupQuote quote,
    required List<Seat> seats,
    required GroupPayer payer,
    required String idempotencyKey,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.itineraryHolds,
      data: {
        'itinerary_quote_uuid': quote.id,
        'idempotency_key': idempotencyKey,
        // Tu par défaut : le serveur retombe alors sur le titulaire.
        if (payer.isCompany) 'payer': payer.toJson(),
        'passengers': [
          for (var index = 0; index < seats.length; index++)
            {
              'passenger_index': index,
              'seat_assignments': [
                {'leg_order': 1, 'seat_uuid': seats[index].id},
              ],
            },
        ],
      },
    );

    return SeatHoldModel.fromJson(
      ApiJson.map(_payload(response.data)),
    ).toEntity();
  }

  /// Les comptes entreprise dont le voyageur est membre actif.
  ///
  /// Rangé ici plutôt que dans une couche à part : c'est un seul point
  /// d'entrée, et il ne sert qu'à désigner le payeur d'une réservation.
  /// Une liste vide est un **succès** — la plupart des voyageurs
  /// n'appartiennent à aucune entreprise.
  Future<List<CompanyAccount>> companyAccounts() async {
    final response = await _dio.get<dynamic>(ApiConstants.companyAccounts);

    return [
      for (final json in ApiJson.list(_payload(response.data)))
        CompanyAccountModel.fromJson(json).toEntity(),
    ];
  }

  /// Les billets émis d'une réservation, un par voyageur.
  ///
  /// Vide tant que le paiement n'est pas confirmé : l'émission suit le
  /// règlement, et une liste vide n'est donc pas une erreur mais une
  /// attente.
  Future<List<GroupTicket>> bookingTickets(String reservationId) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.bookingTickets(reservationId),
    );

    return [
      for (final json in ApiJson.list(_payload(response.data)))
        GroupTicketModel.fromJson(json).toEntity(),
    ];
  }

  Future<void> release(String reservationId) =>
      _dio.delete<dynamic>(ApiConstants.hold(reservationId));
}

/// Un voyageur, tel que l'attend `POST bookings/{uuid}/passengers`.
///
/// Les champs du manifeste ne partent que s'ils sont renseignés : sur une
/// liaison intérieure ils n'existent pas, et envoyer `null` là où le
/// serveur accepte l'absence ferait passer un formulaire vide pour une
/// saisie incomplète.
Map<String, dynamic> _passengerJson(Passenger passenger) {
  return <String, dynamic>{
    'first_name': passenger.firstName,
    'last_name': passenger.lastName,
    'seat_uuid': passenger.seat.id,
    if (passenger.phone.isNotEmpty) 'phone': passenger.phone,
    if (passenger.email != null) 'email': passenger.email,
    if (passenger.documentType != null)
      'document_type': passenger.documentType!.wire,
    if (passenger.documentNumber != null)
      'document_number': passenger.documentNumber,
    if (passenger.nationality != null) 'nationality': passenger.nationality,
    if (passenger.birthDate != null)
      // `date_format:Y-m-d` : une date seule, sans heure ni fuseau — le
      // serveur refuse un ISO complet.
      'birth_date': _isoDay(passenger.birthDate!),
    // Déclaration de vaccination contre la fièvre jaune, demandée à la
    // revue Kora du 29 août 2026.
    //
    // **Le serveur la jette aujourd'hui** : `StorePassengersRequest`
    // recopie une liste fermée de champs, et celui-ci n'en fait pas
    // partie. On l'envoie quand même — le jour où la colonne existe, la
    // valeur arrive sans qu'il faille repasser par le magasin
    // d'applications. Envoyée toujours, jamais conditionnée : « non
    // vacciné » est une réponse, pas une absence de réponse.
    'yellow_fever_vaccinated': passenger.yellowFeverVaccinated,
  };
}

String _isoDay(DateTime date) {
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}

/// Déballe l'enveloppe `{ "data": … }` quand elle est là.
Object? _payload(Object? body) {
  if (body is Map && body['data'] != null) return body['data'];
  return body;
}
