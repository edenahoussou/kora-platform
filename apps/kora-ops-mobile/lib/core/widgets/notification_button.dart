import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../app/theme/app_colors.dart';
import '../../app/theme/kora_colors.dart';
import '../../features/synchro/presentation/cubit/sync_cubit.dart';
import '../services/kora_dialogs.dart';

/// Bouton de notification — aucun système de notifications n'existe encore
/// (voir document.md § Notifications), le tap affiche un état vide via
/// [KoraDialogs]. Une pastille dorée apparaît s'il y a des opérations de
/// synchro en attente (voir `SyncCubit`), en complément visuel discret du
/// détail disponible sur l'onglet Synchro.
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
    final int pending = context.watch<SyncCubit>().state.pending.length;

    return Stack(
      clipBehavior: Clip.none,
      children: <Widget>[
        Material(
          color: background,
          shape: const CircleBorder(),
          elevation: 1,
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: () => KoraDialogs.info(
              context,
              title: 'Notifications',
              description: 'Aucune notification pour le moment.',
            ),
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Icon(Icons.notifications_outlined,
                  color: foreground, size: 18),
            ),
          ),
        ),
        if (pending > 0)
          Positioned(
            top: -2,
            right: -2,
            child: Container(
              width: 12,
              height: 12,
              decoration: BoxDecoration(
                color: AppColors.gold,
                shape: BoxShape.circle,
                border: Border.all(color: background, width: 2),
              ),
            ),
          ),
      ],
    );
  }
}
