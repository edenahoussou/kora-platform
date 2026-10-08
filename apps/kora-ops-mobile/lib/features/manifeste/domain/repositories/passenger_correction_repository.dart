import '../../../../core/result/result.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../entities/passenger_correction.dart';

/// Couche DOMAIN : correction par le convoyeur des informations d'un
/// passager (avant comme après l'embarquement) — voir
/// `OpsPassengerCorrectionController` côté backend.
abstract class PassengerCorrectionRepository {
  /// Envoie la correction. Hors ligne (réseau injoignable), la met en file
  /// locale et renvoie [PassengerCorrectionOutcome.pendingSync] ; un refus
  /// du serveur (validation 422, droit 403, billet inconnu 404) est un
  /// `Result.failure` avec un message affichable.
  Future<Result<PassengerCorrectionOutcome>> correct({
    required String departureUuid,
    required String ticketUuid,
    required PassengerCorrection correction,
    String? reason,
  });

  /// Rejoue les corrections en attente d'un départ. Renvoie le nombre
  /// effectivement envoyées au serveur (acceptées). Silencieux hors ligne.
  Future<int> flushPending(String departureUuid);

  /// UUID des billets ayant une correction encore en attente d'envoi.
  Future<Set<String>> pendingTicketUuids(String departureUuid);

  /// Réapplique les corrections en attente sur [passengers] fraîchement
  /// téléchargés (le serveur n'a pas encore reçu ces corrections).
  Future<List<Passenger>> overlayPending(
      String departureUuid, List<Passenger> passengers);
}
