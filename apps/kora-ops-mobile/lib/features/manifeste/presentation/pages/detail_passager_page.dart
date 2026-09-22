import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:uuid/uuid.dart';
import '../../../../app/theme/app_colors.dart';
import '../../../../app/theme/app_radius.dart';
import '../../../../app/theme/app_spacing.dart';
import '../../../../app/theme/kora_colors.dart';
import '../../../../core/services/kora_dialogs.dart';
import '../../../../core/widgets/action_speed_dial.dart';
import '../../../../core/widgets/kora_button.dart';
import '../../../../core/widgets/kora_card.dart';
import '../../../../core/widgets/screen_header.dart';
import '../../../../core/widgets/statut_badge.dart';
import '../../../../core/widgets/ticket_sheet.dart';
import '../../../../injection/injection.dart';
import '../../../auth/presentation/cubit/auth_cubit.dart';
import '../../../boarding/domain/repositories/boarding_repository.dart';
import '../../../ops/data/mock_ops_data_source.dart';
import '../../../ops/domain/entities/depart.dart';
import '../../../ops/domain/entities/passenger.dart';
import '../../../ops/domain/entities/stop.dart';
import '../../../ops/presentation/cubit/selected_depart_cubit.dart';
import '../../../seat_release/domain/compatible_stops.dart';
import '../../../seat_release/domain/entities/seat_release_outcome.dart';
import '../../../seat_release/domain/entities/seat_release_outcome_status.dart';
import '../../../seat_release/domain/repositories/seat_release_repository.dart';
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
    final AuthState authState = context.watch<AuthCubit>().state;
    final bool canReverseBoarding = authState is AuthAuthenticated &&
        authState.user.can('boarding.reverse');
    final bool isPendingRelease = context
        .watch<ManifesteCubit>()
        .state
        .pendingReleaseTicketUuids
        .contains(passengerId);

    if (passenger == null) {
      return const Scaffold(body: Center(child: Text('Passager introuvable.')));
    }

    // "Annuler l'embarquement" (MEZ-87) : jamais l'un sans l'autre — voir
    // docstring `Passenger.boardingEventUuid` (reste renseigné même après
    // une annulation, donc `statut == embarque` est le signal fiable).
    final bool canShowReverseButton = canReverseBoarding &&
        passenger.statut == BoardingStatus.embarque &&
        passenger.boardingEventUuid != null;

    // Actions du Speed Dial (point 2 de la refonte UI/UX) : mêmes 3
    // actions réelles qu'avant (Descente anticipée / Annuler
    // l'embarquement / Voir le billet), seulement listées quand elles
    // sont réellement exécutables — un item désactivé n'a pas sa place
    // dans un menu flottant. Remplace l'empilement de KoraButton
    // ci-dessous (conservé en commentaire).
    final List<SpeedDialAction> speedDialActions = <SpeedDialAction>[
      if (depart != null && passenger.statut == BoardingStatus.embarque)
        SpeedDialAction(
          icon: Icons.move_down,
          label: 'Descente anticipée',
          onPressed: () => _releaseEarlyDropOff(context, depart, passenger),
        ),
      if (canShowReverseButton)
        SpeedDialAction(
          icon: Icons.undo,
          label: 'Annuler l\'embarquement',
          danger: true,
          onPressed: () => _reverseBoarding(context, passenger),
        ),
      if (depart != null)
        SpeedDialAction(
          icon: Icons.confirmation_number_outlined,
          label: 'Voir le billet',
          onPressed: () => _showTicket(context, depart, passenger),
        ),
    ];

    return Scaffold(
      appBar: ScreenHeader(
        eyebrow: 'Passager',
        title: passenger.nomComplet,
        action: const SyncStatusBanner(compact: true),
      ),
      floatingActionButton: speedDialActions.isEmpty
          ? null
          : ActionSpeedDial(actions: speedDialActions),
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
                        if (isPendingRelease) ...<Widget>[
                          const SizedBox(width: AppSpacing.sm),
                          const _PendingSyncTag(),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              // "Embarquer"/"Refuser" (MEZ-11, mock) retirés : ce sont des
              // restes de l'écran mocké d'origine — ils ne faisaient que
              // changer un statut local (ManifesteCubit.updateStatut, file
              // SyncCubit mock), sans jamais appeler le vrai contrat de
              // boarding. Depuis MEZ-91/92, "embarquer" EST le scan
              // (ScanBoardingCubit → BoardingRepository.scan) — il n'y a
              // pas d'action serveur "refuser" côté passager scanné
              // (BoardingStatus.refuse n'existe qu'en affichage, mappé
              // depuis un vrai cancelled/no-show serveur — voir
              // manifeste_dtos.dart et la clôture no-show plus haut).
              // Garder ces deux boutons ici aurait fait croire au
              // convoyeur qu'ils font quelque chose de réel.
              // Row(
              //   children: <Widget>[
              //     Expanded(
              //       child: KoraButton(
              //         label: 'Embarquer',
              //         variant: KoraButtonVariant.primary,
              //         onPressed: passenger.statut == BoardingStatus.embarque
              //             ? null
              //             : () =>
              //                 _updateStatut(context, BoardingStatus.embarque),
              //       ),
              //     ),
              //     const SizedBox(width: AppSpacing.sm),
              //     Expanded(
              //       child: KoraButton(
              //         label: 'Refuser',
              //         variant: KoraButtonVariant.danger,
              //         onPressed: passenger.statut == BoardingStatus.refuse
              //             ? null
              //             : () => _updateStatut(context, BoardingStatus.refuse),
              //       ),
              //     ),
              //   ],
              // ),
              // const SizedBox(height: AppSpacing.sm),
              // Empilement remplacé par le FloatingActionButton Speed
              // Dial (voir speedDialActions / _PassengerActionsSpeedDial
              // ci-dessous) — refonte UI/UX du 21/09/2026, point 2.
              // KoraButton(
              //   label: 'Descente anticipée',
              //   variant: KoraButtonVariant.ghost,
              //   icon: Icons.move_down,
              //   // Une descente anticipée n'a de sens que pour un passager
              //   // déjà embarqué sur CE départ — voir
              //   // `SeatReleaseFunctionalCode.SegmentNotBoarded` côté
              //   // backend, qui rejetterait sinon.
              //   onPressed: depart == null ||
              //           passenger.statut != BoardingStatus.embarque
              //       ? null
              //       : () => _releaseEarlyDropOff(context, depart, passenger),
              // ),
              // if (canShowReverseButton) ...<Widget>[
              //   const SizedBox(height: AppSpacing.sm),
              //   KoraButton(
              //     label: 'Annuler l\'embarquement',
              //     variant: KoraButtonVariant.danger,
              //     icon: Icons.undo,
              //     onPressed: () => _reverseBoarding(context, passenger),
              //   ),
              // ],
              // const SizedBox(height: AppSpacing.sm),
              // KoraButton(
              //   label: 'Voir le billet',
              //   variant: KoraButtonVariant.ghost,
              //   icon: Icons.confirmation_number_outlined,
              //   onPressed: depart == null
              //       ? null
              //       : () => _showTicket(context, depart, passenger),
              // ),
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
    return showTicketSheet(
      context,
      ticket: ticket,
      passengerName: passenger.nomComplet,
      route: depart.route,
      date: depart.date,
      heure: depart.heure,
    );
  }

  // Retiré avec les boutons Embarquer/Refuser ci-dessus — ne touchait que
  // le manifeste local, jamais le serveur. Voir le commentaire au-dessus.
  // Future<void> _updateStatut(
  //     BuildContext context, BoardingStatus statut) async {
  //   context.read<ManifesteCubit>().updateStatut(passengerId, statut);
  //   await KoraDialogs.success(context,
  //       title: 'Statut mis à jour', description: statut.label);
  // }

  /// Descente anticipée réelle (MEZ-87) : sélection de l'arrêt réel parmi
  /// les arrêts compatibles, puis confirmation — voir
  /// `SeatReleaseRepository.release`. Le serveur reste seul arbitre de la
  /// compatibilité, `compatibleStopsFor` n'est qu'un confort d'UX.
  Future<void> _releaseEarlyDropOff(
      BuildContext context, Depart depart, Passenger passenger) async {
    final ManifesteCubit manifesteCubit = context.read<ManifesteCubit>();
    final List<Stop> stops = compatibleStopsFor(depart, passenger);

    final Stop? actualStop = await showModalBottomSheet<Stop>(
      context: context,
      isScrollControlled: true,
      builder: (BuildContext sheetContext) =>
          _StopPickerSheet(stops: stops, passenger: passenger),
    );
    if (actualStop == null || !context.mounted) return;

    final bool confirmed = await KoraDialogs.confirm(
      context,
      title: 'Confirmer la descente anticipée ?',
      description:
          '${passenger.nomComplet} sera signalé descendu à ${actualStop.name}.',
      confirmLabel: 'Confirmer',
    );
    if (!confirmed || !context.mounted) return;

    KoraDialogs.showLoading(context, message: 'Enregistrement…');
    final result = await getIt<SeatReleaseRepository>().release(
      departureUuid: depart.id,
      ticketUuid: passenger.id,
      actualStopUuid: actualStop.uuid,
      operationUuid: const Uuid().v4(),
      localRecordedAt: DateTime.now(),
    );
    if (!context.mounted) return;
    KoraDialogs.hideLoading(context);

    await result.when(
      onSuccess: (outcome) async {
        await _handleSeatReleaseOutcome(context, manifesteCubit, outcome,
            fallbackTicketUuid: passenger.id,
            acceptedStatut: BoardingStatus.descenduAnticipe);
      },
      onFailure: (failure) => KoraDialogs.error(context,
          title: 'Descente anticipée', description: failure.message),
    );
  }

  Future<void> _reverseBoarding(
      BuildContext context, Passenger passenger) async {
    final ManifesteCubit manifesteCubit = context.read<ManifesteCubit>();
    final String? reason = await KoraDialogs.prompt(
      context,
      title: 'Annuler l\'embarquement',
      description: 'Motif obligatoire (visible dans l\'historique) :',
      confirmLabel: 'Annuler l\'embarquement',
      minLength: 5,
      hintText: 'Ex. erreur de scan, mauvais passager…',
    );
    if (reason == null || !context.mounted) return;

    KoraDialogs.showLoading(context, message: 'Annulation…');
    final result = await getIt<BoardingRepository>().reverse(
      boardingEventUuid: passenger.boardingEventUuid!,
      reason: reason,
    );
    if (!context.mounted) return;
    KoraDialogs.hideLoading(context);

    await result.when(
      onSuccess: (_) async {
        await manifesteCubit.markBoardingReversed(passenger.id);
        if (context.mounted) {
          await KoraDialogs.success(context,
              title: 'Embarquement annulé',
              description: '${passenger.nomComplet} redevient "en attente".');
        }
      },
      onFailure: (failure) => KoraDialogs.error(context,
          title: 'Annulation impossible', description: failure.message),
    );
  }

  Future<void> _handleSeatReleaseOutcome(
    BuildContext context,
    ManifesteCubit manifesteCubit,
    SeatReleaseOutcome outcome, {
    required String fallbackTicketUuid,
    required BoardingStatus acceptedStatut,
  }) async {
    final String ticketUuid = outcome.ticketUuid ?? fallbackTicketUuid;

    switch (outcome.status) {
      case SeatReleaseOutcomeStatus.accepted:
        await manifesteCubit.markSeatReleaseAccepted(
            ticketUuid, acceptedStatut);
        if (context.mounted) {
          await KoraDialogs.success(context,
              title: 'Libération enregistrée', description: outcome.message);
        }
      case SeatReleaseOutcomeStatus.pendingSync:
        manifesteCubit.markSeatReleasePending(ticketUuid);
        if (context.mounted) {
          await KoraDialogs.warning(context,
              title: 'Enregistré hors ligne', description: outcome.message);
        }
      case SeatReleaseOutcomeStatus.conflict:
      case SeatReleaseOutcomeStatus.rejected:
      case SeatReleaseOutcomeStatus.error:
        if (context.mounted) {
          await KoraDialogs.error(
            context,
            title: outcome.status == SeatReleaseOutcomeStatus.conflict
                ? 'Conflit'
                : 'Libération refusée',
            description: <String>[
              outcome.message,
              if (outcome.recommendedAction != null) outcome.recommendedAction!,
            ].join('\n'),
          );
        }
    }
  }
}

