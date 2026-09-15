import '../../../../core/result/result.dart';
import '../entities/sync_summary.dart';

/// Couche DOMAIN : synchronise les opérations d'embarquement enregistrées
/// localement pendant une coupure réseau — voir MEZ-88 / document.md §
/// Synchro.
abstract class SyncRepository {
  /// Envoie par lot (max 200 — plafond serveur) toutes les opérations
  /// `boarding.scan` en attente. Ne fait rien et retourne un résumé vide
  /// si la file est vide (jamais d'appel réseau superflu). Le lot est
  /// idempotent côté serveur par `batch_uuid` : un appel rejoué après un
  /// échec réseau partiel ne crée jamais de doublon.
  Future<Result<SyncSummary>> syncPendingBoardingOperations();
}
