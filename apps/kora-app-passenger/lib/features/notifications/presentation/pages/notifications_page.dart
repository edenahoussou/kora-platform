import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';

import '../../../../core/di/injector.dart';
import '../../../../core/l10n/app_localization.dart';
import '../../../../core/utils/date_formats.dart';
import '../../../../core/utils/title_color.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../booking/presentation/widgets/state_view.dart';
import '../../domain/entities/app_notification.dart';
import '../../domain/repositories/notification_repository.dart';
import '../bloc/notifications_cubit.dart';

/// Centre de notifications — écran `notifications` (HOME-02).
///
/// La liste vient du serveur : c'est la même file que celle qui part en
/// notification push, relue plutôt que reconstituée. Un message arrivé
/// téléphone éteint, ou déjà lu sur un autre appareil, se retrouve donc
/// ici dans le bon état.
class NotificationsPage extends StatelessWidget {
  const NotificationsPage({super.key, this.today, this.cubit, this.onOpen});

  /// Horloge de référence, figée par les tests : les libellés « Il y a
  /// 2 min » dépendent du moment où l'écran s'ouvre.
  final DateTime? today;

  /// Cubit déjà construit, pour les tests. En vrai, l'écran le crée
  /// lui-même et le ferme en partant.
  final NotificationsCubit? cubit;

  /// Ouvre ce que désigne la notification touchée.
  ///
  /// L'écran ne navigue pas lui-même : il dit ce qui a été touché, la
  /// route décide où cela mène. C'est ce qui permet de l'éprouver sans
  /// monter tout l'arbre de navigation.
  final void Function(AppNotification notification)? onOpen;

  @override
  Widget build(BuildContext context) {
    final existing = cubit;

    return BlocProvider<NotificationsCubit>(
      create: (_) =>
          (existing ?? NotificationsCubit(getIt<NotificationRepository>()))
            ..load(),
      child: _NotificationsView(today: today, onOpen: onOpen),
    );
  }
}

class _NotificationsView extends StatelessWidget {
  const _NotificationsView({this.today, this.onOpen});

  final DateTime? today;
  final void Function(AppNotification notification)? onOpen;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final cubit = context.read<NotificationsCubit>();

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 18),
          child: BlocBuilder<NotificationsCubit, NotificationsState>(
            builder: (context, state) {
              final notifications = state.feed.notifications;

              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  ScreenHeader(
                    eyebrow: l10n.notificationsEyebrow,
                    title: l10n.notificationsTitle,
                    // `options.action` de la maquette : le lien ne
                    // s'affiche que s'il a quelque chose à faire.
                    action: state.feed.unreadCount > 0
                        ? TextButton(
                            onPressed: () async {
                              await cubit.markAllRead();
                              if (context.mounted) {
                                KoraToaster.show(
                                  context,
                                  l10n.notificationsMarked,
                                );
                              }
                            },
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
                    child: _Body(
                      state: state,
                      today: today ?? DateTime.now(),
                      notifications: notifications,
                      onOpen: onOpen,
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({
    required this.state,
    required this.today,
    required this.notifications,
    this.onOpen,
  });

  final NotificationsState state;
  final DateTime today;
  final List<AppNotification> notifications;
  final void Function(AppNotification notification)? onOpen;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final koraColors = context.koraColors;
    final cubit = context.read<NotificationsCubit>();

    if (state.isEmptyAndLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    // Une panne ne cache ce qui est déjà lu que s'il n'y a rien à cacher :
    // perdre une liste affichée parce que la page suivante a échoué serait
    // pire que l'échec lui-même.
    if (state.failure != null && notifications.isEmpty) {
      return StateView(
        icon: Icons.wifi_off_rounded,
        title: l10n.notificationsErrorTitle,
        message: l10n.notificationsErrorText,
        primaryLabel: l10n.actionRetry,
        onPrimary: cubit.load,
        isPrimaryLoading: state.isLoading,
      );
    }

    if (state.isEmpty) {
      return RefreshIndicator(
        onRefresh: cubit.load,
        child: ListView(
          children: [
            const SizedBox(height: AppSpacing.xl),
            Text(
              l10n.notificationsEmpty,
              style: AppTextStyles.lead(koraColors.textMuted),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: cubit.load,
      child: ListView.separated(
        padding: EdgeInsets.zero,
        itemCount: notifications.length + (state.feed.hasMore ? 1 : 0),
        separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
        itemBuilder: (context, index) {
          if (index == notifications.length) {
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
              child: TextButton(
                onPressed: state.isLoadingMore ? null : cubit.loadMore,
                child: Text(
                  state.isLoadingMore ? '…' : l10n.notificationsMore,
                  style: TextStyle(
                    color: koraColors.textMuted,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            );
          }

          final notification = notifications[index];

          return _NotificationCard(
            notification: notification,
            today: today,
            onTap: () {
              cubit.markRead(notification);
              onOpen?.call(notification);
            },
          );
        },
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
