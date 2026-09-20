import '../../../../core/result/result.dart';
import '../entities/incident.dart';

/// Couche DOMAIN : signalement d'incident par le convoyeur (MEZ-98).
///
/// Ne retourne un `Failure` que pour un échec **technique** qui empêche
/// tout résultat exploitable (authentification, erreur serveur 5xx,
/// requête malformée) — même convention que `BoardingRepository.scan` :
/// une coupure réseau n'est pas un échec, l'incident est mis en file et
/// retourné comme un `Result.success` (`pendingSync: true`).
abstract class IncidentRepository {
  Future<Result<Incident>> report({
    required String operationUuid,
    required String departureUuid,
    required IncidentType type,
    required String description,
    required DateTime localRecordedAt,
    double? latitude,
    double? longitude,
  });

  /// Nombre d'incidents en attente de synchronisation, tous départs
  /// confondus — agrégé dans `SyncCubit.pendingOperationsCount`.
  Future<int> totalPendingCount();

  /// Réémet chaque incident en attente vers le serveur — appelé par
  /// `SyncCubit.syncNow` au retour du réseau. Contrairement à
  /// boarding/seat_release, pas de lot dédié côté serveur pour les
  /// incidents : un `POST /ops/incidents` par incident, idempotent via
  /// [Incident.operationUuid] (`incident_uuid`).
  Future<void> retryPending();
}
