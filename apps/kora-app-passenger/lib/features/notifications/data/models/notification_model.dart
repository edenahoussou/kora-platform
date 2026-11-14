import '../../../../core/network/api_json.dart';
import '../../domain/entities/app_notification.dart';

/// Couche DATA : une notification telle que le serveur la rend.
///
/// Les identifiants de type du backend (`departure_delayed`,
/// `refund_succeeded`...) s'arrêtent ici : le domaine n'en connaît que le
/// pictogramme qu'ils désignent.
///
/// `resource_uuid`, lui, **traverse** : ce n'est pas un nom de champ de
/// l'API mais une donnée métier — la réservation dont on parle. Sans
/// elle, toucher une notification ne peut mener nulle part en
/// particulier.
abstract final class NotificationModel {
  static AppNotification fromJson(Map<String, dynamic> json) {
    final type = ApiJson.string(json['type'], 'bus_update');

    return AppNotification(
      id: ApiJson.string(json['uuid']),
      kind: kindOf(type),
      title: ApiJson.string(json['title'], 'Mise à jour de votre trajet'),
      text: ApiJson.string(json['message']),
      receivedAt:
          DateTime.tryParse(ApiJson.string(json['created_at']))?.toLocal() ??
          DateTime.now(),
      isUnread: json['is_read'] != true,
      resourceUuid: ApiJson.stringOrNull(json['resource_uuid']),
    );
  }

  /// Le pictogramme à montrer, déduit du type d'événement.
  ///
  /// Les types sont ouverts côté serveur — un `refund_` porte le statut du
  /// remboursement, un `departure_` la nature du changement —, d'où la
  /// lecture par préfixe plutôt qu'une liste close qu'il faudrait suivre à
  /// chaque ajout. Un type inconnu reste une mise à jour de voyage, ce que
  /// le serveur emploie lui-même comme valeur de repli.
  static NotificationKind kindOf(String type) => switch (type) {
    'departure_cancelled' => NotificationKind.cancelled,
    'departure_reminder' => NotificationKind.reminder,
    final value when value.startsWith('departure_') => NotificationKind.delay,
    final value when value.startsWith('refund_') => NotificationKind.wallet,
    // `wallet_topup_confirmed`, `wallet_points_expiring`,
    // `wallet_restitution_paid`, `wallet_bonus_*`… Le serveur les émet
    // tous sous ce préfixe, et ils tombaient dans le repli : une
    // recharge confirmée s'affichait avec le pictogramme d'un billet.
    final value when value.startsWith('wallet_') => NotificationKind.wallet,
    _ => NotificationKind.ticket,
  };
}
