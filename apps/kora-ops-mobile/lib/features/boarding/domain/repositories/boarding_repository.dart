import '../../../../core/result/result.dart';
import '../entities/boarding_outcome.dart';
import '../entities/pending_boarding_operation.dart';

/// Couche DOMAIN : valider un scan/une saisie manuelle d'embarquement —
/// voir MEZ-91 / document.md § Scan boarding.
///
/// Ne retourne un `Failure` (`Result.failure`) que pour un échec
/// **technique** qui empêche de produire un résultat exploitable
/// (authentification, erreur serveur 5xx, requête malformée). Tous les cas
/// métier du ticket — y compris "billet inconnu", "annulé", "conflit" — et
/// le cas hors ligne sont des `Result.success(BoardingOutcome(...))` :
/// ce sont des réponses normales que l'agent doit voir, pas des erreurs.
abstract class BoardingRepository {
  /// [qrPayload] xor [shortCode] doit être fourni (jamais les deux) —
  /// voir `BoardingScanRequest` côté backend. [operationUuid] est généré
  /// par l'appelant (voir `Uuid().v4()` dans `ScanBoardingCubit`) : c'est
  /// la clé d'idempotence, y compris entre deux tentatives d'un même scan
  /// après une coupure réseau.
  ///
  /// Vérifie d'abord localement si ce billet (même [qrPayload]/[shortCode]
  /// pour ce [departureUuid]) a déjà un résultat conclusif enregistré sur
  /// ce terminal — dans ce cas, retourne ce résultat immédiatement, sans
  /// appel réseau (anti-double-scan, y compris hors ligne — voir critère
  /// d'acceptation MEZ-91).
  ///
  /// Si aucun réseau n'est disponible, enregistre la tentative localement
  /// (file hors ligne — MEZ-88) et retourne `BoardingOutcomeStatus.
  /// pendingSync` sans attendre.
  Future<Result<BoardingOutcome>> scan({
    required String departureUuid,
    String? qrPayload,
    String? shortCode,
    required String operationUuid,
    required DateTime localRecordedAt,
  });

  /// Nombre d'embarquements enregistrés localement pour [departureUuid] et
  /// pas encore synchronisés (`sync_status = pending`) — affiché dans
  /// `SyncStatusBanner`/l'onglet Synchro dès l'étape suivante (MEZ-88).
  Future<int> pendingCount(String departureUuid);

  /// Toutes les opérations en attente de synchronisation, tous départs
  /// confondus — voir `SyncRepository.syncNow` (MEZ-88).
  Future<List<PendingBoardingOperation>> listPending();

  /// Nombre total d'opérations en attente, tous départs confondus.
  Future<int> totalPendingCount();

  /// Applique le résultat d'un lot de synchronisation à une opération déjà
  /// enregistrée localement — voir `SyncRepository.syncNow`.
  Future<void> applySyncResult({
    required String operationUuid,
    required BoardingOutcome outcome,
    required DateTime serverRecordedAt,
  });
}
