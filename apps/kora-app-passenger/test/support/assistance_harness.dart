// La réservation assistée, montée pour les tests : un dépôt qui ne parle à
// personne, réglable pour échouer.

import 'dart:async';

import 'package:dartz/dartz.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/assisted_booking.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/price.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat_hold.dart';
import 'package:kora_app_passenger/features/booking/domain/repositories/assisted_booking_repository.dart';

/// L'agence que le réseau finit par affecter au dossier.
const kAgencyDantokpa = AssistanceAgency(
  id: 'agence-dantokpa',
  code: 'AG-000001',
  name: 'Agence Dantokpa',
  city: 'Cotonou',
);

/// Le support central : les deux numéros que le serveur expose.
const kSupport = AssistanceSupport(
  callPhone: '+229010000001',
  whatsappPhone: '+229010000002',
  callUrl: 'tel:+229010000001',
  whatsappUrl: 'https://wa.me/229010000002?text=Bonjour',
);

/// Le dossier que rend le « serveur », au statut voulu.
///
/// [agency] reste nulle par défaut : à l'envoi, personne n'a encore pris
/// le dossier.
AssistedBooking assistedBooking({
  String reservationId = 'hold-groupe-1',
  AssistanceStatus status = AssistanceStatus.pending,
  AssistanceChannel channel = AssistanceChannel.whatsapp,
  AssistanceAgency? agency,
  bool canResumeOnline = true,
}) {
  return AssistedBooking(
    id: 'dossier-1',
    status: status,
    reservationId: reservationId,
    reference: 'KBR-7X2K9P4Q',
    channel: channel,
    agency: agency,
    support: kSupport,
    amount: const Price(12500),
    validUntil: DateTime(2026, 8, 14, 5),
    whatsappMessage: 'Bonjour, je souhaite terminer cette réservation KORA',
    legs: [
      AssistanceLeg(
        origin: 'Cotonou',
        destination: 'Malanville',
        departsAt: DateTime(2026, 8, 14, 7, 30),
      ),
    ],
    seatLabels: const ['4A'],
    canResumeOnline: canResumeOnline,
  );
}

class StubAssistedBookingRepository implements AssistedBookingRepository {
  StubAssistedBookingRepository();

  /// L'agence que [fetch] rend — nulle tant que le réseau n'a affecté
  /// personne.
  AssistanceAgency? agency;

  /// Échecs rendus, dans l'ordre, par les prochains [request] — une coupure
  /// réseau suivie d'un nouvel essai.
  final List<Failure> nextRequestFailures = [];

  /// Le statut que rend [fetch].
  AssistanceStatus status = AssistanceStatus.pending;
  Failure? fetchFailure;
  Failure? resumeFailure;

  final List<String> calls = [];
  final List<String> idempotencyKeys = [];
  final List<AssistanceRecord> saved = [];
  final _changes = StreamController<void>.broadcast();

  @override
  Future<Either<Failure, AssistedBooking>> request({
    required String reservationId,
    required AssistanceChannel channel,
    required String contactName,
    required String contactPhone,
    required String idempotencyKey,
  }) async {
    calls.add('request:${channel.wire}:$contactPhone');
    idempotencyKeys.add(idempotencyKey);
    if (nextRequestFailures.isNotEmpty) {
      return Left(nextRequestFailures.removeAt(0));
    }
    // Le dossier revient avec le canal demandé : c'est lui que l'écran
    // de suivi ouvrira.
    return Right(
      assistedBooking(reservationId: reservationId, channel: channel),
    );
  }

  @override
  Future<Either<Failure, AssistedBooking>> fetch(String reservationId) async {
    calls.add('fetch:$reservationId');
    final failure = fetchFailure;
    if (failure != null) return Left(failure);
    return Right(
      assistedBooking(
        reservationId: reservationId,
        status: status,
        agency: agency,
        canResumeOnline: status == AssistanceStatus.pending,
      ),
    );
  }

  @override
  Future<Either<Failure, SeatHold>> resumeOnline({
    required String reservationId,
    required List<AssistanceSeat> seats,
    required String idempotencyKey,
  }) async {
    calls.add('resume:$reservationId:${seats.length}');
    idempotencyKeys.add(idempotencyKey);
    final failure = resumeFailure;
    if (failure != null) return Left(failure);
    return Right(
      SeatHold(
        id: reservationId,
        expiresAt: DateTime.now().toUtc().add(const Duration(minutes: 10)),
        seatLabels: [for (final seat in seats) seat.label],
        total: const Price(12500),
      ),
    );
  }

  @override
  Future<List<AssistanceRecord>> records() async => List.of(saved);

  @override
  Future<AssistanceRecord?> record(String reservationId) async {
    for (final record in saved) {
      if (record.reservationId == reservationId) return record;
    }
    return null;
  }

  @override
  Future<void> remember(AssistanceRecord record) async {
    calls.add('remember:${record.reference}');
    saved
      ..removeWhere((item) => item.reservationId == record.reservationId)
      ..insert(0, record);
    _changes.add(null);
  }

  @override
  Stream<void> get changes => _changes.stream;
}

/// Une trace locale, comme après un envoi.
AssistanceRecord assistanceRecord({String reservationId = 'hold-groupe-1'}) {
  return AssistanceRecord(
    reservationId: reservationId,
    reference: 'KBR-7X2K9P4Q',
    sentAt: DateTime(2026, 8, 13, 18),
    origin: 'Cotonou',
    destination: 'Malanville',
    departsAt: DateTime(2026, 8, 14, 7, 30),
    seats: const [
      AssistanceSeat(
        passengerIndex: 0,
        legOrder: 1,
        seatId: 'seat-4a',
        label: '4A',
      ),
    ],
  );
}
