import 'package:dartz/dartz.dart';
import 'package:dio/dio.dart';

import '../../../../core/error/api_failure_mapper.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/assisted_booking.dart';
import '../../domain/entities/seat_hold.dart';
import '../../domain/repositories/assisted_booking_repository.dart';
import '../datasources/assisted_booking_local_data_source.dart';
import '../datasources/assisted_booking_remote_data_source.dart';

class AssistedBookingRepositoryImpl
    with ApiFailureMapper
    implements AssistedBookingRepository {
  const AssistedBookingRepositoryImpl(this._remote, this._local);

  final AssistedBookingRemoteDataSource _remote;
  final AssistedBookingLocalDataSource _local;

  @override
  Future<Either<Failure, AssistedBooking>> request({
    required String reservationId,
    required AssistanceChannel channel,
    required String contactName,
    required String contactPhone,
    required String idempotencyKey,
  }) async {
    Failure? translated;

    final result = await attempt(() async {
      try {
        return await _remote.request(
          reservationId: reservationId,
          channel: channel,
          contactName: contactName,
          contactPhone: contactPhone,
          idempotencyKey: idempotencyKey,
        );
      } on DioException catch (error) {
        // Le 503 du support ne porte qu'un code : sans ça, le mapper
        // commun rendrait « erreur serveur », qui ne dit pas quoi faire.
        // Le 403, lui, arrive avec le message anglais de Laravel.
        translated = _supportUnavailable(error) ?? _notAllowed(error);
        rethrow;
      }
    });

    final captured = translated;
    return captured == null ? result : Left(captured);
  }

  @override
  Future<Either<Failure, AssistedBooking>> fetch(String reservationId) =>
      attempt(() => _remote.fetch(reservationId));

  @override
  Future<Either<Failure, SeatHold>> resumeOnline({
    required String reservationId,
    required List<AssistanceSeat> seats,
    required String idempotencyKey,
  }) async {
    SeatUnavailableFailure? conflict;

    final result = await attempt(() async {
      try {
        return await _remote.resumeOnline(
          reservationId: reservationId,
          seats: seats,
          idempotencyKey: idempotencyKey,
        );
      } on DioException catch (error) {
        // Le 409 d'un siège parti nomme ce siège : le mapper commun le
        // perdrait. Celui d'un dossier déjà pris par un agent n'en nomme
        // aucun, et garde son message tel quel.
        conflict = _seatConflict(error);
        rethrow;
      }
    });

    final captured = conflict;
    return captured == null ? result : Left(captured);
  }

  @override
  Future<List<AssistanceRecord>> records() async => _local.records();

  @override
  Future<AssistanceRecord?> record(String reservationId) async {
    for (final record in _local.records()) {
      if (record.reservationId == reservationId) return record;
    }
    return null;
  }

  @override
  Future<void> remember(AssistanceRecord record) => _local.remember(record);

  @override
  Stream<void> get changes => _local.changes;
}

/// Le support central injoignable : le canal demandé n'a pas de numéro
/// configuré côté serveur.
ServerFailure? _supportUnavailable(DioException error) {
  if (error.response?.statusCode != 503) return null;

  final body = ApiJson.map(error.response?.data);
  final code = ApiJson.stringOrNull(ApiJson.map(body['error'])['code']);
  if (code != 'assisted_booking_support_unavailable') return null;

  return const ServerFailure(
    'Le service d’assistance KORA est momentanément injoignable. '
    'Réessayez dans un instant, ou payez en ligne.',
  );
}

/// Le serveur refuse la demande d'assistance pour ce compte.
///
/// Sa réponse est « This action is unauthorized. » — le message par défaut
/// de Laravel, en anglais, que le voyageur voyait tel quel. Le motif est
/// côté serveur : la politique exige, en plus d'être propriétaire de la
/// réservation, la permission `bookings.create`, portée par le rôle
/// `passenger`. Ce rôle n'est posé qu'à l'inscription : un compte né
/// autrement — guichet, import, réservation assistée — ne l'a pas, et se
/// voit refuser le relais vers un agent alors qu'il peut réserver, annuler
/// et payer.
///
/// L'app ne peut pas lever ce refus. Elle peut au moins le dire en
/// français, et indiquer la sortie : le paiement en ligne, lui, reste
/// ouvert.
AuthFailure? _notAllowed(DioException error) {
  if (error.response?.statusCode != 403) return null;

  return const AuthFailure(
    'Votre compte n’est pas autorisé à confier une réservation à un agent. '
    'Payez en ligne, ou contactez le support KORA.',
  );
}

SeatUnavailableFailure? _seatConflict(DioException error) {
  if (error.response?.statusCode != 409) return null;

  final body = ApiJson.map(error.response?.data);
  final label = ApiJson.stringOrNull(body['seat_code']);
  if (label == null) return null;

  return SeatUnavailableFailure(
    ApiJson.string(body['message'], 'Le siège $label vient d’être pris.'),
    seatId: ApiJson.stringOrNull(body['conflicting_seat_uuid']),
    seatLabel: label,
  );
}
