import '../../../../core/result/result.dart';
import '../entities/pending_seat_release_operation.dart';
import '../entities/seat_release_outcome.dart';

/// Couche DOMAIN : descente anticipée et clôture no-show — voir MEZ-87 /
/// document.md § Descente anticipée.
///
/// Même philosophie que `BoardingRepository` : seul un échec **technique**
/// (authentification, serveur) produit un `Result.failure`. Les cas
/// métier (conflit, rejeté) et le cas hors ligne sont des
/// `Result.success(...)` — des réponses normales que l'agent doit voir.
abstract class SeatReleaseRepository {
  /// Descente anticipée d'un billet. Vérifie d'abord localement si ce
  /// billet a déjà un résultat conclusif pour ce départ (anti-doublon,
  /// y compris hors ligne — même principe que `BoardingRepository.scan`).
  /// Hors ligne : mis en file, retourne `pendingSync` sans attendre.
  Future<Result<SeatReleaseOutcome>> release({
    required String departureUuid,
    required String ticketUuid,
    required String actualStopUuid,
    required String operationUuid,
    required DateTime localRecordedAt,
  });

  /// Clôture no-show pour tout le départ. [uncontrolledTicketUuids] :
  /// billets encore "en attente" au moment de la clôture (calculés par
  /// l'appelant depuis le manifeste déjà chargé — voir `ManifesteCubit`).
  ///
  /// En ligne : un seul appel serveur (`POST /ops/seat-releases/
  /// no-show-closure`, un seul [operationUuid]), qui traite tous les
  /// billets d'un coup et renvoie un résultat par billet.
  ///
  /// Hors ligne : aucun appel — chaque billet de [uncontrolledTicketUuids]
  /// devient sa propre opération `boarding.no_show` en file locale (un
  /// `operationUuid` généré par billet, [operationUuid] ignoré dans ce
  /// cas), chacune synchronisée et résolue indépendamment plus tard — le
  /// contrat de synchro ne connaît pas de "clôture groupée hors ligne"
  /// (voir `SyncOperationType`, un seul type `boarding.no_show` par
  /// billet côté serveur).
  Future<Result<List<SeatReleaseOutcome>>> closeNoShows({
    required String departureUuid,
    required List<String> uncontrolledTicketUuids,
    required String operationUuid,
    required DateTime localRecordedAt,
  });

  Future<int> totalPendingCount();

  Future<List<PendingSeatReleaseOperation>> listPending();

  Future<void> applySyncResult({
    required String operationUuid,
    required SeatReleaseOutcome outcome,
    required DateTime serverRecordedAt,
  });
}
