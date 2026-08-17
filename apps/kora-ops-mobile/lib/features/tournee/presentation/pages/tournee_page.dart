import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/main_app_bar.dart';
import '../../../ops/data/mock_ops_data_source.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';

/// Onglet Tournée : liste des courses de l'agent (voir échange du
/// 17/08/2026 — on prend d'abord par la tournée, le Manifeste n'est plus
/// un onglet séparé). Sélectionner une course l'enregistre comme départ
/// géré et ouvre directement son Manifeste.
class TourneePage extends StatelessWidget {
  const TourneePage({super.key});

  @override
  Widget build(BuildContext context) {
    final Depart? current = context.watch<SelectedDepartCubit>().state;

    return Scaffold(
      appBar: const MainAppBar(eyebrow: 'Vos courses', title: 'Tournée'),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              const SizedBox(height: AppSpacing.sm),
              const Align(
                  alignment: Alignment.centerLeft, child: SyncStatusBanner()),
              const SizedBox(height: AppSpacing.sm),
              Expanded(
                child: ListView.separated(
                  itemCount: MockOpsDataSource.departs.length,
                  separatorBuilder: (_, __) =>
                      const SizedBox(height: AppSpacing.sm),
                  itemBuilder: (BuildContext context, int index) {
                    final Depart depart = MockOpsDataSource.departs[index];
                    final bool isSelected = depart.id == current?.id;

                    return KoraCard(
                      variant: isSelected
                          ? KoraCardVariant.gold
                          : KoraCardVariant.surface,
                      onTap: () {
                        context.read<SelectedDepartCubit>().select(depart);
                        context.push(AppRoutes.manifeste);
                      },
                      child: Row(
                        children: <Widget>[
                          Icon(
                            Icons.directions_bus_filled,
                            color: isSelected
                                ? AppColors.onGold
                                : context.koraColors.textPrimary,
                          ),
                          const SizedBox(width: AppSpacing.md),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  depart.route,
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleMedium
                                      ?.copyWith(
                                        color: isSelected
                                            ? AppColors.onGold
                                            : null,
                                      ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  '${depart.date} · ${depart.heure} · ${depart.vehiculeImmat}',
                                  style: TextStyle(
                                    color: isSelected
                                        ? AppColors.onGold
                                            .withValues(alpha: 0.7)
                                        : context.koraColors.textMuted,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Text(
                            '${depart.placesOccupees}/${depart.totalPlaces}',
                            style: TextStyle(
                              fontWeight: FontWeight.w700,
                              color: isSelected
                                  ? AppColors.onGold
                                  : context.koraColors.textPrimary,
                            ),
                          ),
                          Icon(
                            Icons.chevron_right,
                            color: isSelected
                                ? AppColors.onGold
                                : context.koraColors.textMuted,
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
