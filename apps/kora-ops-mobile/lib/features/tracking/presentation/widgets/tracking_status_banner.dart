import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../domain/entities/tracking_availability.dart';
import '../cubit/tracking_cubit.dart';

/// Pastille compacte de l'état du suivi de position — même gabarit que
/// `SyncStatusBanner` (MEZ-101, critère d'acceptation « le convoyeur sait
/// pourquoi le suivi est indisponible et peut corriger »). N'affiche rien
/// tant qu'aucun suivi n'est actif ET qu'aucune condition ne bloque son
/// démarrage (départ non encore parti — rien à signaler dans ce cas).
///
/// Tapable en mode non compact quand une condition bloque le démarrage :
/// relance la demande de permission/vérifie le GPS (voir
/// `TrackingCubit.requestPermission`).
class TrackingStatusBanner extends StatelessWidget {
  const TrackingStatusBanner({this.compact = false, super.key});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<TrackingCubit, TrackingState>(
      builder: (BuildContext context, TrackingState state) {
        final bool hasSomethingToShow =
            state.isActive || state.availability != TrackingAvailability.ready;
        if (!hasSomethingToShow) return const SizedBox.shrink();

        final _TrackingBannerContent content = _contentFor(state);

        final Widget dot = Container(
          width: 8,
          height: 8,
          decoration:
              BoxDecoration(color: content.color, shape: BoxShape.circle),
        );

        if (compact) {
          return Tooltip(
            message: content.label,
            child: Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: content.color.withValues(alpha: 0.12),
                shape: BoxShape.circle,
              ),
              child: dot,
            ),
          );
        }

        final Widget pill = Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: content.color.withValues(alpha: 0.1),
            borderRadius: BorderRadius.circular(AppRadius.pill),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              dot,
              const SizedBox(width: 6),
              Text(
                content.label,
                style: TextStyle(
                  color: content.color,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        );

        if (!content.correctable) return pill;

        return InkWell(
          borderRadius: BorderRadius.circular(AppRadius.pill),
          onTap: () => context.read<TrackingCubit>().requestPermission(),
          child: pill,
        );
      },
    );
  }

  _TrackingBannerContent _contentFor(TrackingState state) {
    if (!state.isActive) {
      return _TrackingBannerContent(
        label: state.availability.label,
        color: AppColors.danger,
        correctable: true,
      );
    }

    if (!state.isOnline) {
      return _TrackingBannerContent(
        label: 'Suivi actif · hors ligne'
            '${state.pendingCount > 0 ? ' · ${state.pendingCount} en attente' : ''}',
        color: AppColors.warning,
      );
    }

    if (state.isSyncing) {
      return const _TrackingBannerContent(
        label: 'Suivi actif · synchronisation…',
        color: AppColors.info,
      );
    }

    return _TrackingBannerContent(
      label: state.pendingCount > 0
          ? 'Suivi actif · ${state.pendingCount} en attente'
          : 'Suivi actif',
      color: AppColors.success,
    );
  }
}

class _TrackingBannerContent {
  const _TrackingBannerContent({
    required this.label,
    required this.color,
    this.correctable = false,
  });

  final String label;
  final Color color;
  final bool correctable;
}
