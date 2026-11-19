import 'package:dartz/dartz.dart';
import 'package:dio/dio.dart';

import '../../../../core/error/api_failure_mapper.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/company_account.dart';
import '../../domain/entities/group_payer.dart';
import '../../domain/entities/group_quote.dart';
import '../../domain/entities/group_ticket.dart';
import '../../domain/entities/journey.dart';
import '../../domain/entities/passenger.dart';
import '../../domain/entities/reservation_pricing.dart';
import '../../domain/entities/seat_hold.dart';
import '../../domain/repositories/reservation_repository.dart';
import '../datasources/booking_cache_local_data_source.dart';
import '../datasources/reservation_remote_data_source.dart';

class ReservationRepositoryImpl
    with ApiFailureMapper
    implements ReservationRepository {
  const ReservationRepositoryImpl(this._dataSource, this._tickets);

  final ReservationRemoteDataSource _dataSource;

  /// Les billets déjà émis, gardés sur le téléphone : à l'embarquement, le
  /// réseau manque souvent (APP-PAY-006).
  final BookingCacheLocalDataSource _tickets;

  @override
  Future<Either<Failure, List<GroupTicket>>> bookingTickets(
    String reservationId,
  ) async {
    final result = await attempt(
      () => _dataSource.bookingTickets(reservationId),
    );

    if (result.isRight()) {
      final tickets = result.getOrElse(() => const []);
      await _tickets.rememberGroupTickets(reservationId, tickets);
      return Right(tickets);
    }

    // Le serveur ne répond pas : la copie vaut mieux qu'un écran vide
    // devant le convoyeur. Sans copie, l'échec reste un échec.
    final kept = _tickets.groupTickets(reservationId);
    return kept.isEmpty ? result : Right(kept);
  }

  @override
  Future<Either<Failure, List<CompanyAccount>>> companyAccounts() =>
      attempt(_dataSource.companyAccounts);

  @override
  Future<Either<Failure, Unit>> cancelBooking(String reservationId) async {
    final result = await attempt(
      () => _dataSource.cancelBooking(reservationId),
    );

    return result.map((_) => unit);
  }

  @override
  Future<Either<Failure, ReservationPricing>> updatePromoCode({
    required String reservationId,
    String? promoCode,
  }) async {
    ServerFailure? broken;

    final result = await attempt(() async {
      try {
        return await _dataSource.updatePromoCode(
          reservationId: reservationId,
          promoCode: promoCode,
        );
      } on DioException catch (error) {
        // Une panne du recalcul rend « Server Error », le message
        // générique de Laravel : illisible pour le voyageur, et qui ne dit
        // pas que sa réservation, elle, tient toujours.
        broken = _promoUnavailable(error);
        rethrow;
      }
    });

    final captured = broken;
    return captured == null ? result : Left(captured);
  }

  @override
  Future<Either<Failure, SeatHold>> holdGroup({
    required GroupQuote quote,
    required Journey journey,
    required GroupPayer payer,
    required String idempotencyKey,
  }) async {
    SeatUnavailableFailure? conflict;

    final result = await attempt(() async {
      try {
        return await _dataSource.holdGroup(
          quote: quote,
          journey: journey,
          payer: payer,
          idempotencyKey: idempotencyKey,
        );
      } on DioException catch (error) {
        // Le 409 est relevé ici, avant que le mapper commun ne le réduise
        // à son statut : lui perdrait le siège que le serveur nomme, et
        // c'est pourtant la seule information qui permette à l'écran de
        // repeindre ce siège en occupé plutôt que de tout recommencer.
        conflict = _seatConflict(error);
        rethrow;
      }
    });

    final captured = conflict;
    return captured == null ? result : Left(captured);
  }

  @override
  Future<Either<Failure, GroupQuote>> groupQuote({
    required Journey journey,
    required int passengers,
    String? promoCode,
  }) {
    return attempt(
      () => _dataSource.groupQuote(
        journey: journey,
        passengers: passengers,
        promoCode: promoCode,
      ),
    );
  }

  @override
  Future<Either<Failure, SeatHold>> savePassengers({
    required String reservationId,
    required List<Passenger> passengers,
  }) => attempt(
    () => _dataSource.savePassengers(
      reservationId: reservationId,
      passengers: passengers,
    ),
  );

  @override
  Future<Either<Failure, void>> release(String reservationId) =>
      attempt(() => _dataSource.release(reservationId));
}

/// Lit un 409 « siège plus disponible », ou rend `null` si c'en est un
/// autre.
/// Le serveur n'a pas su recalculer la réservation : le code n'est pas en
/// cause, et la réservation reste payable au tarif affiché.
ServerFailure? _promoUnavailable(DioException error) {
  final status = error.response?.statusCode ?? 0;
  if (status < 500) return null;

  // La ligne suivante de l'écran dit déjà qu'on peut continuer au tarif
  // normal : la répéter ici ferait deux fois la même phrase.
  return const ServerFailure(
    'Le code n’a pas pu être appliqué à cette réservation.',
  );
}

SeatUnavailableFailure? _seatConflict(DioException error) {
  if (error.response?.statusCode != 409) return null;

  final body = ApiJson.map(error.response?.data);
  final label = ApiJson.stringOrNull(body['seat_code']);

  return SeatUnavailableFailure(
    ApiJson.string(
      body['message'],
      label == null
          ? 'Ce siège vient d’être pris.'
          : 'Le siège $label vient d’être pris.',
    ),
    seatId: ApiJson.stringOrNull(body['conflicting_seat_uuid']),
    seatLabel: label,
  );
}
