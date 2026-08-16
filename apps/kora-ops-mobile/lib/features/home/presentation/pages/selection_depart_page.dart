import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../ops/data/mock_ops_data_source.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';

/// Sélection du départ à gérer — accessible en écran plein depuis
/// `AccueilPage` **et** depuis le sélecteur de l'en-tête du Manifeste (voir
/// document.md § Sélection du départ). Route : `/depart/selection`.
class SelectionDepartPage extends StatelessWidget {
  const SelectionDepartPage({super.key});

  @override
  Widget build(BuildContext context) {
    final Depart? current = context.watch<SelectedDepartCubit>().state;

    return Scaffold(
      appBar: const ScreenHeader(
        eyebrow: 'Tournée',
        title: 'Sélection du départ',
        action: SyncStatusBanner(compact: true),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: ListView.separated(
            itemCount: MockOpsDataSource.departs.length,
            separatorBuilder: (_, __) => const SizedBox(height: AppSpacing.sm),
            itemBuilder: (BuildContext context, int index) {
              final Depart depart = MockOpsDataSource.departs[index];
              final bool isSelected = depart.id == current?.id;

              return KoraCard(
                variant:
                    isSelected ? KoraCardVariant.gold : KoraCardVariant.surface,
                onTap: () async {
                  context.read<SelectedDepartCubit>().select(depart);
                  await KoraDialogs.success(
                    context,
                    title: 'Départ sélectionné',
                    description: depart.route,
                  );
                  if (context.mounted) context.pop();
                },
                child: Row(
                  children: <Widget>[
                    Icon(
                      Icons.directions_bus_filled,
                      color: isSelected ? AppColors.onGold : AppColors.kora,
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
                                  color: isSelected ? AppColors.onGold : null,
                                ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            '${depart.date} · ${depart.heure} · ${depart.vehiculeImmat}',
                            style: TextStyle(
                              color: isSelected
                                  ? AppColors.onGold.withValues(alpha: 0.7)
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
                        color: isSelected ? AppColors.onGold : AppColors.kora,
                      ),
                    ),
                    if (isSelected) ...<Widget>[
                      const SizedBox(width: 8),
                      const Icon(Icons.check_circle,
                          color: AppColors.onGold, size: 20),
                    ],
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}
