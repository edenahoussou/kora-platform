import '../../../../core/result/result.dart';
import '../entities/trip_log.dart';

/// Couche DOMAIN : carnet de bord du chauffeur et du convoyeur
/// (kilométrage départ/arrivée, carburant, péages et taxes frontières).
///
/// Même convention que `IncidentRepository` : une coupure réseau n'est
/// jamais un échec — la saisie est mise en file locale et renvoyée comme
/// un succès (`pendingSync: true`), puis rejouée par `SyncCubit` au retour
/// du réseau. Un `Failure` signale un refus exploitable (validation,
/// départ non affecté, authentification, serveur).
abstract class TripLogRepository {
  /// Lecture locale immédiate (dernier carnet en cache + saisies en
  /// attente) — affichage instantané, y compris hors ligne.
  Future<TripLog> cached(String departureUuid);

  /// Carnet à jour depuis le serveur (mis en cache), fusionné avec les
  /// saisies encore en attente. Hors ligne : le cache.
  Future<Result<TripLog>> load(String departureUuid);

  Future<Result<TripLog>> recordOdometer({
    required String departureUuid,
    required OdometerKind kind,
    required int valueKm,
    DateTime? recordedAt,
  });

  /// [draft.photoPath] doit déjà pointer vers une copie durable (voir
  /// [persistPhoto]).
  Future<Result<TripExpense>> submitExpense(TripExpenseDraft draft);

  /// Copie la photo du ticket dans le stockage de l'app (survit à un
  /// envoi différé).
  Future<String> persistPhoto(String sourcePath, String clientUuid);

  /// Saisies en attente, tous départs confondus — agrégé dans
  /// `SyncCubit.pendingOperationsCount`.
  Future<int> totalPendingCount();

  /// Rejoue les saisies en attente — appelé par `SyncCubit.syncNow`.
  Future<void> retryPending();
}
