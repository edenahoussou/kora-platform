import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../app/router/app_routes.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/kora_colors.dart';
import '../../features/notifications/presentation/cubit/notification_cubit.dart';

// Ancienne version (avant MEZ-97) — aucun système de notifications
// n'existait encore, le tap affichait un état vide via KoraDialogs, et la
// pastille reprenait le compteur d'opérations de synchro en attente
// (SyncCubit) en attendant un vrai système d'alertes. Conservée en
// commentaire pour mémoire.
//
// import 'package:flutter/material.dart';
// import 'package:flutter_bloc/flutter_bloc.dart';
// import '../../app/theme/app_colors.dart';
// import '../../app/theme/kora_colors.dart';
// import '../../features/synchro/presentation/cubit/sync_cubit.dart';
// import '../services/kora_dialogs.dart';
//
// class NotificationButton extends StatelessWidget {
//   const NotificationButton({this.onNavy = false, super.key});
//
//   final bool onNavy;
//
//   @override
//   Widget build(BuildContext context) {
//     final KoraColors koraColors = context.koraColors;
//     final Color background = onNavy ? AppColors.white : koraColors.surface;
//     final Color foreground = onNavy ? AppColors.kora : koraColors.textPrimary;
//     final int pending = context.watch<SyncCubit>().state.pending.length;
//
//     return Stack(
//       clipBehavior: Clip.none,
//       children: <Widget>[
//         Material(
//           color: background,
//           shape: const CircleBorder(),
//           elevation: 1,
//           child: InkWell(
//             customBorder: const CircleBorder(),
//             onTap: () => KoraDialogs.info(
//               context,
//               title: 'Notifications',
//               description: 'Aucune notification pour le moment.',
//             ),
//             child: Padding(
//               padding: const EdgeInsets.all(10),
//               child: Icon(Icons.notifications_outlined,
//                   color: foreground, size: 18),
//             ),
//           ),
//         ),
//         if (pending > 0)
//           Positioned(
//             top: -2,
//             right: -2,
//             child: Container(
//               width: 12,
//               height: 12,
//               decoration: BoxDecoration(
//                 color: AppColors.gold,
//                 shape: BoxShape.circle,
//                 border: Border.all(color: background, width: 2),
//               ),
//             ),
//           ),
//       ],
//     );
//   }
// }

/// Bouton de notification — badge doré si des alertes sont non lues, rouge
/// si au moins une est critique (révocation de terminal, conflit de
/// scan… voir MEZ-97). Ouvre `NotificationsPage` au tap.
///
/// [onNavy] pour un usage sur fond marine fixe (en-tête de `AccueilPage`) :
/// cercle blanc/icône marine plutôt que les couleurs contextuelles du thème.
class NotificationButton extends StatelessWidget {
  const NotificationButton({this.onNavy = false, super.key});

  final bool onNavy;

  @override
  Widget build(BuildContext context) {
    final KoraColors koraColors = context.koraColors;
    final Color background = onNavy ? AppColors.white : koraColors.surface;
    final Color foreground = onNavy ? AppColors.kora : koraColors.textPrimary;
    final NotificationState notificationState =
        context.watch<NotificationCubit>().state;
    final bool hasUnread = notificationState.unreadCount > 0;
    final bool hasCritical = notificationState.criticalUnreadCount > 0;

    return Stack(
      clipBehavior: Clip.none,
      children: <Widget>[
        Material(
          color: background,
          shape: const CircleBorder(),
          elevation: 1,
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: () => context.push(AppRoutes.notifications),
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Icon(
                hasUnread
                    ? Icons.notifications_rounded
                    : Icons.notifications_outlined,
                color: foreground,
                size: 18,
              ),
            ),
          ),
        ),
        if (hasUnread)
          Positioned(
            top: -2,
            right: -2,
            child: Container(
              width: 12,
              height: 12,
              decoration: BoxDecoration(
                color: hasCritical ? AppColors.danger : AppColors.gold,
                shape: BoxShape.circle,
                border: Border.all(color: background, width: 2),
              ),
            ),
          ),
      ],
    );
  }
}
