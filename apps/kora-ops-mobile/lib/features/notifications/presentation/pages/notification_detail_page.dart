import 'package:flutter/material.dart';

import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../domain/entities/ops_notification.dart';
import '../notification_actions.dart';

/// Détails d'une notification (échange du 24/09/2026) — jusque-là, taper
/// une notification déclenchait directement son action sans jamais
/// montrer le message complet, et une notification sans action
/// ([OpsNotificationAction.none]) ne faisait rien du tout au tap. Cette
/// page affiche tout ce que l'entité porte (voir `OpsNotification`) et
/// propose l'action, le cas échéant, via un bouton explicite plutôt
/// qu'une redirection immédiate.
class NotificationDetailPage extends StatelessWidget {
  const NotificationDetailPage({required this.notification, super.key});

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

  String _severityLabel() => switch (notification.severity) {
        OpsNotificationSeverity.critical => 'Alerte critique',
        OpsNotificationSeverity.warning => 'Avertissement',
        OpsNotificationSeverity.info => 'Information',
      };

  String _formatDateTime(DateTime value) {
    final DateTime local = value.toLocal();
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(local.day)}/${two(local.month)}/${local.year} à '
        '${two(local.hour)}:${two(local.minute)}';
  }

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;
    final Color severityColor = _severityColor();
    final String? actionLabel = notificationActionLabel(notification.action);

    return Scaffold(
      appBar: ScreenHeader(eyebrow: _severityLabel(), title: 'Notification'),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: <Widget>[
            KoraCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Container(
                        width: 44,
                        height: 44,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: severityColor.withValues(alpha: 0.12),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(_severityIcon(),
                            color: severityColor, size: 22),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          notification.title,
                          style:
                              AppTextStyles.titleMedium(koraColors.textPrimary),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    notification.message,
                    style: AppTextStyles.body(koraColors.textPrimary),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            if (notification.departure != null) ...<Widget>[
              _DetailCard(
                title: 'Départ concerné',
                rows: <(String, String)>[
                  if (notification.departure!.lineCode != null)
                    ('Ligne', notification.departure!.lineCode!),
                  if (notification.departure!.date != null)
                    ('Date', notification.departure!.date!),
                  if (notification.departure!.time != null)
                    ('Heure', notification.departure!.time!),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
            ],
            _DetailCard(
              title: 'Chronologie',
              rows: <(String, String)>[
                if (notification.occurredAt != null)
                  ('Survenue le', _formatDateTime(notification.occurredAt!)),
                ('Reçue le', _formatDateTime(notification.createdAt)),
                (
                  'Statut',
                  notification.isRead && notification.readAt != null
                      ? 'Lue le ${_formatDateTime(notification.readAt!)}'
                      : notification.isRead
                          ? 'Lue'
                          : 'Non lue',
                ),
              ],
            ),
            if (actionLabel != null) ...<Widget>[
              const SizedBox(height: AppSpacing.lg),
              KoraButton(
                label: actionLabel,
                onPressed: () => runNotificationAction(context, notification),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DetailCard extends StatelessWidget {
  const _DetailCard({required this.title, required this.rows});

  final String title;
  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) return const SizedBox.shrink();
    final KoraColors koraColors = context.koraColors;

    return KoraCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(title, style: AppTextStyles.eyebrow(koraColors.textMuted)),
          const SizedBox(height: AppSpacing.sm),
          for (final (String label, String value) in rows) ...<Widget>[
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  SizedBox(
                    width: 100,
                    child: Text(label,
                        style: AppTextStyles.caption(koraColors.textMuted)),
                  ),
                  Expanded(
                    child: Text(value,
                        style: AppTextStyles.body(koraColors.textPrimary)),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
