part of 'notification_cubit.dart';

enum NotificationStatus { initial, loading, success, error }

class NotificationState extends Equatable {
  const NotificationState({
    this.status = NotificationStatus.initial,
    this.notifications = const <OpsNotification>[],
    this.unreadCount = 0,
    this.criticalUnreadCount = 0,
    this.errorMessage,
  });

  final NotificationStatus status;
  final List<OpsNotification> notifications;
  final int unreadCount;
  final int criticalUnreadCount;
  final String? errorMessage;

  NotificationState copyWith({
    NotificationStatus? status,
    List<OpsNotification>? notifications,
    int? unreadCount,
    int? criticalUnreadCount,
    String? errorMessage,
  }) =>
      NotificationState(
        status: status ?? this.status,
        notifications: notifications ?? this.notifications,
        unreadCount: unreadCount ?? this.unreadCount,
        criticalUnreadCount: criticalUnreadCount ?? this.criticalUnreadCount,
        errorMessage: errorMessage,
      );

  @override
  List<Object?> get props => <Object?>[
        status,
        notifications,
        unreadCount,
        criticalUnreadCount,
        errorMessage,
      ];
}
