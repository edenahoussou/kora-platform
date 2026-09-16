import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_skeleton.dart';
import '../../../../core/widgets/passenger_list_tile.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';
import '../cubit/manifeste_cubit.dart';

/// Manifeste de la course sélectionnée depuis l'onglet Tournée (voir
/// échange du 17/08/2026 : le Manifeste n'est plus un onglet, on y accède
/// après avoir choisi une course). Liste des passagers (nom, siège, statut
/// d'embarquement, segment O/D). Route : `/tournee/manifeste`.
class ManifestePage extends StatelessWidget {
  const ManifestePage({super.key});

  @override
  Widget build(BuildContext context) {
    final Depart? depart = context.watch<SelectedDepartCubit>().state;

    return Scaffold(
      appBar: ScreenHeader(
        eyebrow: depart?.route ?? 'Manifeste',
        title: 'Manifeste',
        action: const SyncStatusBanner(compact: true),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: depart == null
              ? const AppStatePlaceholder.empty(
                  message: 'Sélectionnez une course depuis l\'onglet Tournée.',
                )
              : BlocBuilder<ManifesteCubit, ManifesteState>(
                  builder: (BuildContext context, ManifesteState state) {
                    final Widget content = switch (state.status) {
                      ManifesteStatus.noDepart =>
                        const AppStatePlaceholder.empty(
                          message:
                              'Sélectionnez une course depuis l\'onglet Tournée.',
                        ),
                      ManifesteStatus.loading => const SkeletonList(),
                      ManifesteStatus.error => AppStatePlaceholder.error(
                          message: state.errorMessage ?? 'Erreur inattendue.',
                          actionLabel: 'Réessayer',
                          onAction: () => context.read<ManifesteCubit>().load(),
                        ),
                      ManifesteStatus.success when state.passengers.isEmpty =>
                        const AppStatePlaceholder.empty(
                            message: 'Aucun passager sur ce départ.'),
                      ManifesteStatus.success => RefreshIndicator(
                          onRefresh: () =>
                              context.read<ManifesteCubit>().load(),
                          child: ListView.separated(
                            itemCount: state.passengers.length,
                            separatorBuilder: (_, __) => Divider(
                                height: 1, color: context.koraColors.line),
                            itemBuilder: (BuildContext context, int index) {
                              final passenger = state.passengers[index];
                              return PassengerListTile(
                                passenger: passenger,
                                onTap: () => context.push(
                                    AppRoutes.detailPassager,
                                    extra: passenger.id),
                              );
                            },
                          ),
                        ),
                    };

                    if (state.status != ManifesteStatus.success ||
                        !state.isStale) {
                      return content;
                    }

                    // Manifeste servi hors ligne (jamais vérifié à jour
                    // auprès du serveur) — voir critère d'acceptation
                    // MEZ-92 "signaler la version périmée avant
                    // l'embarquement".
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: <Widget>[
                        _StaleManifestBanner(fetchedAt: state.fetchedAt),
                        const SizedBox(height: AppSpacing.sm),
                        Expanded(child: content),
                      ],
                    );
                  },
                ),
        ),
      ),
    );
  }
}

class _StaleManifestBanner extends StatelessWidget {
  const _StaleManifestBanner({this.fetchedAt});

  final DateTime? fetchedAt;

  @override
  Widget build(BuildContext context) {
    final String suffix = fetchedAt == null
        ? ''
        : ' (${fetchedAt!.hour.toString().padLeft(2, '0')}:'
            '${fetchedAt!.minute.toString().padLeft(2, '0')})';

    return Container(
      padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md, vertical: AppSpacing.sm),
      decoration: BoxDecoration(
        color: Colors.orange.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: <Widget>[
          const Icon(Icons.cloud_off, size: 18, color: Colors.orange),
          const SizedBox(width: AppSpacing.sm),
          Expanded(
            child: Text(
              'Hors ligne — dernière version connue$suffix, non vérifiée '
              'auprès du serveur.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
        ],
      ),
    );
  }
}
