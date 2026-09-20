import '../../../../core/result/result.dart';
import '../entities/ops_notification.dart';

/// Compteurs badge (voir `GET /notifications/unread-count`).
class OpsNotificationCounts {
  const OpsNotificationCounts({
    required this.unread,
    required this.criticalUnread,
  });

  final int unread;
  final int criticalUnread;
}

abstract class NotificationRepository {
  /// Notifications les plus récentes, les plus récentes d'abord. En ligne :
  /// retélécharge la première page et la fusionne dans le cache local
  /// (dédoublonnage par `uuid` — voir critère MEZ-97 "affichée sans
  /// doublon"), puis relit le cache pour renvoyer un état cohérent, y
  /// compris les marquages "lu" pas encore synchronisés. Hors ligne :
  /// retombe directement sur le cache, sans échec (voir critère "restaurer
  /// les alertes non acquittées après redémarrage").
  Future<Result<List<OpsNotification>>> list();

  /// Relit uniquement le cache local — restauration immédiate au
  /// démarrage, avant même le premier appel réseau.
  Future<List<OpsNotification>> readCache();

  /// Compteurs (badge cloche). Retombe sur un calcul local si l'appel
  /// réseau échoue.
  Future<OpsNotificationCounts> unreadCounts();

  /// Marque une notification lue — optimiste localement (utilisable hors
  /// ligne), envoi au serveur en best-effort. Voir [retryPendingReads].
  Future<void> markRead(String uuid);

  /// Marque tout lu — même stratégie optimiste que [markRead].
  Future<void> markAllRead();

  /// Réémet vers le serveur les marquages "lu" faits hors ligne (voir
  /// critère MEZ-97 "conserver une trace des actions locales") — à
  /// appeler au retour du réseau, comme `SyncCubit.syncNow`.
  Future<void> retryPendingReads();

  /// Vide le cache local — à la déconnexion (terminal partagé : le
  /// prochain convoyeur ne doit jamais voir les alertes du précédent, même
  /// non lues — voir `SelectedDepartCubit.clear` pour la même règle sur le
  /// départ géré).
  Future<void> clearCache();
}
