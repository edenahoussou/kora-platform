import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/error/exceptions.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/company_account.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/group_payer.dart';
import '../../domain/entities/group_quote.dart';
import '../../domain/entities/group_ticket.dart';
import '../../domain/entities/journey.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/reservation_pricing.dart';
import '../../domain/entities/seat_hold.dart';
import '../models/company_account_model.dart';
import '../models/group_quote_model.dart';
import '../models/group_ticket_model.dart';
import '../models/seat_hold_model.dart';

/// Couche DATA : devis, blocage et libération des sièges.
///
/// Tous ces endpoints exigent une session (`auth:sanctum`) — contrairement
/// au catalogue, qui est public. Comme les autres data sources, celui-ci
/// laisse remonter les `DioException` : c'est le repository qui les traduit
/// en `Failure`.
class ReservationRemoteDataSource {
  const ReservationRemoteDataSource(this._dio);

  final Dio _dio;

  /// Le devis d'un trajet, remise de groupe comprise.
  ///
  /// Seul devis qui reste : MEZ-63 a retiré `POST bus/quotes`, et
  /// `itinerary-quotes` le remplace pour tout le monde — un voyageur seul
  /// est un groupe d'un, que le barème laissera simplement au plein tarif.
  ///
  /// Tout le voyage tient dans **le même devis** : les tronçons de
  /// l'aller puis ceux du retour dans un seul tableau `legs`, `leg_order`
  /// suivant cette liste concaténée et `direction` distinguant les deux
  /// sens. C'est la convention du guichet
  /// (`CounterSaleController::buildItineraryLegs`), et c'est elle qui
  /// permet un seul blocage et un seul paiement — pour les deux sens d'un
  /// aller-retour comme pour les deux bus d'une correspondance.
  Future<GroupQuote> groupQuote({
    required Journey journey,
    required int passengers,
    String? promoCode,
  }) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.itineraryQuotes,
      data: {
        'passenger_count': passengers,
        // MEZ-73 : le code se donne au devis, et seulement là. Le serveur
        // calcule la remise ; un code refusé répond 422 sur `promo_code`.
        'promo_code': ?promoCode,
        'legs': _legsJson(journey),
      },
    );

    // Enveloppée dans `data` comme le reste : `ItineraryController::quote`
    // rend `response()->json(['data' => [...]])`. La lire à plat donnait un
    // devis vide — dont un `itinerary_quote_uuid` absent, que le blocage
    // réclame ensuite (422 « le champ itinerary quote uuid est
    // obligatoire »).
    return GroupQuoteModel.fromJson(
      ApiJson.map(_payload(response.data)),
    ).toEntity(passengerCount: passengers);
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
  /// i-ième siège de chaque tronçon, et c'est ce même ordre que suivra le
  /// formulaire des identités. Tous les tronçons partent dans le même
  /// blocage, chacun sous son `leg_order` — les deux sens d'un
  /// aller-retour comme les deux bus d'une correspondance.
  Future<SeatHold> holdGroup({
    required GroupQuote quote,
    required Journey journey,
    required GroupPayer payer,
    required String idempotencyKey,
  }) async {
    final seatsByLeg = journey.seatsByLeg;

    // Un siège par voyageur sur chaque tronçon, sinon le serveur
    // refuserait le blocage entier — autant le dire ici, où la cause est
    // lisible.
    if (seatsByLeg.length != journey.legs.length ||
        seatsByLeg.any((seats) => seats.length != journey.passengerCount)) {
      throw const ServerException(
        'Chaque voyageur doit avoir un siège sur chaque trajet.',
      );
    }

    final response = await _dio.post<dynamic>(
      ApiConstants.itineraryHolds,
      data: {
        'itinerary_quote_uuid': quote.id,
        'idempotency_key': idempotencyKey,
        // Tu par défaut : le serveur retombe alors sur le titulaire.
        if (payer.isCompany) 'payer': payer.toJson(),
        'passengers': [
          for (var index = 0; index < journey.passengerCount; index++)
            {
              'passenger_index': index,
              // `leg_order` suit l'ordre du devis : les tronçons de
              // l'aller, puis ceux du retour.
              'seat_assignments': [
                for (var leg = 0; leg < seatsByLeg.length; leg++)
                  {
                    'leg_order': leg + 1,
                    'seat_uuid': seatsByLeg[leg][index].id,
                  },
              ],
            },
        ],
      },
    );

    return SeatHoldModel.fromJson(
      ApiJson.map(_payload(response.data)),
    ).toEntity();
  }

  /// Annule la réservation, et avec elle tous ses billets.
  ///
  /// Le serveur rend la réservation close ; l'app n'en garde que le
  /// statut, seule chose dont l'écran ait besoin pour dire ce qui s'est
  /// passé.
  Future<String> cancelBooking(String reservationId) async {
    final response = await _dio.post<dynamic>(
      ApiConstants.bookingCancel(reservationId),
    );

    return ApiJson.string(
      ApiJson.map(_payload(response.data))['status'],
      'cancelled',
    );
  }

  /// Applique ou retire un code promo sur une réservation déjà retenue.
  ///
  /// `null` retire la remise en cours. Le serveur recalcule le total dans
  /// la même transaction : ni libération ni nouveau blocage, les sièges et
  /// les identités restent en place. Un code refusé revient en 422, avec
  /// son message sous `promo_code`.
  Future<ReservationPricing> updatePromoCode({
    required String reservationId,
    String? promoCode,
  }) async {
    final response = await _dio.put<dynamic>(
      ApiConstants.bookingPromoCode(reservationId),
      // Toujours présent, même nul : c'est `null` qui dit « retire ».
      data: {'promo_code': promoCode},
    );

    final json = ApiJson.map(_payload(response.data));
    final currency = ApiJson.string(json['currency'], 'XOF');
    final promotion = ApiJson.map(json['promotion']);
    final discount = ApiJson.intOrNull(json['promotion_discount_amount']) ?? 0;
    final total = ApiJson.intOrNull(json['total_amount']) ?? 0;
    final expiresAt = ApiJson.stringOrNull(json['expires_at']);

    return ReservationPricing(
      total: Price(total, currency: _currencyLabel(currency)),
      subtotal: Price(
        ApiJson.intOrNull(json['subtotal_amount']) ?? total + discount,
        currency: _currencyLabel(currency),
      ),
      promotion: promotion.isEmpty
          ? null
          : QuotePromotion(
              code: ApiJson.string(promotion['code']),
              name: ApiJson.stringOrNull(promotion['name']),
              discount: Price(
                ApiJson.intOrNull(promotion['discount_amount']) ?? discount,
                currency: _currencyLabel(currency),
              ),
            ),
      expiresAt: expiresAt == null ? null : DateTime.tryParse(expiresAt),
    );
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
      _dio.delete<dynamic>(ApiConstants.booking(reservationId));
}

