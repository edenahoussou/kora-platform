part of 'sent_notification_cubit.dart';

enum SentNotificationStatus { loading, success, empty, error }

class SentNotificationState extends Equatable {
  const SentNotificationState({
    this.status = SentNotificationStatus.loading,
    this.notifications = const <OpsNotification>[],
    this.errorMessage,
  });

  final SentNotificationStatus status;
  final List<OpsNotification> notifications;
  final String? errorMessage;

  SentNotificationState copyWith({
    SentNotificationStatus? status,
    List<OpsNotification>? notifications,
    String? errorMessage,
  }) {
    return SentNotificationState(
      status: status ?? this.status,
      notifications: notifications ?? this.notifications,
      errorMessage: errorMessage,
    );
  }

  @override
  List<Object?> get props => <Object?>[status, notifications, errorMessage];
}
