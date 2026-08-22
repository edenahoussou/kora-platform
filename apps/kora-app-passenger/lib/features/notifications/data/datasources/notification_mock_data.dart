import '../../domain/entities/app_notification.dart';

/// Les sept notifications de la maquette (HOME-02).
///
/// Aucune route de l'API n'expose encore le centre de notifications :
/// cette liste tient lieu de source en attendant, comme les billets et
/// le Wallet. Les horodatages sont relatifs à [now] pour que l'écran
/// affiche « Il y a 2 min » quel que soit le jour où on l'ouvre.
List<AppNotification> mockNotifications(DateTime now) {
  final today = DateTime(now.year, now.month, now.day);

  return [
    AppNotification(
      id: 'ticket-confirme',
      kind: NotificationKind.ticket,
      title: 'Billet confirmé',
      text: 'Votre voyage Cotonou → Malanville est confirmé.',
      receivedAt: now.subtract(const Duration(minutes: 2)),
      isUnread: true,
    ),
    AppNotification(
      id: 'depart-imminent',
      kind: NotificationKind.reminder,
      title: 'Départ dans 2 heures',
      text: 'Présentez-vous à la gare KORA St Michel.',
      receivedAt: today.add(const Duration(hours: 6)),
      isUnread: true,
    ),
    AppNotification(
      id: 'retard',
      kind: NotificationKind.delay,
      title: 'Retard estimé : 35 min',
      text: 'Nouveau départ prévu à 08:05. Circulation dense.',
      receivedAt: today.add(const Duration(hours: 5)),
    ),
    AppNotification(
      id: 'remboursement',
      kind: NotificationKind.wallet,
      title: '+ 12 500 FCFA',
      text: 'Votre remboursement a été crédité sur le Wallet.',
      receivedAt: today.subtract(const Duration(hours: 6)),
    ),
    AppNotification(
      id: 'colis-arrive',
      kind: NotificationKind.parcel,
      title: 'Votre colis est arrivé',
      text: 'KC-20260728-40982 est disponible à Parakou.',
      receivedAt: today.subtract(const Duration(days: 3)),
    ),
    AppNotification(
      id: 'depart-annule',
      kind: NotificationKind.cancelled,
      title: 'Départ annulé',
      text: 'Remboursement intégral déclenché sur votre Wallet.',
      receivedAt: today.subtract(const Duration(days: 3, hours: 4)),
    ),
    AppNotification(
      id: 'rappel-voyage',
      kind: NotificationKind.reminder,
      title: 'Rappel de voyage',
      text: 'Votre bus part demain à 07:30 depuis St Michel.',
      receivedAt: today.subtract(const Duration(days: 25)),
    ),
  ];
}