/// Un tronçon de `POST bus/itinerary-quotes`.
///
/// `direction` n'est envoyé que sur un aller-retour : le serveur le
/// considère facultatif et retombe sur `outbound`, et le taire garde à
/// l'aller simple la requête vérifiée sur la staging.
/// Les tronçons d'un voyage, dans l'ordre où on les prend.
///
/// Un aller-retour les enchaîne : les tronçons de l'aller d'abord, ceux
/// du retour ensuite, `leg_order` courant sur toute la liste. Le
/// serveur en accepte six, ce qui couvre une correspondance dans chaque
/// sens.
///
/// `direction` reste tu sur un aller simple : le serveur retombe sur
/// `outbound`, et la requête garde la forme éprouvée en ligne.
List<Map<String, dynamic>> _legsJson(Journey journey) {
  final legs = [
    for (final leg in journey.outbound.legs)
      (leg: leg, direction: journey.isRoundTrip ? 'outbound' : null),
    for (final leg in journey.inbound?.legs ?? const <Departure>[])
      (leg: leg, direction: 'inbound'),
  ];

  return [
    for (var index = 0; index < legs.length; index++)
      _legJson(
        legs[index].leg,
        order: index + 1,
        direction: legs[index].direction,
      ),
  ];
}

Map<String, dynamic> _legJson(
  Departure departure, {
  required int order,
  String? direction,
}) {
  return <String, dynamic>{
    'leg_order': order,
    'departure_uuid': departure.id,
    'origin_stop_uuid': departure.originStopId,
    'destination_stop_uuid': departure.destinationStopId,
    'direction': ?direction,
  };
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
/// `XOF` est le code ISO ; `FCFA` est ce que lit le voyageur.
String _currencyLabel(String currency) =>
    currency.toUpperCase() == 'XOF' ? 'FCFA' : currency;

Object? _payload(Object? body) {
  if (body is Map && body['data'] != null) return body['data'];
  return body;
}
