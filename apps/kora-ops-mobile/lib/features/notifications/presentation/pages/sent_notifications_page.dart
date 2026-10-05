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
import '../cubit/sent_notification_cubit.dart';

/// Page "Notifications envoyées" (Profil → Mon compte) — les événements
/// que le convoyeur a lui-même déclenchés (incident signalé, départ fait
/// progresser…), qui n'apparaissent plus dans la page Notifications
/// normale depuis qu'elle exclut ses propres actions (échange du
/// 26/09/2026). Pure consultation : pas d'état lu/non lu, pas d'action
/// "tout lire".
class SentNotificationsPage extends StatefulWidget {
  const SentNotificationsPage({super.key});

  @override
  State<SentNotificationsPage> createState() => _SentNotificationsPageState();
}

class _SentNotificationsPageState extends State<SentNotificationsPage> {
  @override
  void initState() {
    super.initState();
    context.read<SentNotificationCubit>().load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const ScreenHeader(
        eyebrow: 'Historique',
        title: 'Notifications envoyées',
      ),
      body: SafeArea(
        child: BlocBuilder<SentNotificationCubit, SentNotificationState>(
          builder: (context, state) {
            return switch (state.status) {
              SentNotificationStatus.loading =>
                const Center(child: CircularProgressIndicator()),
              SentNotificationStatus.error => AppStatePlaceholder.error(
                  message: state.errorMessage ??
                      'Impossible de charger vos notifications envoyées.',
                  actionLabel: 'Réessayer',
                  onAction: () => context.read<SentNotificationCubit>().load(),
                ),
              SentNotificationStatus.empty => const AppStatePlaceholder.empty(
                  message: 'Vous n\'avez encore déclenché aucune alerte.',
                ),
              SentNotificationStatus.success => RefreshIndicator(
                  onRefresh: () => context.read<SentNotificationCubit>().load(),
                  child: ListView.separated(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    itemCount: state.notifications.length,
                    separatorBuilder: (_, __) =>
                        const SizedBox(height: AppSpacing.sm),
                    itemBuilder: (context, index) => _SentNotificationTile(
                      notification: state.notifications[index],
                    ),
                  ),
                ),
            };
          },
        ),
      ),
    );
  }
}

class _SentNotificationTile extends StatelessWidget {
  const _SentNotificationTile({required this.notification});

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
      // Jamais de marquage "lu" ici — c'est l'utilisateur qui a déclenché
      // l'événement, pas de `NotificationCubit.markAsRead` à appeler (ce
      // ne sont pas des lignes `ops_notifications`, voir
      // `OpsSentNotificationResource`).
      onTap: () => context.push(
        AppRoutes.notificationDetail,
        extra: notification,
      ),
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
                Text(
                  notification.title,
                  style: AppTextStyles.body(koraColors.textPrimary),
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
}