/// Le bottom sheet "Voir le billet" vit désormais dans
/// `core/widgets/ticket_sheet.dart` (`showTicketSheet` / `TicketSheet`)
/// — partagé avec `DescenteAnticipeePage` (tap sur un siège, refonte
/// UI/UX du 21/09/2026).

class _StopPickerSheet extends StatelessWidget {
  const _StopPickerSheet({required this.stops, required this.passenger});

  final List<Stop> stops;
  final Passenger passenger;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.md),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Arrêt de descente',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(
              'Entre ${passenger.segmentOrigine} et ${passenger.segmentDestination}',
              style:
                  TextStyle(color: context.koraColors.textMuted, fontSize: 12),
            ),
            const SizedBox(height: AppSpacing.md),
            if (stops.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: AppSpacing.lg),
                child: Text('Aucun arrêt disponible pour ce billet.'),
              )
            else
              ConstrainedBox(
                constraints: BoxConstraints(
                    maxHeight: MediaQuery.sizeOf(context).height * 0.5),
                child: ListView.separated(
                  shrinkWrap: true,
                  itemCount: stops.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (BuildContext context, int index) {
                    final Stop stop = stops[index];
                    return ListTile(
                      title: Text(stop.name),
                      subtitle: stop.city == null ? null : Text(stop.city!),
                      onTap: () => Navigator.of(context).pop(stop),
                    );
                  },
                ),
              ),
            const SizedBox(height: AppSpacing.sm),
          ],
        ),
      ),
    );
  }
}

class _PendingSyncTag extends StatelessWidget {
  const _PendingSyncTag();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.orange.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(20),
      ),
      child: const Text(
        'En attente de sync',
        style: TextStyle(
            fontSize: 10, color: Colors.orange, fontWeight: FontWeight.w700),
      ),
    );
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

/// Le Speed Dial des actions ci-dessus vit désormais dans
/// `core/widgets/action_speed_dial.dart` (`SpeedDialAction` /
/// `ActionSpeedDial`) — partagé avec `ManifestePage`, qui a le même
/// besoin (refonte UI/UX du 21/09/2026).
