import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/assisted_booking.dart';
import '../entities/seat_hold.dart';

/// Confier une réservation au support KORA, la suivre, ou revenir au
/// paiement en ligne (MEZ-80).
abstract class AssistedBookingRepository {
  /// Transforme la réservation retenue en dossier d'assistance.
  ///
  /// Le dossier part dans la file du réseau : aucune agence n'est choisie
  /// ici, c'est KORA qui en affecte une.
  ///
  /// Rejouer la même [idempotencyKey] rend le dossier déjà créé : c'est ce
  /// qui rend un nouvel essai sans risque après une coupure réseau.
  Future<Either<Failure, AssistedBooking>> request({
    required String reservationId,
    required AssistanceChannel channel,
    required String contactName,
    required String contactPhone,
    required String idempotencyKey,
  });

  /// L'état courant du dossier.
  Future<Either<Failure, AssistedBooking>> fetch(String reservationId);

  /// Reprend les mêmes sièges et rend la réservation de nouveau payable
  /// en ligne. Un siège parti rend un [SeatUnavailableFailure].
  Future<Either<Failure, SeatHold>> resumeOnline({
    required String reservationId,
    required List<AssistanceSeat> seats,
    required String idempotencyKey,
  });

  /// Les demandes envoyées depuis ce téléphone, les plus récentes d'abord.
  Future<List<AssistanceRecord>> records();

  Future<AssistanceRecord?> record(String reservationId);

  Future<void> remember(AssistanceRecord record);

  /// Émet à chaque demande gardée : l'onglet Voyages, resté en mémoire
  /// derrière le tunnel, se met à jour sans être rouvert.
  Stream<void> get changes;
}
