import '../entities/border_manifest_print_job.dart';

/// Persistance et machine à états des impressions du manifeste frontière
/// (MEZ-94) — ce qui garantit « pas d'exemplaire supplémentaire en
/// silence », même après une erreur matérielle, un crash ou un redémarrage
/// du terminal.
///
/// Séquence d'un exemplaire :
/// `markCopyStarted` → (impression) → `markCopyConfirmed` **ou**
/// `markCopyFailed`. Un crash entre les deux laisse l'exemplaire « en
/// cours » ; il se règle avec [resolveInFlightCopy] après constat du
/// convoyeur.
///
/// Toute transition interdite (ex. démarrer l'exemplaire 3 alors que le 2
/// n'est pas confirmé, dépasser le nombre demandé) lève une [StateError] :
/// l'invariant est protégé ici, pas seulement par l'écran.
abstract class BorderManifestPrintRepository {
  /// Demande non terminée (en cours ou interrompue) pour ce départ, la plus
  /// récente — `null` s'il n'y en a pas.
  Future<BorderManifestPrintJob?> findUnfinishedJob(String departureUuid);

  /// Dernière demande (terminée ou non) pour ce départ.
  Future<BorderManifestPrintJob?> findLastJob(String departureUuid);

  /// Crée une demande de [requestedCopies] exemplaires. **Ne crée jamais un
  /// second job non terminé** pour le même départ : s'il en existe déjà un,
  /// il est renvoyé tel quel (reprise, pas doublon).
  Future<BorderManifestPrintJob> createJob({
    required String departureUuid,
    required int requestedCopies,
    String? manifestVersion,
    String? userUuid,
    String? terminalSerial,
  });

  /// Marque [copyNumber] « envoyé à l'imprimante » — à persister AVANT
  /// l'appel matériel. Exige `copyNumber == confirmedCopies + 1`, aucun
  /// exemplaire déjà en cours, et `copyNumber <= requestedCopies`.
  Future<BorderManifestPrintJob> markCopyStarted(
    String jobUuid,
    int copyNumber,
  );

  /// L'imprimante a confirmé [copyNumber] : le compte d'exemplaires
  /// confirmés passe à [copyNumber]. Termine le job s'il était le dernier.
  Future<BorderManifestPrintJob> markCopyConfirmed(
    String jobUuid,
    int copyNumber,
  );

  /// Erreur sur [copyNumber] (papier, imprimante…) : plus d'exemplaire en
  /// cours, job « interrompu », [message] conservé. Le compte confirmé ne
  /// bouge pas — la reprise repart de cet exemplaire.
  Future<BorderManifestPrintJob> markCopyFailed(
    String jobUuid,
    int copyNumber,
    String message,
  );

  /// Règle un exemplaire resté « en cours » (crash) d'après le constat du
  /// convoyeur : [printed] `true` = il est sorti (compté confirmé),
  /// `false` = il n'est pas sorti (sera réimprimé à la reprise).
  Future<BorderManifestPrintJob> resolveInFlightCopy(
    String jobUuid, {
    required bool printed,
  });
}
