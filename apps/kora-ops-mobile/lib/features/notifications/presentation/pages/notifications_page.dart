import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/ops_notification.dart';
import '../cubit/notification_cubit.dart';

class NotificationsPage extends StatelessWidget {
  const NotificationsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: ScreenHeader(
        eyebrow: 'Alertes',
        title: 'Notifications',
        action: BlocBuilder<NotificationCubit, NotificationState>(
          buildWhen: (a, b) => a.unreadCount != b.unreadCount,
          builder: (context, state) => state.unreadCount > 0
              ? TextButton(
                  onPressed: () =>
                      context.read<NotificationCubit>().markAllAsRead(),
                  child: const Text('Tout lire'),
                )
              : const SizedBox.shrink(),
        ),
      ),
      body: SafeArea(
        child: BlocBuilder<NotificationCubit, NotificationState>(
          builder: (context, state) {
            if (state.notifications.isEmpty) {
              if (state.status == NotificationStatus.loading) {
                return const Center(child: CircularProgressIndicator());
              }
              if (state.status == NotificationStatus.error) {
                return AppStatePlaceholder.error(
                  message: state.errorMessage ??
                      'Impossible de charger les notifications.',
                  actionLabel: 'Réessayer',
                  onAction: () => context.read<NotificationCubit>().refresh(),
                );
              }
              return const AppStatePlaceholder.empty(
                message: 'Aucune notification pour le moment.',
              );
            }

            return RefreshIndicator(
              onRefresh: () => context.read<NotificationCubit>().refresh(),
              child: ListView.separated(
                padding: const EdgeInsets.all(AppSpacing.md),
                itemCount: state.notifications.length,
                separatorBuilder: (_, __) =>
                    const SizedBox(height: AppSpacing.sm),
                itemBuilder: (context, index) => _NotificationTile(
                  notification: state.notifications[index],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.notification});

  final OpsNotification notification;

  Color _severityColor() => switch (notification.severity) {
        OpsNotificationSeverity.critical => AppColors.danger,
        OpsNotificationSeverity.warning => AppColors.warning,
        OpsNotificationSeverity.info => AppColors.info,
      };

  IconData _severityIcon() => switch (notification.severity) {
        OpsNotificationSeverity.critical => Icons.error_rounded,
        OpsNotificationSeverity.warning => Icons.warning_rounded,
        OpsNotificationSeverity.info => Icons.info_rounded,
      };

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;
    final Color severityColor = _severityColor();

    return KoraCard(
      onTap: () => _handleTap(context),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: severityColor.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: Icon(_severityIcon(), color: severityColor, size: 20),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        notification.title,
                        style: notification.isRead
                            ? AppTextStyles.body(koraColors.textPrimary)
                            : AppTextStyles.titleMedium(koraColors.textPrimary),
                      ),
                    ),
                    if (!notification.isRead)
                      Container(
                        width: 8,
                        height: 8,
                        margin: const EdgeInsets.only(left: AppSpacing.xs),
                        decoration: const BoxDecoration(
                          color: AppColors.gold,
                          shape: BoxShape.circle,
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 2),
                Text(
                  notification.message,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
                if (notification.departure != null) ...<Widget>[
                  const SizedBox(height: 4),
                  Text(
                    <String?>[
                      notification.departure!.lineCode,
                      notification.departure!.date,
                      notification.departure!.time,
                    ].whereType<String>().join(' · '),
                    style: AppTextStyles.caption(koraColors.textMuted),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _handleTap(BuildContext context) async {
    final NotificationCubit cubit = context.read<NotificationCubit>();
    final bool wasUnread = !notification.isRead;
    if (wasUnread) await cubit.markAsRead(notification.uuid);
    if (!context.mounted) return;

    // Ouvre les détails plutôt que de lancer l'action directement (échange
    // du 24/09/2026) : le convoyeur doit pouvoir lire le message complet
    // avant d'être redirigé — l'action, le cas échéant, reste accessible
    // depuis un bouton sur cette page (voir `notification_actions.dart`).
    // Copie locale marquée lue : évite un aller-retour visuel "non lue"
    // sur la page de détails pendant que `NotificationCubit` se met à
    // jour de son côté.
    await context.push(
      AppRoutes.notificationDetail,
      extra: wasUnread
          ? notification.copyWith(isRead: true, readAt: DateTime.now())
          : notification,
    );
  }
}
