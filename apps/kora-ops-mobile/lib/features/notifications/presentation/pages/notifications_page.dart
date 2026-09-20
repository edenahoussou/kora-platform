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
import '../../../../injection/injection.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/repositories/departure_repository.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
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
    if (!notification.isRead) await cubit.markAsRead(notification.uuid);
    if (!context.mounted) return;

    switch (notification.action) {
      case OpsNotificationAction.openDeparture:
      case OpsNotificationAction.refreshManifest:
      case OpsNotificationAction.reviewScanConflict:
        await _openDeparture(context);
      case OpsNotificationAction.reauthenticate:
        await context.read<AuthCubit>().sessionExpired();
      case OpsNotificationAction.reviewRejectedOperations:
      case OpsNotificationAction.retrySync:
        await context.push(AppRoutes.synchro);
      case OpsNotificationAction.checkPrinter:
        await context.push(AppRoutes.printerTest);
      case OpsNotificationAction.none:
        break;
    }
  }

  /// Charge le départ visé par la notification et l'ouvre — même logique
  /// que `TourneePage._select` (repli sur les infos déjà connues si le
  /// serveur est injoignable). `refresh_manifest`/`review_scan_conflict`
  /// utilisent le même chemin : sélectionner le départ recharge le
  /// manifeste de lui-même (`ManifesteCubit` écoute `SelectedDepartCubit`),
  /// pas besoin d'un rafraîchissement séparé. Sans départ associé, ou si
  /// le chargement échoue autrement, on renvoie vers Tournée pour une
  /// sélection manuelle plutôt que de bloquer le convoyeur.
  Future<void> _openDeparture(BuildContext context) async {
    final String? uuid = notification.departure?.uuid;
    if (uuid == null) {
      context.go(AppRoutes.tournee);
      return;
    }

    final result = await getIt<DepartureRepository>().show(uuid);
    if (!context.mounted) return;

    await result.when(
      onSuccess: (Depart detail) async {
        await context.read<SelectedDepartCubit>().select(detail);
        if (context.mounted) await context.push(AppRoutes.manifeste);
      },
      onFailure: (_) async {
        context.go(AppRoutes.tournee);
      },
    );
  }
}
