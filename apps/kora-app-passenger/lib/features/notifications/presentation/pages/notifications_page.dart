import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../data/datasources/notification_mock_data.dart';
import '../../domain/entities/app_notification.dart';

/// Centre de notifications — écran `notifications` (HOME-02).
///
/// Aucune route de l'API ne les expose encore : la liste vient d'un jeu
/// de données figé, comme les billets et le Wallet. « Tout lire » retire
/// donc les pastilles à l'écran sans rien envoyer, et l'ouverture d'une
/// ligne annonce l'écran de détail plutôt que de mener à une page vide.
class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key, this.today});

  /// Horloge de référence, figée par les tests : les libellés « Il y a
  /// 2 min » dépendent du moment où l'écran s'ouvre.
  final DateTime? today;

  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> {
  late List<AppNotification> _notifications = mockNotifications(
    widget.today ?? DateTime.now(),
  );

  void _markAllRead() {
    setState(() {
      _notifications = [
        for (final notification in _notifications) notification.markRead(),
      ];
    });

    KoraToaster.show(context, context.l10n.notificationsMarked);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final hasUnread = _notifications.any((n) => n.isUnread);

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ScreenHeader(
                eyebrow: l10n.notificationsEyebrow,
                title: l10n.notificationsTitle,
                // `options.action` de la maquette : le lien ne s'affiche
                // que s'il a quelque chose à faire.
                action: hasUnread
                    ? TextButton(
                        onPressed: _markAllRead,
                        child: Text(
                          l10n.notificationsMarkAll,
                          style: TextStyle(
                            color: koraColors.textMuted,
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      )
                    : null,
              ),
              Expanded(
                child: _notifications.isEmpty
                    ? Text(
                        l10n.notificationsEmpty,
                        style: AppTextStyles.lead(koraColors.textMuted),
                      )
                    : ListView.separated(
                        padding: EdgeInsets.zero,
                        itemCount: _notifications.length,
                        separatorBuilder: (_, _) =>
                            const SizedBox(height: AppSpacing.md),
                        itemBuilder: (context, index) => _NotificationCard(
                          notification: _notifications[index],
                          today: widget.today ?? DateTime.now(),
                          onTap: () =>
                              KoraToaster.show(context, l10n.notificationsSoon),
                        ),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `notification()` de la maquette : carte cliquable, pictogramme, titre
/// avec sa pastille, texte, date et chevron.
class _NotificationCard extends StatelessWidget {
  const _NotificationCard({
    required this.notification,
    required this.today,
    required this.onTap,
  });

  final AppNotification notification;
  final DateTime today;
  final VoidCallback onTap;

  /// « Il y a 2 min » dans l'heure, « Aujourd'hui », « Hier », puis la
  /// date — comme la maquette, qui mélange les trois formes.
  String _when(BuildContext context) {
    final l10n = context.l10n;
    final locale = Localizations.localeOf(context).languageCode;
    final elapsed = today.difference(notification.receivedAt);

    if (elapsed.inMinutes < 60 && !elapsed.isNegative) {
      return l10n.notificationsMinutesAgo(elapsed.inMinutes);
    }

    final day = DateTime(
      notification.receivedAt.year,
      notification.receivedAt.month,
      notification.receivedAt.day,
    );
    final reference = DateTime(today.year, today.month, today.day);
    final days = reference.difference(day).inDays;

    return switch (days) {
      0 => l10n.notificationsToday,
      1 => l10n.notificationsYesterday,
      _ => KoraDateFormats.dayMonthIn(notification.receivedAt, locale),
    };
  }

  @override
  Widget build(BuildContext context) {
    final koraColors = context.koraColors;

    return KoraCard(
      onTap: onTap,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // `.transaction-icon` : 40×40, radius 13, fond mist.
          Container(
            width: 40,
            height: 40,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: koraColors.background,
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(
              notification.kind.icon,
              size: 19,
              color: context.koraTitleColor,
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        notification.title,
                        style: AppTextStyles.titleMedium(
                          context.koraTitleColor,
                        ),
                      ),
                    ),
                    if (notification.isUnread)
                      Container(
                        width: 8,
                        height: 8,
                        decoration: const BoxDecoration(
                          shape: BoxShape.circle,
                          color: AppColors.gold,
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  notification.text,
                  style: AppTextStyles.body(koraColors.textMuted),
                ),
                const SizedBox(height: 6),
                Text(
                  _when(context),
                  style: AppTextStyles.caption(koraColors.textMuted),
                ),
              ],
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          Icon(
            Icons.chevron_right_rounded,
            size: 20,
            color: koraColors.textMuted,
          ),
        ],
      ),
    );
  }
}
