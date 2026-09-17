import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../cubit/sync_cubit.dart';

/// Écran Synchro (empilé, accessible depuis l'onglet Profil — voir échange
/// du 17/08/2026, la bottom nav n'a plus d'onglet Synchro dédié) :
/// indicateur de connexion détaillé + file d'attente des opérations en
/// attente (embarquements/refus/descentes saisis pendant que l'app était
/// considérée hors-ligne). Bascule manuelle en/hors-ligne pour la
/// démonstration — voir document.md § Synchro.
class SynchroPage extends StatelessWidget {
  const SynchroPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const ScreenHeader(eyebrow: 'Connexion', title: 'Synchro'),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: BlocBuilder<SyncCubit, SyncState>(
            builder: (BuildContext context, SyncState state) {
              final Color statusColor =
                  state.isOnline ? AppColors.success : AppColors.danger;

              return ListView(
                children: <Widget>[
                  KoraCard(
                    variant: KoraCardVariant.navy,
                    child: Row(
                      children: <Widget>[
                        Container(
                          width: 48,
                          height: 48,
                          decoration: BoxDecoration(
                            color: statusColor.withValues(alpha: 0.2),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            state.isOnline ? Icons.wifi : Icons.wifi_off,
                            color: statusColor,
                          ),
                        ),
                        const SizedBox(width: AppSpacing.md),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                state.isOnline ? 'Connecté' : 'Hors ligne',
                                style: const TextStyle(
                                  color: AppColors.white,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 16,
                                ),
                              ),
                              Text(
                                state.isOnline
                                    ? 'Les opérations se synchronisent automatiquement.'
                                    : 'Les opérations sont mises en file et envoyées au retour du réseau.',
                                style: TextStyle(
                                    color:
                                        AppColors.white.withValues(alpha: 0.7),
                                    fontSize: 12),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: KoraButton(
                          label: 'Simuler hors ligne',
                          variant: KoraButtonVariant.ghost,
                          onPressed: state.isOnline
                              ? () => context.read<SyncCubit>().goOffline()
                              : null,
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: KoraButton(
                          label: 'Repasser en ligne',
                          variant: KoraButtonVariant.ghost,
                          onPressed: state.isOnline
                              ? null
                              : () => context.read<SyncCubit>().goOnline(),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  if (state.lastSyncSummary != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.md),
                      child: KoraCard(
                        child: Row(
                          children: <Widget>[
                            const Icon(Icons.cloud_done,
                                color: AppColors.success, size: 18),
                            const SizedBox(width: AppSpacing.sm),
                            Expanded(
                              child: Text(
                                'Dernière synchro : '
                                '${state.lastSyncSummary!.accepted} accepté(s), '
                                '${state.lastSyncSummary!.rejected} rejeté(s), '
                                '${state.lastSyncSummary!.conflict} conflit(s).',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  if (state.lastSyncError != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: AppSpacing.md),
                      child: Text(
                        state.lastSyncError!,
                        style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                            fontSize: 12),
                      ),
                    ),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: <Widget>[
                      Text('File d\'attente (${state.totalPending})',
                          style: Theme.of(context).textTheme.titleMedium),
                      if (state.totalPending > 0)
                        TextButton(
                          onPressed: state.isOnline && !state.isSyncing
                              ? () => context.read<SyncCubit>().syncNow()
                              : null,
                          child: Text(state.isSyncing
                              ? 'Synchronisation…'
                              : 'Synchroniser maintenant'),
                        ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  if (state.pendingOperationsCount > 0)
                    KoraCard(
                      padding: const EdgeInsets.symmetric(
                          horizontal: AppSpacing.md, vertical: AppSpacing.sm),
                      child: Row(
                        children: <Widget>[
                          const Icon(Icons.confirmation_number_outlined,
                              color: AppColors.warning, size: 18),
                          const SizedBox(width: AppSpacing.sm),
                          Expanded(
                            child: Text(
                              '${state.pendingOperationsCount} opération(s) en '
                              'attente de synchronisation',
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ),
                        ],
                      ),
                    ),
                  if (state.totalPending == 0)
                    const AppStatePlaceholder.empty(
                        message: 'Aucune opération en attente.')
                  else
                    ...state.pending.map(
                      (op) => KoraCard(
                        padding: const EdgeInsets.symmetric(
                            horizontal: AppSpacing.md, vertical: AppSpacing.sm),
                        child: Row(
                          children: <Widget>[
                            const Icon(Icons.hourglass_top,
                                color: AppColors.warning, size: 18),
                            const SizedBox(width: AppSpacing.sm),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: <Widget>[
                                  Text(op.label,
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodyMedium),
                                  Text(
                                    op.createdAtLabel,
                                    style: TextStyle(
                                        color: context.koraColors.textMuted,
                                        fontSize: 11),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
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
