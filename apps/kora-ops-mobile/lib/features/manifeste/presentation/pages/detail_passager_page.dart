import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/statut_badge.dart';
import '../../../../core/widgets/ticket_card.dart';
import '../../../ops/data/mock_ops_data_source.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../synchro/presentation/widgets/sync_status_banner.dart';
import '../cubit/manifeste_cubit.dart';

/// Détail d'un passager — infos billet, segment, siège, statut. Écran
/// empilé au-dessus de la bottom nav, ouvert depuis une ligne du Manifeste
/// (`context.push`). Route : `/manifeste/passager` (id transmis en `extra`).
class DetailPassagerPage extends StatelessWidget {
  const DetailPassagerPage({required this.passengerId, super.key});

  final String passengerId;

  @override
  Widget build(BuildContext context) {
    final Passenger? passenger =
        context.watch<ManifesteCubit>().byId(passengerId);
    final Depart? depart = context.watch<SelectedDepartCubit>().state;
    final String departRoute = depart?.route ?? '—';

    if (passenger == null) {
      return const Scaffold(body: Center(child: Text('Passager introuvable.')));
    }

    return Scaffold(
      appBar: ScreenHeader(
        eyebrow: 'Passager',
        title: passenger.nomComplet,
        action: const SyncStatusBanner(compact: true),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: ListView(
            children: <Widget>[
              KoraCard(
                variant: KoraCardVariant.navy,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      departRoute,
                      style: const TextStyle(
                        color: AppColors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Row(
                      children: <Widget>[
                        _NavyStat(label: 'Siège', value: passenger.siege),
                        _NavyStat(
                            label: 'Origine', value: passenger.segmentOrigine),
                        _NavyStat(
                            label: 'Destination',
                            value: passenger.segmentDestination),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              KoraCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text('Billet',
                        style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: AppSpacing.sm),
                    _InfoRow(
                        label: 'Numéro',
                        value: passenger.ticketId.toUpperCase()),
                    _InfoRow(
                        label: 'Téléphone', value: passenger.telephone ?? '—'),
                    const SizedBox(height: AppSpacing.sm),
                    Row(
                      children: <Widget>[
                        Text('Statut : ',
                            style: Theme.of(context).textTheme.bodyMedium),
                        StatutBadge(statut: passenger.statut),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Row(
                children: <Widget>[
                  Expanded(
                    child: KoraButton(
                      label: 'Embarquer',
                      variant: KoraButtonVariant.primary,
                      onPressed: passenger.statut == BoardingStatus.embarque
                          ? null
                          : () =>
                              _updateStatut(context, BoardingStatus.embarque),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: KoraButton(
                      label: 'Refuser',
                      variant: KoraButtonVariant.danger,
                      onPressed: passenger.statut == BoardingStatus.refuse
                          ? null
                          : () => _updateStatut(context, BoardingStatus.refuse),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              KoraButton(
                label: 'Descente anticipée',
                variant: KoraButtonVariant.ghost,
                icon: Icons.move_down,
                onPressed: passenger.statut == BoardingStatus.descenduAnticipe
                    ? null
                    : () =>
                        _updateStatut(context, BoardingStatus.descenduAnticipe),
              ),
              const SizedBox(height: AppSpacing.sm),
              KoraButton(
                label: 'Voir le billet',
                variant: KoraButtonVariant.ghost,
                icon: Icons.confirmation_number_outlined,
                onPressed: depart == null
                    ? null
                    : () => _showTicket(context, depart, passenger),
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showTicket(
      BuildContext context, Depart depart, Passenger passenger) {
    final ticket = MockOpsDataSource.ticketFor(passenger, depart.id);
    return showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (BuildContext context) => Padding(
        padding: EdgeInsets.fromLTRB(
          AppSpacing.md,
          AppSpacing.lg,
          AppSpacing.md,
          MediaQuery.viewInsetsOf(context).bottom + AppSpacing.lg,
        ),
        child: TicketCard(
          ticket: ticket,
          passengerName: passenger.nomComplet,
          route: depart.route,
          date: depart.date,
          heure: depart.heure,
        ),
      ),
    );
  }

  Future<void> _updateStatut(
      BuildContext context, BoardingStatus statut) async {
    context.read<ManifesteCubit>().updateStatut(passengerId, statut);
    await KoraDialogs.success(context,
        title: 'Statut mis à jour', description: statut.label);
  }
}

class _NavyStat extends StatelessWidget {
  const _NavyStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(label,
              style: TextStyle(
                  color: AppColors.white.withValues(alpha: 0.6), fontSize: 10)),
          const SizedBox(height: 2),
          Text(
            value,
            style: const TextStyle(
                color: AppColors.white,
                fontWeight: FontWeight.w700,
                fontSize: 13),
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          Text(label,
              style:
                  TextStyle(color: context.koraColors.textMuted, fontSize: 12)),
          Text(value,
              style: Theme.of(context)
                  .textTheme
                  .bodyMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}
