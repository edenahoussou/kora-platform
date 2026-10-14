import 'package:equatable/equatable.dart';

import 'app_notification.dart';

/// Une page du centre de notifications.
///
/// Le compteur de non-lues vient du serveur et non d'un décompte local :
/// une notification lue sur un autre appareil doit éteindre la pastille
/// ici aussi, et les pages suivantes ne sont pas chargées pour pouvoir le
/// recalculer soi-même.
class NotificationFeed extends Equatable {
  const NotificationFeed({
    this.notifications = const [],
    this.unreadCount = 0,
    this.page = 1,
    this.lastPage = 1,
  });

  final List<AppNotification> notifications;
  final int unreadCount;

  final int page;
  final int lastPage;

  /// Vrai s'il reste des notifications plus anciennes à aller chercher.
  bool get hasMore => page < lastPage;

  @override
  List<Object?> get props => [notifications, unreadCount, page, lastPage];
}
