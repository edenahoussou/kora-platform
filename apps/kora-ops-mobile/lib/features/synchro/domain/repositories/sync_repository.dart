import '../../../../core/result/result.dart';
import '../entities/sync_summary.dart';

/// Couche DOMAIN : synchronise toutes les opérations terrain enregistrées
/// localement pendant une coupure réseau — embarquements (MEZ-86),
/// descentes anticipées et no-show (MEZ-87) — voir MEZ-88 / document.md §
/// Synchro.
abstract class SyncRepository {
  /// Envoie en un seul lot (max 200 par table — plafond serveur) toutes
  /// les opérations en attente, tous types confondus (`boarding.scan`,
  /// `seat_release`, `boarding.no_show`) — le serveur les réordonne
  /// lui-même par priorité (libérations avant embarquements, voir
  /// `SyncOperationType.priority()`), rien à trier côté app. Ne fait rien
  /// et retourne un résumé vide si la file est vide (jamais d'appel
  /// réseau superflu). Le lot est idempotent côté serveur par
  /// `batch_uuid`, et chaque opération par `operation_uuid` : un appel
  /// rejoué après un échec réseau partiel ne crée jamais de doublon.
  Future<Result<SyncSummary>> syncPending();
}
