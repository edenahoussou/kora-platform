import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:uuid/uuid.dart';
import '../../../../app/router/app_routes.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/app_state_placeholder.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/kora_skeleton.dart';
import '../../../../core/widgets/passenger_list_tile.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../injection/injection.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../seat_release/domain/entities/seat_release_outcome.dart';
import '../../../seat_release/domain/entities/seat_release_outcome_status.dart';
import '../../../seat_release/domain/repositories/seat_release_repository.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';
import '../cubit/manifeste_cubit.dart';

/// Manifeste de la course sélectionnée depuis l'onglet Tournée (voir
/// échange du 17/08/2026 : le Manifeste n'est plus un onglet, on y accède
/// après avoir choisi une course). Liste des passagers (nom, siège, statut
/// d'embarquement, segment O/D). Route : `/tournee/manifeste`.
class ManifestePage extends StatelessWidget {
  const ManifestePage({super.key});

  bool _canCloseNoShows(Depart depart, ManifesteState state) {
    if (state.status != ManifesteStatus.success) return false;
    final bool hasUncontrolled =
        state.passengers.any((Passenger p) => p.statut == BoardingStatus.enAttente);
    if (!hasUncontrolled) return false;

    // Confort d'UX seulement : si le statut du départ n'est pas encore
    // connu localement, on laisse le bouton visible et le serveur
    // tranche (`departure_not_closable`, voir `CloseNoShowsForDeparture
    // Action`) plutôt que de priver l'agent de l'action.
    if (depart.status == null) return true;
    return depart.status == 'departed' || depart.status == 'arrived';
  }

  Future<void> _closeNoShows(
      BuildContext context, Depart depart, ManifesteState state) async {
    final ManifesteCubit manifesteCubit = context.read<ManifesteCubit>();
    final List<Passenger> uncontrolled = state.passengers
        .where((Passenger p) => p.statut == BoardingStatus.enAttente)
        .toList();

    final bool confirmed = await KoraDialogs.confirm(
      context,
      title: 'Clôturer les no-show ?',
      description: '${uncontrolled.length} passager(s) non contrôlé(s) '
          'seront marqués no-show :\n'
          '${uncontrolled.map((p) => '• ${p.nomComplet} (${p.siege})').join('\n')}',
      confirmLabel: 'Clôturer',
    );
    if (!confirmed || !context.mounted) return;

    KoraDialogs.showLoading(context, message: 'Clôture en cours…');
    final result = await getIt<SeatReleaseRepository>().closeNoShows(
      departureUuid: depart.id,
      uncontrolledTicketUuids: uncontrolled.map((p) => p.id).toList(),
      operationUuid: const Uuid().v4(),
      localRecordedAt: DateTime.now(),
    );
    if (!context.mounted) return;
    KoraDialogs.hideLoading(context);

    await result.when(
      onSuccess: (outcomes) async {
        int accepted = 0, pending = 0, rejected = 0;
        for (final SeatReleaseOutcome outcome in outcomes) {
          final String? ticketUuid = outcome.ticketUuid;
          if (ticketUuid == null) continue;
          switch (outcome.status) {
            case SeatReleaseOutcomeStatus.accepted:
              accepted++;
              await manifesteCubit.markSeatReleaseAccepted(
                  ticketUuid, BoardingStatus.refuse);
            case SeatReleaseOutcomeStatus.pendingSync:
              pending++;
              manifesteCubit.markSeatReleasePending(ticketUuid);
            default:
              rejected++;
          }
        }
        if (context.mounted) {
          await KoraDialogs.success(
            context,
            title: 'Clôture no-show',
            description: pending > 0
                ? '$pending en attente de synchronisation, $accepted '
                    'accepté(s), $rejected rejeté(s).'
                : '$accepted accepté(s), $rejected rejeté(s).',
          );
        }
      },
      onFailure: (failure) => KoraDialogs.error(context,
          title: 'Clôture no-show', description: failure.message),
    );
  }

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

                    final Widget body = (state.status != ManifesteStatus.success ||
                            !state.isStale)
                        ? content
                        : Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: <Widget>[
                              _StaleManifestBanner(fetchedAt: state.fetchedAt),
                              const SizedBox(height: AppSpacing.sm),
                              Expanded(child: content),
                            ],
                          );

                    if (!_canCloseNoShows(depart, state)) return body;

                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: <Widget>[
                        Expanded(child: body),
                        const SizedBox(height: AppSpacing.sm),
                        KoraButton(
                          label: 'Clôturer les no-show',
                          variant: KoraButtonVariant.danger,
                          icon: Icons.event_busy,
                          onPressed: () =>
                              _closeNoShows(context, depart, state),
                        ),
                        const SizedBox(height: AppSpacing.sm),
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
