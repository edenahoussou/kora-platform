import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/app_text_styles.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_skeleton.dart';
import '../../../../core/widgets/passenger_list_tile.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';
import '../cubit/manifeste_cubit.dart';

/// Onglet Manifeste : liste des passagers du départ géré (nom, siège,
/// statut d'embarquement, segment O/D). Sélecteur de départ dans l'en-tête
/// — voir document.md § Sélection du départ (accessible aussi depuis
/// l'accueil).
class ManifestePage extends StatelessWidget {
  const ManifestePage({super.key});

  @override
  Widget build(BuildContext context) {
    final Depart? depart = context.watch<SelectedDepartCubit>().state;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Manifeste'),
        actions: const <Widget>[
          Padding(
              padding: EdgeInsets.only(right: AppSpacing.md),
              child: SyncStatusBanner(compact: true))
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              const SizedBox(height: AppSpacing.sm),
              _DepartSelector(depart: depart),
              const SizedBox(height: AppSpacing.sm),
              Expanded(
                child: depart == null
                    ? const AppStatePlaceholder.empty(
                        message:
                            'Sélectionnez un départ à gérer pour afficher son manifeste.',
                      )
                    : BlocBuilder<ManifesteCubit, ManifesteState>(
                        builder: (BuildContext context, ManifesteState state) {
                          return switch (state.status) {
                            ManifesteStatus.noDepart =>
                              const AppStatePlaceholder.empty(
                                message: 'Sélectionnez un départ à gérer.',
                              ),
                            ManifesteStatus.loading => const SkeletonList(),
                            ManifesteStatus.error => AppStatePlaceholder.error(
                                message:
                                    state.errorMessage ?? 'Erreur inattendue.',
                                actionLabel: 'Réessayer',
                                onAction: () =>
                                    context.read<ManifesteCubit>().load(),
                              ),
                            ManifesteStatus.success
                                when state.passengers.isEmpty =>
                              const AppStatePlaceholder.empty(
                                  message: 'Aucun passager sur ce départ.'),
                            ManifesteStatus.success => RefreshIndicator(
                                onRefresh: () =>
                                    context.read<ManifesteCubit>().load(),
                                child: ListView.separated(
                                  itemCount: state.passengers.length,
                                  separatorBuilder: (_, __) => Divider(
                                      height: 1,
                                      color: context.koraColors.line),
                                  itemBuilder:
                                      (BuildContext context, int index) {
                                    final passenger = state.passengers[index];
                                    return PassengerListTile(
                                      passenger: passenger,
                                      onTap: () => context.push(
                                        AppRoutes.detailPassager,
                                        extra: passenger.id,
                                      ),
                                    );
                                  },
                                ),
                              ),
                          };
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

class _DepartSelector extends StatelessWidget {
  const _DepartSelector({required this.depart});

  final Depart? depart;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => context.push(AppRoutes.selectionDepart),
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.sm),
        decoration: BoxDecoration(
          color: context.koraColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: context.koraColors.line),
        ),
        child: Row(
          children: <Widget>[
            const Icon(Icons.directions_bus_filled, color: AppColors.kora),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text('Départ géré',
                      style:
                          AppTextStyles.eyebrow(context.koraColors.textMuted)),
                  Text(
                    depart?.route ?? 'Aucun départ sélectionné',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ],
              ),
            ),
            const Icon(Icons.swap_horiz, color: AppColors.digital),
          ],
        ),
      ),
    );
  }
}
