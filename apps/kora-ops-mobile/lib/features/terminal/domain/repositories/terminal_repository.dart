import '../../../../core/result/result.dart';
import '../entities/terminal.dart';
import '../entities/terminal_activity_type.dart';

/// Couche DOMAIN : lier un terminal P501 à cet appareil, rafraîchir son
/// état, et journaliser son activité — voir MEZ-91 / document.md §
/// Terminal P501.
abstract class TerminalRepository {
  /// Relit le terminal en cache localement, sans appel réseau — utilisé au
  /// démarrage pour décider immédiatement (avant confirmation serveur) si
  /// l'app doit afficher l'écran de liaison terminal ou continuer vers
  /// `/home`. Retourne `null` (jamais un échec) si aucun terminal n'a
  /// jamais été lié sur cet appareil.
  Future<Terminal?> cachedTerminal();

  /// Lie ce terminal à l'appareil : appelle le serveur, puis met le
  /// résultat en cache localement. À appeler juste après le login (voir
  /// docstring `TerminalOpsMobileController.handshake`) ou depuis l'écran
  /// de liaison si aucun terminal n'est encore connu.
  Future<Result<Terminal>> handshake(String serialNumber);

  /// Oublie le terminal lié sur cet appareil (ex. terminal remplacé) —
  /// renvoie à l'écran de liaison à la prochaine navigation.
  Future<void> forget();

  /// Accuse réception d'une synchronisation réussie auprès du serveur
  /// (`last_sync_at`) — voir MEZ-88, appelé après un lot de sync traité.
  Future<Result<Terminal>> acknowledgeSync(Map<String, dynamic> summary);

  /// Journalise un scan/une impression/une erreur. Best-effort : un échec
  /// réseau ne doit jamais interrompre le flux appelant (scan, impression)
  /// — voir `TerminalRepositoryImpl.logActivity`, qui avale l'échec après
  /// l'avoir conservé localement pour un futur rattrapage.
  Future<void> logActivity(
    TerminalActivityType type, {
    Map<String, dynamic>? context,
  });
}
