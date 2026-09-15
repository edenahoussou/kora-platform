import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../cubit/sync_cubit.dart';

/// Pastille compacte de statut connexion/synchro — voir document.md §
/// Synchro. Intégrée à la coquille de la bottom nav (visible sur les 5
/// onglets) et dans `ScreenHeader.action` des écrans empilés, pour rester
/// visible partout dans l'app comme l'exige le critère d'acceptation.
class SyncStatusBanner extends StatelessWidget {
  const SyncStatusBanner({this.compact = false, super.key});

  /// Version compacte (juste la pastille), utilisée en `action` de
  /// `ScreenHeader`. Sinon, bandeau pleine largeur avec libellé.
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<SyncCubit, SyncState>(
      builder: (BuildContext context, SyncState state) {
        final int totalPending = state.totalPending;
        final Color color = !state.isOnline
            ? AppColors.danger
            : totalPending > 0
                ? AppColors.warning
                : AppColors.success;

        final String label = !state.isOnline
            ? 'Hors ligne${totalPending > 0 ? ' · $totalPending en attente' : ''}'
            : state.isSyncing
                ? 'Synchronisation…'
                : totalPending > 0
                    ? '$totalPending en attente'
                    : 'En ligne';

        final Widget dot = Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        );

        if (compact) {
          return Tooltip(
            message: label,
            child: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12), shape: BoxShape.circle),
              child: dot,
            ),
          );
        }

        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(AppRadius.pill),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              dot,
              const SizedBox(width: 6),
              Text(label,
                  style: TextStyle(
                      color: color, fontSize: 11, fontWeight: FontWeight.w700)),
            ],
          ),
        );
      },
    );
  }
}
